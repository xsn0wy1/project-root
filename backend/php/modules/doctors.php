<?php
declare(strict_types=1);

use Hospital\Http\ApiException;

// Doctor field validation.
const DOCTOR_INPUT_FIELDS = ['title','full_name','national_id','license','specialty','department','office','phone','email','availability','shift','days','start_time','end_time','notes'];

function doctor_input_invalid(string $field) : void
{
    throw new ApiException(422,'VALIDATION_FAILED',\Hospital\I18n::t("errors.revisa_los_datos_del_medico"),[$field=>\Hospital\I18n::t("errors.valor_no_valido")]);
}

function doctor_input_fields(array $input) : array
{
    foreach (DOCTOR_INPUT_FIELDS as $key) if (!array_key_exists($key,$input)) doctor_input_invalid($key);
    $f = [];
    foreach (['title'=>8,'full_name'=>100,'license'=>30,'specialty'=>80,'department'=>80,'office'=>80,'email'=>120,'days'=>40] as $key=>$max) {
        $f[$key]=settings_text($input[$key], $key, $max, in_array($key,['office','email'],true));
    }
    if (!in_array($f['title'],['Dr.','Dra.'],true)) doctor_input_invalid('title');
    $f['national_id']=patient_input_identity($input['national_id']);
    $f['license']=strtoupper(preg_replace('/[\s.-]/u','',$f['license']));
    if (!preg_match('/^[A-Z0-9]{2,30}$/',$f['license'])) doctor_input_invalid('license');
    $f['phone']=patient_input_phone($input['phone'], 'phone');
    if (strlen(preg_replace('/\D/','',$f['phone']))<8) doctor_input_invalid('phone');
    if ($f['email']!=='' && !filter_var($f['email'],FILTER_VALIDATE_EMAIL)) doctor_input_invalid('email');
    foreach (['availability'=>['available','consulting','on_call','inactive'],'shift'=>['morning','afternoon','night','rotating'],'days'=>['weekdays','mon_wed_fri','tue_thu','weekends','rotating']] as $key=>$choices) {
        if (!in_array($input[$key],$choices,true)) doctor_input_invalid($key);
        $f[$key]=$input[$key];
    }
    foreach (['start_time','end_time'] as $key) {
        $v=$input[$key];
        if ($v!==null && $v!=='' && (!is_string($v) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$v))) doctor_input_invalid($key);
        $f[$key]=$v===''?null:$v;
    }
    if (($f['start_time']===null)!==($f['end_time']===null)) doctor_input_invalid('end_time');
    // Overnight shifts are valid. These hours describe the profile, not bookings.
    $notes=$input['notes'];
    if (!is_string($notes) || !mb_check_encoding($notes,'UTF-8')) doctor_input_invalid('notes');
    $f['notes']=trim(str_replace(["\r\n","\r"],"\n",$notes));
    if (mb_strlen($f['notes'])>500 || preg_match('/\p{C}/u',str_replace(["\n","\t"],'',$f['notes']))) doctor_input_invalid('notes');
    return $f;
}

// Doctor directory.
function doctors_transaction(PDO $db, array $config, string $actor,callable $work)
{
    return transfer_serialized($db, $config, function() use ($actor, $work, $db) {
        $db->beginTransaction();
        try {
            $u=users_by_id($db, $actor, true);
            if (!$u || $u['status']!=='active' || $u['staff_status']!=='active' || (int)$u['auth_version']!==(int)($_SESSION['auth_version']??0)) throw new ApiException(401,'SESSION_EXPIRED',\Hospital\I18n::t("errors.la_sesion_ya_no_tiene_acceso"));
            patients_access($db, $actor); $result=$work(); $db->commit(); return $result;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof \PDOException && ($e->errorInfo[1]??0)===1062) throw new ApiException(409,'DOCTOR_CONFLICT',\Hospital\I18n::t("errors.ya_existe_un_medico_con_ese_documento_o_registro_msp"));
            throw $e;
        }
    });
}

