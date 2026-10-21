<?php
declare(strict_types=1);

use Hospital\Http\ApiException;

function transfer_catalog_selection(PDO $db): array
{
    $vehicles=$db->query("SELECT id,code,plate FROM ambulances WHERE status='active' AND operational_status='available' ORDER BY code LIMIT 1000")->fetchAll();
    $crew=$db->query("SELECT s.id,s.full_name,r.role_code FROM staff s JOIN staff_roles r ON r.staff_id=s.id LEFT JOIN doctor_profiles d ON d.staff_id=s.id LEFT JOIN personnel_profiles p ON p.staff_id=s.id WHERE s.status='active' AND (r.role_code<>'doctor' OR d.staff_id IS NULL OR (d.status='active' AND d.availability<>'inactive')) AND (r.role_code NOT IN ('driver','copilot') OR p.staff_id IS NULL OR (p.status='active' AND p.availability='active' AND p.responsibility=r.role_code)) ORDER BY s.full_name,s.id LIMIT 2000")->fetchAll();
    $facilities=$db->query("SELECT id,name,address,locality,facility_type FROM facilities WHERE status='active' AND availability='active' ORDER BY name,id LIMIT 1000")->fetchAll();
    $routeRows=$db->query("SELECT * FROM route_templates WHERE status='active' AND availability='active' ORDER BY name,id LIMIT 2000")->fetchAll();

    $routes=array_map(fn(array $row):array=>routes_payload($db, $row),$routeRows);
    $stringIds=static function(array $row):array  { foreach($row as $key=>&$value) if ($key==='id'||str_ends_with($key,'_id')) $value=$value===null?null:(string)$value; return $row; };
    return ['ambulances'=>array_map($stringIds,$vehicles),'crew'=>array_map($stringIds,$crew),'facilities'=>array_map($stringIds,$facilities),'routes'=>array_map($stringIds,$routes)];
}

function transfer_catalog_patients(PDO $db, $query): array
{
    $q=settings_text($query,'q',150);
    if (mb_strlen($q)<2) return [];
    $escape=static fn(string $s):string=>'%'.str_replace(['!','%','_'],['!!','!%','!_'],$s).'%';
    $s=$db->prepare("SELECT id,full_name,national_id,date_of_birth,sex,estimated_age,age_recorded_on FROM patients WHERE status='active' AND (full_name LIKE ? ESCAPE '!' OR national_id LIKE ? ESCAPE '!') ORDER BY full_name,id LIMIT 20");
    $s->execute([$escape($q),$escape(preg_replace('/[.\s-]/','',$q))]);
    return array_map('transfer_catalog_patient',$s->fetchAll());
}

function transfer_catalog_patient(array $row): array
{
    $age=isset($row['estimated_age'])?(int)$row['estimated_age']:null;
    if ($row['date_of_birth']!==null) $age=(new \DateTimeImmutable($row['date_of_birth']))->diff(new \DateTimeImmutable('today',new \DateTimeZone('America/Montevideo')))->y;
    return ['id'=>(string)$row['id'],'full_name'=>$row['full_name'],'national_id'=>$row['national_id'],'age'=>$age,'age_is_estimate'=>$age!==null && $row['date_of_birth']===null,'age_recorded_on'=>$row['age_recorded_on']??null,'sex'=>$row['sex']];
}