function doctors_query() : string
{
    return 'SELECT d.*,s.version AS staff_version,s.full_name,s.national_id,s.status AS staff_status,sp.name AS specialty,dep.name AS department FROM doctor_profiles d JOIN staff s ON s.id=d.staff_id JOIN specialties sp ON sp.id=d.specialty_id JOIN departments dep ON dep.id=d.department_id';
}

function doctors_serialize(array $r) : array
{
    $r['id']=(string)$r['staff_id']; unset($r['staff_id']);
    $r['specialty_id']=(string)$r['specialty_id']; $r['department_id']=(string)$r['department_id']; $r['version']=(int)$r['version']+(int)$r['staff_version']; unset($r['staff_version']);
    foreach (['start_time','end_time'] as $key) $r[$key]=$r[$key]===null?null:substr($r[$key],0,5);
    foreach (['created_at','updated_at','archived_at'] as $key) $r[$key]=$r[$key]===null?null:users_date($r[$key]);
    return $r;
}

function doctors_listing(PDO $db, string $actor) : array
{
    patients_access($db, $actor);
    // The school directory is small; retain the existing browser filters/pagination.
    return array_map(fn(array $r):array=>doctors_serialize($r),$db->query(doctors_query().' ORDER BY s.full_name,s.id')->fetchAll());
}

function doctors_get(PDO $db, string $actor,string $id) : array
{
    patients_access($db, $actor); $s=$db->prepare(doctors_query().' WHERE d.staff_id=?'); $s->execute([$id]); $r=$s->fetch();
    if (!$r) throw new ApiException(404,'DOCTOR_NOT_FOUND',\Hospital\I18n::t("errors.no_se_encontro_el_medico"));
    return doctors_serialize($r);
}

function doctors_catalogs(PDO $db, string $actor) : array
{
    patients_access($db, $actor);
    return ['specialties'=>$db->query("SELECT name FROM specialties WHERE status='active' ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN),'departments'=>$db->query("SELECT name FROM departments WHERE status='active' ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN)];
}

function doctors_lock(PDO $db, string $id,int $version) : array
{
    $s=$db->prepare('SELECT d.*,s.version AS staff_version FROM doctor_profiles d JOIN staff s ON s.id=d.staff_id WHERE d.staff_id=? FOR UPDATE'); $s->execute([$id]); $r=$s->fetch();
    if (!$r) throw new ApiException(404,'DOCTOR_NOT_FOUND',\Hospital\I18n::t("errors.no_se_encontro_el_medico"));
    if ((int)$r['version']+(int)$r['staff_version']!==$version) throw new ApiException(409,'VERSION_CONFLICT',\Hospital\I18n::t("errors.el_medico_cambio_volve_a_abrirlo_antes_de_guardar"));
    return $r;
}

function doctors_check_transfer(PDO $db, string $id) : void
{
    $s=$db->prepare("SELECT t.id FROM transfers t JOIN transfer_crew c ON c.transfer_id=t.id WHERE c.staff_id=? AND c.responsibility='doctor' AND t.status NOT IN ('completed','cancelled') LIMIT 1"); $s->execute([$id]);
    if ($s->fetchColumn()) throw new ApiException(409,'DOCTOR_IN_USE',\Hospital\I18n::t("errors.el_medico_tiene_un_traslado_pendiente_o_en_curso"));
}

function doctors_catalog_id(PDO $db, string $table,string $name) : string
{
    // Table names are internal constants; labels are always bound parameters.
    $s=$db->prepare("INSERT INTO $table (name) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)"); $s->execute([$name]); $id=$db->lastInsertId();
    $s=$db->prepare("SELECT status FROM $table WHERE id=?"); $s->execute([$id]);
    if ($s->fetchColumn()!=='active') throw new ApiException(422,'CATALOG_INACTIVE',\Hospital\I18n::t("errors.selecciona_una_especialidad_y_un_servicio_activos"));
    return $id;
}

function doctors_save(PDO $db, array $config, string $actor,array $input,?string $id=null) : array
{
    $f=doctor_input_fields($input); $version=$id===null?null:patient_input_version($input['version']??null);
    $id=doctors_transaction($db, $config, $actor, function() use ($actor, $f, $id, $version, $db, $config) {
        if ($id!==null) {
            $old=doctors_lock($db, $id, $version);
            if ($old['status']!=='active') throw new ApiException(409,'DOCTOR_ARCHIVED',\Hospital\I18n::t("errors.restaura_el_medico_antes_de_editarlo"));
            if ($f['availability']==='inactive') doctors_check_transfer($db, $id);
            $s=$db->prepare('SELECT registration_national_id FROM users WHERE staff_id=?'); $s->execute([$id]); $ci=$s->fetchColumn();
            if ($ci!==false && $ci!==$f['national_id']) throw new ApiException(409,'IDENTITY_LINKED',\Hospital\I18n::t("errors.el_documento_esta_vinculado_a_una_cuenta_y_no_puede_cambiarse_desde_este_directorio"));
        } else {
            $s=$db->prepare('SELECT id,status FROM staff WHERE national_id=? FOR UPDATE'); $s->execute([$f['national_id']]); $staff=$s->fetch();
            if ($staff) {
                if ($staff['status']!=='active') throw new ApiException(409,'STAFF_INACTIVE',\Hospital\I18n::t("errors.la_identidad_del_personal_esta_inactiva"));
                $id=(string)$staff['id'];
                $s=$db->prepare('SELECT staff_id FROM doctor_profiles WHERE staff_id=?'); $s->execute([$id]);
                if ($s->fetchColumn()) throw new ApiException(409,'DOCTOR_CONFLICT',\Hospital\I18n::t("errors.ya_existe_un_medico_con_ese_documento_revisa_tambien_los_inactivos"));
            } else {
                $s=$db->prepare('INSERT INTO staff (national_id,full_name) VALUES (?,?)'); $s->execute([$f['national_id'],$f['full_name']]); $id=$db->lastInsertId();
            }
        }
        $s=$db->prepare('UPDATE staff SET national_id=?,full_name=?,version=version+1 WHERE id=?'); $s->execute([$f['national_id'],$f['full_name'],$id]);
        $specialty=doctors_catalog_id($db, 'specialties', $f['specialty']); $department=doctors_catalog_id($db, 'departments', $f['department']);
        $values=[$f['title'],$f['license'],$specialty,$department,$f['office'],$f['phone'],$f['email'],$f['availability'],$f['shift'],$f['days'],$f['start_time'],$f['end_time'],$f['notes'],$id];
        if ($version===null) {
            $s=$db->prepare('INSERT INTO doctor_profiles (title,license,specialty_id,department_id,office,phone,email,availability,shift,days,start_time,end_time,notes,staff_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        } else {
            $s=$db->prepare('UPDATE doctor_profiles SET title=?,license=?,specialty_id=?,department_id=?,office=?,phone=?,email=?,availability=?,shift=?,days=?,start_time=?,end_time=?,notes=?,version=version+1 WHERE staff_id=?');
        }
        $s->execute($values);
        $s=$db->prepare("INSERT IGNORE INTO staff_roles (staff_id,role_code) VALUES (?,'doctor')"); $s->execute([$id]);
        transfer_audit($db, $actor,$version===null?'doctor.created':'doctor.updated',$id,'doctor');
        return $id;
    });
    return doctors_get($db, $actor, $id);
}

function doctors_archive(PDO $db, array $config, string $actor,string $id,int $version,bool $restore=false) : array
{
    doctors_transaction($db, $config, $actor, function() use ($actor, $id, $version, $restore, $db, $config) {
        $old=doctors_lock($db, $id, $version); $state=$restore?'active':'archived';
        if ($old['status']===$state) throw new ApiException(409,'VERSION_CONFLICT',\Hospital\I18n::t("errors.el_estado_del_medico_cambio"));
        if (!$restore) doctors_check_transfer($db, $id);
        // Keep shared staff and existing encounter/crew references intact.
        $s=$db->prepare('UPDATE doctor_profiles SET status=?,archived_at=?,version=version+1 WHERE staff_id=?'); $s->execute([$state,$restore?null:gmdate('Y-m-d H:i:s'),$id]);
        transfer_audit($db, $actor,$restore?'doctor.restored':'doctor.archived',$id,'doctor');
    });
    return doctors_get($db, $actor, $id);
}