function transfer_catalog_import(PDO $db, array $input): array
{
    if (array_diff(array_keys($input),['facilities','ambulances','crew','routes'])) throw new ApiException(422,'VALIDATION_FAILED',\Hospital\I18n::t("errors.catalogo_no_valido"));
    $db->beginTransaction();
    try {
        foreach ($input['facilities']??[] as $item) {
            $name=settings_text($item['name']??null,'name',150,false); $address=settings_text($item['address']??'','address',255);
            foreach(['latitude'=>90,'longitude'=>180] as $key=>$limit) if (!is_numeric($item[$key]??null)||abs((float)$item[$key])>$limit) throw new ApiException(422,'INVALID_ROUTE',\Hospital\I18n::t("errors.coordenadas_no_validas"));
            $s=$db->prepare('SELECT id FROM facilities WHERE name=? ORDER BY id LIMIT 1 FOR UPDATE'); $s->execute([$name]); $id=$s->fetchColumn();
            if ($id) { $s=$db->prepare('UPDATE facilities SET address=?,latitude=?,longitude=?,version=version+1 WHERE id=?'); $s->execute([$address,$item['latitude'],$item['longitude'],$id]); }
            else { $s=$db->prepare('INSERT INTO facilities (name,address,latitude,longitude) VALUES (?,?,?,?)'); $s->execute([$name,$address,$item['latitude'],$item['longitude']]); }
        }
        foreach($input['ambulances']??[] as $item) {
            $code=strtoupper(settings_text($item['code']??null,'code',20,false)); $plate=strtoupper(preg_replace('/[\s-]/','',settings_text($item['plate']??null,'plate',20,false)));
            if (!preg_match('/^[A-Z0-9-]{1,20}$/',$code)||!preg_match('/^[A-Z0-9]{1,20}$/',$plate)) throw new ApiException(422,'VALIDATION_FAILED',\Hospital\I18n::t("errors.identidad_de_vehiculo_no_valida"));
            $s=$db->prepare('INSERT INTO ambulances (code,plate,model) VALUES (?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)'); $s->execute([$code,$plate,settings_text($item['model']??'','model',100)]);
            $s=$db->prepare('SELECT code,plate FROM ambulances WHERE code=? OR plate=?');$s->execute([$code,$plate]);$rows=$s->fetchAll();
            if(count($rows)!==1||$rows[0]['code']!==$code||$rows[0]['plate']!==$plate) throw new ApiException(409,'CATALOG_CONFLICT',\Hospital\I18n::t("errors.codigo_o_matricula_ya_utilizados"));
        }
        foreach($input['crew']??[] as $item) {
            $ci=preg_replace('/[.\s-]/','',$item['national_id']??''); if(!preg_match('/^[0-9]{6,12}$/',$ci)) throw new ApiException(422,'VALIDATION_FAILED',\Hospital\I18n::t("errors.identidad_de_personal_no_valida"));
            $name=settings_text($item['full_name']??null,'full_name',150,false);
            $s=$db->prepare('INSERT INTO staff (national_id,full_name) VALUES (?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');$s->execute([$ci,$name]); $staff=$db->lastInsertId();
            foreach($item['responsibilities']??[] as $role) { transfer_input_choice($role,['driver','copilot','doctor']);$s=$db->prepare('INSERT IGNORE INTO staff_roles (staff_id,role_code) VALUES (?,?)');$s->execute([$staff,$role]); }
        }
        foreach($input['routes']??[] as $item) {
            $points=transfer_input_geometry($item['geometry']??null); $ends=[];
            foreach(['origin','destination'] as $key) { $s=$db->prepare("SELECT id,name,address,latitude,longitude FROM facilities WHERE name=? AND status='active' ORDER BY id LIMIT 1 FOR UPDATE");$s->execute([$item[$key]??null]);$row=$s->fetch();if(!$row||$row['latitude']===null||$row['longitude']===null) throw new ApiException(422,'INVALID_ROUTE',\Hospital\I18n::t("errors.centro_no_valido"));$ends[]=$row; }
            if ($ends[0]['id']===$ends[1]['id']||transfer_input_distance($ends[0],$points['points'][0])>100||transfer_input_distance($ends[1],$points['points'][count($points['points'])-1])>100) throw new ApiException(422,'INVALID_ROUTE',\Hospital\I18n::t("errors.la_geometria_debe_unir_los_centros_seleccionados"));
            $seconds=$item['duration_seconds']??null; if(!is_int($seconds)||$seconds<30||$seconds>86400) throw new ApiException(422,'INVALID_ROUTE',\Hospital\I18n::t("errors.duracion_no_valida"));
            $code=settings_text($item['code']??null,'code',40,false); $name=settings_text($item['name']??null,'name',150,false);
            $s=$db->prepare('INSERT INTO route_templates (code,name,origin_facility_id,destination_facility_id,geometry,distance_meters,duration_seconds) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),origin_facility_id=VALUES(origin_facility_id),destination_facility_id=VALUES(destination_facility_id),geometry=VALUES(geometry),distance_meters=VALUES(distance_meters),duration_seconds=VALUES(duration_seconds),version=version+1');
            $s->execute([$code,$name,$ends[0]['id'],$ends[1]['id'],json_encode($points['points'],JSON_THROW_ON_ERROR),$points['distance'],$seconds]);
            $s=$db->prepare('UPDATE route_templates SET origin_name=?,origin_address=?,destination_name=?,destination_address=? WHERE code=?');$s->execute([$ends[0]['name'],$ends[0]['address'],$ends[1]['name'],$ends[1]['address'],$code]);
        }
        transfer_audit($db, null,'transfer.catalog_import',null,'catalog');
        $db->commit();
    } catch(\Throwable $e) { if($db->inTransaction())$db->rollBack();throw $e; }
    return transfer_catalog_selection($db);
}

function transfer_draft_get(PDO $db, string $actor):array
{
    transfer_require_dispatch($db, $actor);$s=$db->prepare('SELECT * FROM transfer_drafts WHERE user_id=?');$s->execute([$actor]);$row=$s->fetch();
    $payload=$row?json_decode($row['payload'],true,16,JSON_THROW_ON_ERROR):[];
    return ['version'=>$row?(int)$row['version']:0,'payload'=>$payload?:new \stdClass(),'updated_at'=>$row?users_date($row['updated_at']):null];
}

function transfer_draft_save(PDO $db, array $config, string $actor,array $input,bool $clear=false):array
{
    $payload=$clear?new \stdClass():($input['payload']??null);
    $allowed=['ambulance','driver','companion','startDate','startTime','reason','patientName','patientCedula','patientAge','patientSex','origin','destination','routeTemplate','routeVersion','transferType','priority','notes','specialEquipment','withFamily','interdepartmental','criticalPatient','patientId','createToken'];
    if(!$clear){
        if(!is_array($payload)||array_diff(array_keys($payload),$allowed))throw new ApiException(422,'VALIDATION_FAILED',\Hospital\I18n::t("errors.borrador_no_valido"));
        foreach($payload as $key=>$value){
            if(in_array($key,['specialEquipment','withFamily','interdepartmental','criticalPatient'],true)){if(!is_bool($value))throw new ApiException(422,'VALIDATION_FAILED',\Hospital\I18n::t("errors.opcion_no_valida"));}
            else{if(!is_string($value)||!mb_check_encoding($value,'UTF-8')||mb_strlen($value)>($key==='notes'?1000:255))throw new ApiException(422,'VALIDATION_FAILED',\Hospital\I18n::t("errors.texto_de_borrador_no_valido"));}
        }
    }
    $version=$input['version']??null;if(!is_int($version)||$version<0||$version>4294967295)throw new ApiException(422,'VERSION_REQUIRED',\Hospital\I18n::t("errors.se_requiere_la_revision_del_borrador"));
    transfer_serialized($db, $config, function() use ($actor, $payload, $version, $db) {
        $db->beginTransaction();
        try{
            transfer_actor($db, $actor);$s=$db->prepare('SELECT version FROM transfer_drafts WHERE user_id=? FOR UPDATE');$s->execute([$actor]);$old=$s->fetchColumn();
            if(($old===false?0:(int)$old)!==$version)throw new ApiException(409,'VERSION_CONFLICT',\Hospital\I18n::t("errors.el_borrador_cambio_en_otra_sesion"));
            $s=$db->prepare('INSERT INTO transfer_drafts (user_id,payload,version) VALUES (?,?,1) ON DUPLICATE KEY UPDATE payload=VALUES(payload),version=version+1,updated_at=UTC_TIMESTAMP()');$s->execute([$actor,json_encode($payload,JSON_THROW_ON_ERROR)]);
            transfer_audit($db, $actor,'transfer.draft_saved',null);$db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    });return transfer_draft_get($db, $actor);
}

function transfer_locations_fingerprint(array $resolved): string
{
    $r=$resolved['route'];
    return hash('sha256',json_encode([$resolved['origin'],$resolved['destination'],$r['id'],$r['geometry'],$r['distance_meters'],$r['duration_seconds'],$r['route_snapshot']??null],JSON_THROW_ON_ERROR));
}

function transfer_locations_resolve(PDO $db, array $fields,?array $existing,bool $lock=true,bool $refresh=false): array
{
    $explicit=array_key_exists('route_template_id',$fields);
    $selected=$fields['route_template_id']??null;
    $keepSelected=!$refresh && $existing && $explicit && $selected===($existing['route_template_id']===null?null:(string)$existing['route_template_id']);
    if ($selected!==null && $keepSelected) {
        $ends=[]; foreach (['origin','destination'] as $key) $ends[$key]=['id'=>$existing[$key.'_facility_id'],'name'=>$existing[$key.'_name'],'address'=>$existing[$key.'_address']];
        $route=['id'=>$existing['route_template_id'],'geometry'=>$existing['geometry'],'distance_meters'=>$existing['distance_meters'],'duration_seconds'=>$existing['duration_seconds'],'route_snapshot'=>$existing['route_snapshot']??null];
        return ['origin'=>$ends['origin'],'destination'=>$ends['destination'],'route'=>$route,'sameRoute'=>true];
    }
    if ($selected!==null && !$keepSelected) {
        $s=$db->prepare("SELECT * FROM route_templates WHERE id=? AND status='active' AND availability='active'".($lock?' FOR UPDATE':'')); $s->execute([$selected]); $route=$s->fetch();
        if (!$route) throw new ApiException(422,'ROUTE_UNAVAILABLE',\Hospital\I18n::t("errors.selecciona_una_ruta_activa_o_escribi_el_recorrido_manualmente"));
        if (isset($fields['route_version']) && (int)$route['version']!==$fields['route_version']) throw new ApiException(409,'VERSION_CONFLICT',\Hospital\I18n::t("errors.la_ruta_cambio_volve_a_seleccionarla"));
        $payload=routes_payload($db, $route);
        $route['route_snapshot']=json_encode(array_intersect_key($payload,array_flip(['id','code','name','category','direction','stops','version'])),JSON_THROW_ON_ERROR);
        $ends=[]; foreach (['origin','destination'] as $key) $ends[$key]=['id'=>$route[$key.'_facility_id'],'name'=>$route[$key.'_name'],'address'=>$route[$key.'_address']];
        return ['origin'=>$ends['origin'],'destination'=>$ends['destination'],'route'=>$route,'sameRoute'=>false];
    }
    $ends=[]; $sameRoute=$existing!==null && !$refresh;
    if ($explicit && !$keepSelected) $sameRoute=false;
    foreach (['origin','destination'] as $key) {
        $id=$fields[$key.'_facility_id'];
        $same=$existing && $id===($existing[$key.'_facility_id']===null?null:(string)$existing[$key.'_facility_id']);
        if ($id===null) $same=$same && $fields[$key.'_name']===$existing[$key.'_name'] && $fields[$key.'_address']===$existing[$key.'_address'];
        $sameRoute=$sameRoute && $same;
        if ($same) {
            $ends[$key]=['id'=>$id,'name'=>$existing[$key.'_name'],'address'=>$existing[$key.'_address']];
        } elseif ($id!==null) {
            $s=$db->prepare("SELECT * FROM facilities WHERE id=? AND status='active' AND availability='active'".($lock?' FOR UPDATE':'')); $s->execute([$id]); $center=$s->fetch();
            if (!$center) throw new ApiException(422,'FACILITY_UNAVAILABLE',\Hospital\I18n::t("errors.selecciona_un_centro_activo_o_escribi_una_ubicacion_propia"));
            $ends[$key]=$center;
        } else {
            $ends[$key]=['id'=>null,'name'=>$fields[$key.'_name'],'address'=>$fields[$key.'_address']];
        }
    }
    $route=['id'=>null,'geometry'=>'[]','distance_meters'=>null,'duration_seconds'=>null,'route_snapshot'=>null];
    if ($sameRoute) {
        $route=['id'=>$existing['route_template_id'],'geometry'=>$existing['geometry'],'distance_meters'=>$existing['distance_meters'],'duration_seconds'=>$existing['duration_seconds'],'route_snapshot'=>$existing['route_snapshot']??null];
    } elseif (!$explicit && $fields['origin_facility_id']!==null && $fields['destination_facility_id']!==null) {
        $s=$db->prepare("SELECT * FROM route_templates WHERE origin_facility_id=? AND destination_facility_id=? AND status='active' AND availability='active' ORDER BY duration_seconds,id LIMIT 1");
        $s->execute([$fields['origin_facility_id'],$fields['destination_facility_id']]); $candidate=$s->fetch();
        if ($candidate) {
            $points=json_decode($candidate['geometry'],true,32,JSON_THROW_ON_ERROR);
            $valid=count($points)>=2;
            if (!$valid) $candidate=null;
            if ($valid) $points=transfer_input_geometry($points)['points'];
            foreach (['origin'=>0,'destination'=>count($points)-1] as $key=>$index) {
                $center=$ends[$key];
                // An unchanged endpoint may have used its immutable snapshot above.
                if (!array_key_exists('latitude',$center)) { $s=$db->prepare('SELECT latitude,longitude FROM facilities WHERE id=?'); $s->execute([$center['id']]); $center=$s->fetch(); }
                if (!$valid || $center['latitude']===null || $center['longitude']===null || transfer_input_distance($center,$points[$index])>100) $valid=false;
            }
            if ($valid) $route=$candidate;
        }
    }
    return ['origin'=>$ends['origin'],'destination'=>$ends['destination'],'route'=>$route,'sameRoute'=>(bool)$sameRoute];
}

function transfers_row(PDO $db, string $actor,string $id,bool $lock=false): array
{
    $args=[$id];$scope=transfer_scope($db, $actor,$args);
    $s=$db->prepare('SELECT t.* FROM transfers t WHERE t.id=? AND '.$scope.($lock?' FOR UPDATE':''));$s->execute($args);$row=$s->fetch();
    if(!$row)throw new ApiException(404,'TRANSFER_NOT_FOUND',\Hospital\I18n::t("errors.no_se_encontro_el_traslado"));
    return $row;
}

function transfers_revision(array $row,$version):void
{
    if((int)$row['version']!==transfer_input_version($version))throw new ApiException(409,'VERSION_CONFLICT',\Hospital\I18n::t("errors.el_traslado_cambio_volve_a_abrirlo_antes_de_guardar"));
}

function transfers_transaction(PDO $db, array $config, string $actor,callable $work)
{
    return transfer_serialized($db, $config, function() use ($actor, $work, $db) {
        $db->beginTransaction();
        try{transfer_actor($db, $actor);$result=$work();$db->commit();return $result;}
        catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    });
}

function transfers_catalog(PDO $db, array $f,?array $existing=null,?array $prepared=null,bool $refresh=false):array
{
    // Lock canonical resources in stable ID order. All transfer writes also
    // use a database-wide domain lock, including the simulation worker.
    $s=$db->prepare("SELECT * FROM ambulances WHERE id=? FOR UPDATE");$s->execute([$f['ambulance_id']]);$vehicle=$s->fetch();
    if(!$vehicle||$vehicle['status']!=='active'||$vehicle['operational_status']!=='available')throw new ApiException(422,'VEHICLE_UNAVAILABLE',\Hospital\I18n::t("errors.selecciona_una_ambulancia_operativa"));
    $ids=array_values(array_filter([$f['driver_id'],$f['companion_id']]));sort($ids,SORT_NUMERIC);
    $s=$db->prepare('SELECT * FROM staff WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY id FOR UPDATE');$s->execute($ids);$staff=[];
    foreach($s->fetchAll() as $member)$staff[(string)$member['id']]=$member;
    foreach(['driver_id'=>['driver'],'companion_id'=>['copilot','doctor']] as $key=>$roles){
        if($f[$key]===null)continue;$member=$staff[$f[$key]]??null;
        $s=$db->prepare('SELECT role_code FROM staff_roles WHERE staff_id=? FOR UPDATE');$s->execute([$f[$key]]);$responsibilities=$s->fetchAll(\PDO::FETCH_COLUMN);
        $s=$db->prepare('SELECT status,availability FROM doctor_profiles WHERE staff_id=?');$s->execute([$f[$key]]);$doctor=$s->fetch();
        if($doctor && ($doctor['status']!=='active'||$doctor['availability']==='inactive'))$responsibilities=array_values(array_diff($responsibilities,['doctor']));
        $s=$db->prepare('SELECT status,availability,responsibility FROM personnel_profiles WHERE staff_id=?');$s->execute([$f[$key]]);$personnel=$s->fetch();
        if($personnel)foreach(['driver','copilot'] as $role)if($personnel['status']!=='active'||$personnel['availability']!=='active'||$personnel['responsibility']!==$role)$responsibilities=array_values(array_diff($responsibilities,[$role]));
        if(!$member||$member['status']!=='active'||!array_intersect($roles,$responsibilities))throw new ApiException(422,'CREW_UNAVAILABLE',\Hospital\I18n::t("errors.selecciona_personal_activo_con_la_funcion_requerida"));
        $staff[$f[$key]]['responsibility']=$key==='driver_id'?'driver':(in_array('doctor',$responsibilities,true)?'doctor':'copilot');
    }
    if($f['patient_id']!==null){
        $keep=$existing&&$existing['patient_id']!==null&&(string)$existing['patient_id']===$f['patient_id'];
        $s=$db->prepare('SELECT id FROM patients WHERE id=?'.($keep?'':" AND status='active'").' FOR UPDATE');$s->execute([$f['patient_id']]);
        if(!$s->fetchColumn())throw new ApiException(422,'PATIENT_UNAVAILABLE',\Hospital\I18n::t("errors.selecciona_un_paciente_activo_del_hospital"));
    }
    $resolved=transfer_locations_resolve($db, $f,$existing,true,$refresh);
    if ($prepared!==null) {
        if (!hash_equals($prepared['fingerprint'],transfer_locations_fingerprint($resolved))) throw new ApiException(409,'VERSION_CONFLICT',\Hospital\I18n::t("errors.la_ubicacion_o_la_ruta_cambio_durante_la_consulta_reintenta"));
        $resolved['route']=$prepared['route'];
    }
    extract($resolved);
    return compact('vehicle','staff','origin','destination','route','sameRoute');
}

function transfers_prepare_locations(PDO $db, array $config, array $f,?array $existing=null,bool $refresh=false): array
{
    $resolved=transfer_locations_resolve($db, $f,$existing,false,$refresh);
    $fingerprint=transfer_locations_fingerprint($resolved);$route=$resolved['route'];
    if ($route['geometry']==='[]' && (!$resolved['sameRoute'] || $refresh)) {
        $snapshot=$route['route_snapshot']??null;$stops=$snapshot===null?[]:(json_decode($snapshot,true,32,JSON_THROW_ON_ERROR)['stops']??[]);
        $mapped=route_mapping_path($db, $config, array_merge([$resolved['origin']],$stops,[$resolved['destination']]));
        if ($mapped) {
            $route['geometry']=$mapped['geometry'];
            if ($route['id']===null) { $route['distance_meters']=$mapped['distance_meters'];$route['duration_seconds']=$mapped['duration_seconds']; }
        } elseif ($refresh) throw new ApiException(503,'MAP_UNAVAILABLE',\Hospital\I18n::t("errors.no_se_pudo_resolver_el_mapa_se_conservo_el_traslado"));
    }
    return ['fingerprint'=>$fingerprint,'route'=>$route];
}

function transfers_reserve(PDO $db, array $config, array $f,array $catalog,string $id,string $start,bool $starting=false):string
{
    $end=gmdate('Y-m-d H:i:s',strtotime($start.' UTC')+(int)($catalog['route']['duration_seconds']??$config['unmapped_duration_seconds']??3600)+900);
    $resources=[['ambulance',$f['ambulance_id']],['staff',$f['driver_id']]];
    if($f['companion_id']!==null)$resources[]=['staff',$f['companion_id']];
    foreach($resources as [$kind,$resource]){
        $s=$db->prepare("SELECT r.transfer_id FROM resource_reservations r JOIN transfers t ON t.id=r.transfer_id WHERE r.resource_type=? AND r.resource_id=? AND r.transfer_id<>? AND ((r.starts_at<? AND r.ends_at>?) OR (t.status IN ('in_progress','arrived') AND r.ends_at<=UTC_TIMESTAMP())".($starting?" OR t.status IN ('in_progress','arrived')":'').') LIMIT 1 FOR UPDATE');
        $s->execute([$kind,$resource,$id,$end,$start]);
        if($s->fetchColumn())throw new ApiException(409,'ASSIGNMENT_CONFLICT',\Hospital\I18n::t("errors.la_ambulancia_o_el_personal_ya_tienen_otro_traslado_en_ese_intervalo"));
    }
    $s=$db->prepare('DELETE FROM resource_reservations WHERE transfer_id=?');$s->execute([$id]);
    foreach($resources as [$kind,$resource]){$s=$db->prepare('INSERT INTO resource_reservations (transfer_id,resource_type,resource_id,starts_at,ends_at) VALUES (?,?,?,?,?)');$s->execute([$id,$kind,$resource,$start,$end]);}
    return $end;
}

function transfers_crew(PDO $db, string $id,array $f,array $catalog):void
{
    $s=$db->prepare('DELETE FROM transfer_crew WHERE transfer_id=?');$s->execute([$id]);
    foreach(['driver'=>'driver_id','companion'=>'companion_id'] as $duty=>$key){if($f[$key]===null)continue;$s=$db->prepare('INSERT INTO transfer_crew (transfer_id,staff_id,duty,name_snapshot,responsibility) VALUES (?,?,?,?,?)');$s->execute([$id,$f[$key],$duty,$catalog['staff'][$f[$key]]['full_name'],$catalog['staff'][$f[$key]]['responsibility']]);}
}

function transfers_event(PDO $db, string $id,?string $actor,string $type,?string $from=null,?string $to=null):void
{
    $s=$db->prepare('INSERT INTO transfer_events (transfer_id,actor_user_id,event_type,from_status,to_status) VALUES (?,?,?,?,?)');$s->execute([$id,$actor,$type,$from,$to]);
}

function transfers_create(PDO $db, array $config, string $actor,array $input):array
{
    transfer_require_dispatch($db, $actor);$f=transfer_input_fields($input);$token=$input['create_token']??null;
    if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/',$token))throw new ApiException(422,'CREATE_TOKEN_REQUIRED',\Hospital\I18n::t("errors.se_requiere_la_referencia_de_creacion"));
    $fingerprint=hash('sha256',json_encode($f,JSON_THROW_ON_ERROR));
    $s=$db->prepare('SELECT id,create_fingerprint FROM transfers WHERE created_by=? AND create_key=?');$s->execute([$actor,hash('sha256',$token)]);$retry=$s->fetch();
    if($retry){if(!hash_equals($retry['create_fingerprint'],$fingerprint))throw new ApiException(409,'CREATE_TOKEN_REUSED',\Hospital\I18n::t("errors.la_referencia_ya_fue_utilizada"));return transfers_get($db, $actor,(string)$retry['id']);}
    $prepared=transfers_prepare_locations($db, $config, $f);
    $id=transfers_transaction($db, $config, $actor,function() use ($actor, $input, $f, $token, $fingerprint, $prepared, $db, $config) {
        $s=$db->prepare('SELECT id,create_fingerprint FROM transfers WHERE created_by=? AND create_key=?');$s->execute([$actor,hash('sha256',$token)]);$existing=$s->fetch();
        if($existing){if(!hash_equals($existing['create_fingerprint'],$fingerprint))throw new ApiException(409,'CREATE_TOKEN_REUSED',\Hospital\I18n::t("errors.la_referencia_ya_fue_utilizada_con_otros_datos"));return(string)$existing['id'];}
        $when=strtotime($f['scheduled_at'].' UTC');
        if($when<time()-86400||$when>time()+366*86400)throw new ApiException(422,'INVALID_SCHEDULE',\Hospital\I18n::t("errors.la_fecha_debe_estar_entre_ayer_y_el_proximo_ano"));
        $c=transfers_catalog($db, $f,null,$prepared);$route=$c['route'];$v=$c['vehicle'];
        $row=['created_by'=>$actor,'ambulance_id'=>$f['ambulance_id'],'patient_id'=>$f['patient_id'],'route_template_id'=>$route['id'],'origin_facility_id'=>$c['origin']['id'],'destination_facility_id'=>$c['destination']['id'],'ambulance_code'=>$v['code'],'ambulance_plate'=>$v['plate'],'origin_name'=>$c['origin']['name'],'destination_name'=>$c['destination']['name'],'origin_address'=>$c['origin']['address'],'destination_address'=>$c['destination']['address'],'geometry'=>$route['geometry'],'route_snapshot'=>$route['route_snapshot']??null,'distance_meters'=>$route['distance_meters'],'duration_seconds'=>$route['duration_seconds'],'reserved_until'=>$f['scheduled_at'],'create_key'=>hash('sha256',$token),'create_fingerprint'=>$fingerprint];
        foreach($f as $key=>$value)if(!in_array($key,['driver_id','companion_id','route_template_id','route_version','origin_facility_id','destination_facility_id','origin_name','origin_address','destination_name','destination_address'],true))$row[$key]=$value;
        $s=$db->prepare('INSERT INTO transfers ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')');$s->execute(array_values($row));$id=$db->lastInsertId();
        $end=transfers_reserve($db, $config, $f,$c,$id,$f['scheduled_at']);$s=$db->prepare('UPDATE transfers SET reserved_until=? WHERE id=?');$s->execute([$end,$id]);
        transfers_crew($db, $id,$f,$c);transfers_event($db, $id,$actor,'created',null,'scheduled');transfer_audit($db, $actor,'transfer.created',$id);
        tracking_sample($db, $id,json_decode($route['geometry'],true),0,null,1);
        if(isset($input['draft_version'])){$version=transfer_input_version($input['draft_version']);$s=$db->prepare("UPDATE transfer_drafts SET payload='{}',version=version+1 WHERE user_id=? AND version=?");$s->execute([$actor,$version]);}
        return $id;
    });return transfers_get($db, $actor,$id);
}

function transfers_update(PDO $db, array $config, string $actor,string $id,array $input):array
{
    transfer_require_dispatch($db, $actor);$f=transfer_input_fields($input);
    $before=transfers_row($db, $actor,$id);transfers_revision($before,$input['version']??null);
    if($before['status']!=='scheduled')throw new ApiException(409,'TRANSFER_LOCKED',\Hospital\I18n::t("errors.solo_los_traslados_programados_permiten_cambiar_sus_datos"));
    $refresh=$input['refresh_route']??false;if(!is_bool($refresh))throw new ApiException(422,'VALIDATION_FAILED',\Hospital\I18n::t("errors.opcion_no_valida"));
    $prepared=transfers_prepare_locations($db, $config, $f,$before,$refresh);
    transfers_transaction($db, $config, $actor,function() use ($actor, $id, $input, $f, $prepared, $refresh, $db, $config) {
        $row=transfers_row($db, $actor,$id,true);transfers_revision($row,$input['version']??null);
        if($row['status']!=='scheduled')throw new ApiException(409,'TRANSFER_LOCKED',\Hospital\I18n::t("errors.solo_los_traslados_programados_permiten_cambiar_sus_datos"));
        $when=strtotime($f['scheduled_at'].' UTC');
        if($f['scheduled_at']!==$row['scheduled_at']&&($when<time()-86400||$when>time()+366*86400))throw new ApiException(422,'INVALID_SCHEDULE',\Hospital\I18n::t("errors.la_fecha_debe_estar_entre_ayer_y_el_proximo_ano"));
        $c=transfers_catalog($db, $f,$row,$prepared,$refresh);$end=transfers_reserve($db, $config, $f,$c,$id,$f['scheduled_at']);
        $changes=$f;unset($changes['driver_id'],$changes['companion_id'],$changes['origin_name'],$changes['origin_address'],$changes['destination_name'],$changes['destination_address'],$changes['route_template_id'],$changes['route_version']);$changes['origin_facility_id']=$c['origin']['id'];$changes['destination_facility_id']=$c['destination']['id'];$changes['reserved_until']=$end;
        $changes['ambulance_code']=$c['vehicle']['code'];$changes['ambulance_plate']=$c['vehicle']['plate'];
        if(!$c['sameRoute'])foreach(['route_template_id'=>$c['route']['id'],'origin_name'=>$c['origin']['name'],'destination_name'=>$c['destination']['name'],'origin_address'=>$c['origin']['address'],'destination_address'=>$c['destination']['address'],'geometry'=>$c['route']['geometry'],'route_snapshot'=>$c['route']['route_snapshot']??null,'distance_meters'=>$c['route']['distance_meters'],'duration_seconds'=>$c['route']['duration_seconds']] as $key=>$value)$changes[$key]=$value;
        $sets=array_map(static fn(string $key):string=>$key.'=?',array_keys($changes));
        $s=$db->prepare('UPDATE transfers SET '.implode(',',$sets).',version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?');$s->execute(array_merge(array_values($changes),[$id]));
        transfers_crew($db, $id,$f,$c);transfers_event($db, $id,$actor,'updated');transfer_audit($db, $actor,'transfer.updated',$id);
        if(!$c['sameRoute'])tracking_sample($db, $id,json_decode($c['route']['geometry'],true),0,null,1);
    });return transfers_get($db, $actor,$id);
}

function transfers_change_status(PDO $db, array $config, string $actor,string $id,$version,string $status):array
{
    transfer_input_choice($status,['in_progress','arrived','completed','cancelled']);
    transfers_transaction($db, $config, $actor,function() use ($actor, $id, $version, $status, $db, $config) {
        $row=transfers_row($db, $actor,$id,true);transfers_revision($row,$version);
        $allowed=['scheduled'=>['in_progress','cancelled'],'in_progress'=>['arrived','cancelled'],'arrived'=>['completed','cancelled'],'completed'=>[],'cancelled'=>[]];
        if(!in_array($status,$allowed[$row['status']],true))throw new ApiException(409,'INVALID_TRANSITION',\Hospital\I18n::t("errors.el_estado_no_permite_esa_transicion"));
        $column=['in_progress'=>'started_at','arrived'=>'arrived_at','completed'=>'finished_at','cancelled'=>'finished_at'][$status];
        if($status==='in_progress'){
            $s=$db->prepare('SELECT staff_id,duty FROM transfer_crew WHERE transfer_id=?');$s->execute([$id]);$crew=[];foreach($s->fetchAll() as $member)$crew[$member['duty']]=(string)$member['staff_id'];
            $f=['ambulance_id'=>(string)$row['ambulance_id'],'patient_id'=>$row['patient_id']===null?null:(string)$row['patient_id'],'driver_id'=>$crew['driver'],'companion_id'=>$crew['companion']??null,'origin_facility_id'=>$row['origin_facility_id']===null?null:(string)$row['origin_facility_id'],'destination_facility_id'=>$row['destination_facility_id']===null?null:(string)$row['destination_facility_id'],'origin_name'=>$row['origin_name'],'origin_address'=>$row['origin_address'],'destination_name'=>$row['destination_name'],'destination_address'=>$row['destination_address']];
            // Resources must still be eligible; endpoint/route snapshots belong to this trace.
            $snapshot=$row;$snapshot['patient_id']=null;
            $c=transfers_catalog($db, $f,$snapshot);
            $c['route']=['duration_seconds'=>$row['duration_seconds']];
            $end=transfers_reserve($db, $config, $f,$c,$id,gmdate('Y-m-d H:i:s'),true);$s=$db->prepare('UPDATE transfers SET reserved_until=? WHERE id=?');$s->execute([$end,$id]);
            tracking_sample($db, $id,json_decode($row['geometry'],true),0,null,1);
        }
        if($status==='arrived')tracking_sample($db, $id,json_decode($row['geometry'],true),1,null,1);
        $s=$db->prepare('UPDATE transfers SET status=?,'.$column.'=UTC_TIMESTAMP(),version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?');$s->execute([$status,$id]);
        if(in_array($status,['completed','cancelled'],true)){$s=$db->prepare('DELETE FROM resource_reservations WHERE transfer_id=?');$s->execute([$id]);}
        transfers_event($db, $id,$actor,'status_changed',$row['status'],$status);transfer_audit($db, $actor,'transfer.'.$status,$id);
    });return transfers_get($db, $actor,$id);
}

function transfers_payload(PDO $db, array $row,bool $detail=false):array
{
    $s=$db->prepare('SELECT staff_id,duty,name_snapshot,responsibility FROM transfer_crew WHERE transfer_id=? ORDER BY duty');$s->execute([$row['id']]);$crew=[];
    foreach($s->fetchAll() as $member)$crew[$member['duty']]=['id'=>(string)$member['staff_id'],'name'=>$member['name_snapshot'],'responsibility'=>$member['responsibility']];
    $elapsed=$row['started_at']===null?0:max(0,strtotime(($row['finished_at']??$row['arrived_at']??gmdate('Y-m-d H:i:s')).' UTC')-strtotime($row['started_at'].' UTC'));
    $delayed=$row['status']==='in_progress'&&(($row['duration_seconds']!==null && $elapsed>(int)$row['duration_seconds'])||strtotime($row['started_at'].' UTC')>strtotime($row['scheduled_at'].' UTC')+300);
    $data=['id'=>(string)$row['id'],'code'=>'TR-'.str_pad((string)$row['id'],6,'0',STR_PAD_LEFT),'version'=>(int)$row['version'],'status'=>$row['status'],'is_delayed'=>$delayed,'cargo_type'=>$row['cargo_type'],'priority'=>$row['priority'],'reason'=>$row['reason'],'ambulance'=>['id'=>(string)$row['ambulance_id'],'code'=>$row['ambulance_code'],'plate'=>$row['ambulance_plate']],'crew'=>$crew,'origin'=>['id'=>$row['origin_facility_id']===null?null:(string)$row['origin_facility_id'],'name'=>$row['origin_name'],'address'=>$row['origin_address']],'destination'=>['id'=>$row['destination_facility_id']===null?null:(string)$row['destination_facility_id'],'name'=>$row['destination_name'],'address'=>$row['destination_address']],'scheduled_at'=>users_date($row['scheduled_at']),'started_at'=>users_date($row['started_at']),'arrived_at'=>users_date($row['arrived_at']),'finished_at'=>users_date($row['finished_at']),'created_at'=>users_date($row['created_at']),'updated_at'=>users_date($row['updated_at']),'duration_seconds'=>$row['duration_seconds']===null?null:(int)$row['duration_seconds'],'distance_meters'=>$row['distance_meters']===null?null:(int)$row['distance_meters'],'elapsed_seconds'=>$elapsed,'eta'=>$row['duration_seconds']===null?null:users_date(gmdate('Y-m-d H:i:s',strtotime(($row['started_at']??$row['scheduled_at']).' UTC')+(int)$row['duration_seconds']))];
    if($detail){
        $data['notes']=$row['notes'];$data['route_template_id']=$row['route_template_id']===null?null:(string)$row['route_template_id'];$data['geometry']=json_decode($row['geometry'],true,32,JSON_THROW_ON_ERROR);$data['route_snapshot']=$row['route_snapshot']===null?null:json_decode($row['route_snapshot'],true,32,JSON_THROW_ON_ERROR);
        foreach(['special_equipment','with_family','interdepartmental','critical_patient'] as $key)$data[$key]=(bool)$row[$key];
        $data['patient']=null;
        if($row['patient_id']!==null){$s=$db->prepare('SELECT * FROM patients WHERE id=?');$s->execute([$row['patient_id']]);$patient=$s->fetch();$data['patient']=transfer_catalog_patient($patient);}
        $s=$db->prepare('SELECT id,event_type,from_status,to_status,created_at FROM transfer_events WHERE transfer_id=? ORDER BY id DESC LIMIT 100');$s->execute([$row['id']]);$events=array_reverse($s->fetchAll());
        foreach($events as &$event){$event['id']=(string)$event['id'];$event['created_at']=users_date($event['created_at']);}unset($event);$data['events']=$events;
        $data['position']=tracking_latest($db, (string)$row['id']);
    }return $data;
}

function transfers_get(PDO $db, string $actor,string $id):array
{
    $data=transfers_payload($db, transfers_row($db, $actor,$id),true);$data['can_manage']=transfer_dispatch($db, $actor);return $data;
}

function transfers_listing(PDO $db, string $actor,array $query):array
{
    if(array_diff(array_keys($query),['page','page_size','state','type','sort','q']))throw new ApiException(422,'UNEXPECTED_FIELDS',\Hospital\I18n::t("errors.filtro_no_permitido"));
    $page=$query['page']??'1';$size=$query['page_size']??'20';
    if(!is_scalar($page)||!preg_match('/^[1-9][0-9]{0,5}$/',(string)$page)||!is_scalar($size)||!preg_match('/^[1-9][0-9]{0,2}$/',(string)$size)||(int)$size>100)throw new ApiException(422,'VALIDATION_FAILED',\Hospital\I18n::t("errors.paginacion_no_valida"));
    $args=[];$scope=transfer_scope($db, $actor,$args);
    $s=$db->prepare("SELECT COUNT(*) AS all_count,COALESCE(SUM(t.status IN ('scheduled','in_progress','arrived')),0) AS active_count,COALESCE(SUM(t.status='completed'),0) AS completed_count FROM transfers t WHERE ".$scope);$s->execute($args);$counts=$s->fetch();$counts=array_map('intval',$counts);
    $where=[$scope];$state=transfer_input_choice($query['state']??'active',['active','completed','all']);
    if($state==='active')$where[]="t.status IN ('scheduled','in_progress','arrived')";elseif($state==='completed')$where[]="t.status='completed'";
    $type=transfer_input_choice($query['type']??'all',['all','patient','biological','equipment']);if($type!=='all'){$where[]='t.cargo_type=?';$args[]=$type;}
    $q=settings_text($query['q']??'','q',150);
    if($q!==''){$pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';$where[]="(t.ambulance_code LIKE ? ESCAPE '!' OR t.origin_name LIKE ? ESCAPE '!' OR t.destination_name LIKE ? ESCAPE '!' OR CONCAT('TR-',LPAD(t.id,6,'0')) LIKE ? ESCAPE '!' OR EXISTS(SELECT 1 FROM transfer_crew c WHERE c.transfer_id=t.id AND c.name_snapshot LIKE ? ESCAPE '!'))";array_push($args,$pattern,$pattern,$pattern,$pattern,$pattern);}
    $sort=transfer_input_choice($query['sort']??'recent',['recent','oldest']);$order=$sort==='recent'?'DESC':'ASC';$condition=implode(' AND ',$where);
    $s=$db->prepare('SELECT COUNT(*) FROM transfers t WHERE '.$condition);$s->execute($args);$total=(int)$s->fetchColumn();
    $offset=((int)$page-1)*(int)$size;
    $s=$db->prepare('SELECT t.* FROM transfers t WHERE '.$condition.' ORDER BY t.scheduled_at '.$order.',t.id '.$order.' LIMIT '.(int)$size.' OFFSET '.$offset);$s->execute($args);
    return ['data'=>array_map(fn(array $row):array=>transfers_payload($db, $row),$s->fetchAll()),'meta'=>['page'=>(int)$page,'page_size'=>(int)$size,'total'=>$total,'counts'=>$counts,'can_manage'=>transfer_dispatch($db, $actor)]];
}
