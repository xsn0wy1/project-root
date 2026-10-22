<?php
declare(strict_types=1);

use Hospital\Http\ApiException;
use Hospital\Http\Request;

// Patient field validation.
const PATIENT_INPUT_FIELDS = [
    'full_name', 'national_id', 'date_of_birth', 'sex', 'age', 'phone', 'blood_group', 'allergies',
    'emergency_name', 'emergency_phone', 'emergency_relationship', 'care_status', 'room', 'department',
    'doctor_staff_id', 'doctor_specialty', 'admission_date', 'diagnosis', 'clinical_state'
];

function patient_input_today() : string
{
    return (new \DateTimeImmutable('now', new \DateTimeZone('America/Montevideo')))->format('Y-m-d');
}

function patient_input_identity($value) : string
{
    if (!is_string($value))
        patient_input_invalid('national_id');
    $value = preg_replace('/[.\s-]/u', '', $value);
    if (!preg_match('/^[0-9]{6,12}$/', $value))
        patient_input_invalid('national_id');
    return $value;
}

function patient_input_invalid(string $field) : void
{
    throw new ApiException(
        422,
        'VALIDATION_FAILED',
        \Hospital\I18n::t("errors.revisa_los_datos_del_paciente"),
        [$field => \Hospital\I18n::t("errors.valor_no_valido")]
    );
}

function patient_input_date($value, string $field) : ?string
{
    if ($value === null || $value === '')
        return null;
    $date = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
    if (
        !$date || $date->format('Y-m-d') !== $value
        || $value < '1900-01-01' || $value > patient_input_today()
    )
        patient_input_invalid($field);
    return $value;
}

function patient_input_phone($value, string $field) : string
{
    $value = settings_text($value, $field, 25);
    if (
        $value !== '' && (
            !preg_match('/^\+?[0-9 ()-]+$/', $value)
            || strlen(preg_replace('/\D/', '', $value)) < 6
            || strlen(preg_replace('/\D/', '', $value)) > 20
        )
    )
        patient_input_invalid($field);
    return $value;
}

function patient_input_version($value) : int
{
    if (
        (!is_int($value) && !is_string($value))
        || !preg_match('/^[1-9][0-9]{0,9}$/', (string) $value)
    )
        throw new ApiException(
            422, 'VERSION_REQUIRED', \Hospital\I18n::t("errors.se_requiere_la_revision_actual")
        );
    return (int) $value;
}

function patient_input_fields(array $input) : array
{
    foreach (PATIENT_INPUT_FIELDS as $key)
        if (!array_key_exists($key, $input))
            throw new ApiException(
                422, 'MISSING_FIELDS', \Hospital\I18n::t("errors.envia_todos_los_campos_del_formulario")
            );
    $f = [
        'full_name' => settings_text($input['full_name'], 'full_name', 150, false),
        'national_id' => patient_input_identity($input['national_id']),
        'date_of_birth' => patient_input_date($input['date_of_birth'], 'date_of_birth')
    ];
    $f['sex'] = $input['sex'] === '' ? null : $input['sex'];
    if (!in_array($f['sex'], [null, 'female', 'male', 'other', 'unspecified'], true))
        patient_input_invalid('sex');
    $age = $input['age'];
    if (
        $age !== null && $age !== '' && (
            (!is_int($age) && !is_string($age))
            || !preg_match('/^(?:0|[1-9][0-9]{0,2})$/', (string) $age) || (int) $age > 120
        )
    )
        patient_input_invalid('age');
    $f['age'] = $age === null || $age === '' ? null : (int) $age;
    if ($f['date_of_birth'] !== null) {
        if (
            (new \DateTimeImmutable($f['date_of_birth']))
                ->diff(new \DateTimeImmutable(patient_input_today()))->y > 120
        )
            patient_input_invalid('date_of_birth');
        $f['age'] = null; // Never invent a birth date from an observed age.
    }
    $f['phone'] = patient_input_phone($input['phone'], 'phone');
    $group = is_string($input['blood_group']) ? strtoupper(trim($input['blood_group'])) : null;
    if ($group === null || !in_array($group, ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true))
        patient_input_invalid('blood_group');
    $f['blood_group'] = $group === '' ? null : $group;
    foreach ([
        'allergies' => 100, 'emergency_name' => 100, 'emergency_relationship' => 50, 'room' => 80,
        'department' => 80, 'doctor_specialty' => 80, 'clinical_state' => 100
    ] as $key => $max)
        $f[$key] = settings_text($input[$key], $key, $max);
    $f['emergency_phone'] = patient_input_phone($input['emergency_phone'], 'emergency_phone');
    $f['care_status'] = $input['care_status'] === '' ? null : $input['care_status'];
    if (!in_array($f['care_status'], [null, 'inpatient', 'outpatient', 'emergency', 'discharged'], true))
        patient_input_invalid('care_status');
    $f['doctor_staff_id'] = $input['doctor_staff_id'] === '' || $input['doctor_staff_id'] === null
        ? null : Request::id($input['doctor_staff_id']);
    $f['admission_date'] = patient_input_date($input['admission_date'], 'admission_date');
    $diagnosis = $input['diagnosis'];
    if (!is_string($diagnosis) || !mb_check_encoding($diagnosis, 'UTF-8'))
        patient_input_invalid('diagnosis');
    $diagnosis = trim(str_replace(["\r\n", "\r"], "\n", $diagnosis));
    if (mb_strlen($diagnosis) > 1000 || preg_match('/\p{C}/u', str_replace(["\n", "\t"], '', $diagnosis)))
        patient_input_invalid('diagnosis');
    $f['diagnosis'] = $diagnosis;
    if ($f['care_status'] === null) {
        foreach ([
            'room', 'department', 'doctor_staff_id', 'doctor_specialty', 'admission_date', 'diagnosis', 'clinical_state'
        ] as $key)
            if ($f[$key] !== '' && $f[$key] !== null)
                patient_input_invalid('care_status');
    } elseif ($f['room'] === '' || $f['department'] === '')
        patient_input_invalid('room');
    return $f;
}

// Patient records and encounters.
function patients_access(PDO $db, string $actor) : void
{
    if (!in_array('records_manager', users_roles($db, $actor), true))
        throw new ApiException(
            403, 'FORBIDDEN', \Hospital\I18n::t("patients.se_requiere_permiso_de_gestion_de_registros")
        );
}

function patients_actor(PDO $db, ?string $actor) : void
{
    if ($actor === null)
        return; // Trusted local CLI only; HTTP always supplies an ID.
    $u = users_by_id($db, $actor, true);
    if (
        !$u || $u['status'] !== 'active' || $u['staff_status'] !== 'active'
        || (int) $u['auth_version'] !== (int) ($_SESSION['auth_version'] ?? 0)
    )
        throw new ApiException(401, 'SESSION_EXPIRED', \Hospital\I18n::t("errors.la_sesion_ya_no_tiene_acceso"));
    patients_access($db, $actor);
}

function patients_audit(PDO $db, array $config, ?string $actor, string $action, string $id) : void
{
    transfer_audit($db, $actor, $action, $id, 'patient');
}

function patients_transaction(PDO $db, array $config, ?string $actor, callable $work)
{
    return transfer_serialized($db, $config, function() use ($actor, $work, $db) {
        $db->beginTransaction();
        try {
            patients_actor($db, $actor);
            $result = $work();
            $db->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($db->inTransaction())
                $db->rollBack();
            if ($error instanceof \PDOException && ($error->errorInfo[1] ?? 0) === 1062)
                throw new ApiException(
                    409, 'PATIENT_CONFLICT', \Hospital\I18n::t("errors.ya_existe_un_registro_con_ese_documento")
                );
            throw $error;
        }
    });
}

function patients_lock(PDO $db, string $id, ?int $version = null) : array
{
    $s = $db->prepare('SELECT * FROM patients WHERE id=? FOR UPDATE');
    $s->execute([$id]);
    $p = $s->fetch();
    if (!$p)
        throw new ApiException(404, 'PATIENT_NOT_FOUND', \Hospital\I18n::t("errors.no_se_encontro_el_paciente"));
    if ($version !== null && (int) $p['version'] !== $version)
        throw new ApiException(
            409, 'VERSION_CONFLICT',
            \Hospital\I18n::t("errors.el_paciente_cambio_volve_a_abrirlo_antes_de_guardar")
        );
    return $p;
}

function patients_joins() : string
{
    return ' FROM patients p LEFT JOIN patient_contacts c ON c.patient_id=p.id LEFT JOIN patient_encounters e ON e.id=(SELECT MAX(ep.id) FROM patient_encounters ep WHERE ep.patient_id=p.id) LEFT JOIN departments dep ON dep.id=e.department_id LEFT JOIN staff doctor ON doctor.id=e.doctor_staff_id LEFT JOIN doctor_profiles dp ON dp.staff_id=doctor.id ';
}

function patients_select() : string
{
    return "SELECT p.*,c.full_name AS emergency_name,c.phone AS emergency_phone,c.relationship AS emergency_relationship,e.id AS encounter_id,e.care_status,e.room,e.department_id,dep.name AS department,e.doctor_staff_id,doctor.full_name AS doctor_name,CASE WHEN doctor.status='active' AND (dp.staff_id IS NULL OR (dp.status='active' AND dp.availability<>'inactive')) THEN 'active' ELSE 'inactive' END AS doctor_status,e.doctor_specialty,e.admission_date,e.discharged_at,e.diagnosis,e.clinical_state";
}

function patients_serialize(array $p) : array
{
    $age = $p['estimated_age'] === null ? null : (int) $p['estimated_age'];
    if ($p['date_of_birth'] !== null)
        $age = (new \DateTimeImmutable($p['date_of_birth']))->diff(new \DateTimeImmutable(patient_input_today()))->y;
    $result = [
        'id' => (string) $p['id'], 'version' => (int) $p['version'], 'full_name' => $p['full_name'],
        'national_id' => $p['national_id'], 'status' => $p['status'],
        'date_of_birth' => $p['date_of_birth'], 'sex' => $p['sex'], 'age' => $age,
        'age_is_estimate' => $age !== null && $p['date_of_birth'] === null,
        'age_recorded_on' => $p['age_recorded_on'],
        'phone' => $p['phone'], 'blood_group' => $p['blood_group'], 'allergies' => $p['allergies'],
        'emergency_contact' => [
            'full_name' => $p['emergency_name'] ?? '', 'phone' => $p['emergency_phone'] ?? '',
            'relationship' => $p['emergency_relationship'] ?? ''
        ],
        'encounter' => null, 'created_at' => users_date($p['created_at']), 'updated_at' => users_date($p['updated_at'])
    ];
    if ($p['encounter_id'] !== null)
        $result['encounter'] = [
            'id' => (string) $p['encounter_id'], 'care_status' => $p['care_status'], 'room' => $p['room'],
            'department_id' => $p['department_id'] === null ? null : (string) $p['department_id'],
            'department' => $p['department'] ?? '',
            'doctor_staff_id' => $p['doctor_staff_id'] === null ? null : (string) $p['doctor_staff_id'],
            'doctor_name' => $p['doctor_name'] ?? '', 'doctor_active' => $p['doctor_status'] === 'active',
            'doctor_specialty' => $p['doctor_specialty'],
            'admission_date' => $p['admission_date'],
            'discharged_at' => $p['discharged_at'] === null ? null : users_date($p['discharged_at']),
            'diagnosis' => $p['diagnosis'], 'clinical_state' => $p['clinical_state']
        ];
    return $result;
}

function patients_get(PDO $db, array $config, string $actor, string $id) : array
{
    patients_access($db, $actor);
    $s = $db->prepare(patients_select() . patients_joins() . ' WHERE p.id=?');
    $s->execute([$id]);
    $p = $s->fetch();
    if (!$p)
        throw new ApiException(404, 'PATIENT_NOT_FOUND', \Hospital\I18n::t("errors.no_se_encontro_el_paciente"));
    patients_audit($db, $config, $actor, 'patient.viewed', $id);
    return patients_serialize($p);
}

function patients_listing(PDO $db, array $config, string $actor, array $input) : array
{
    patients_access($db, $actor);
    $page = patient_input_version($input['page'] ?? 1);
    $size = patient_input_version($input['page_size'] ?? 5);
    if ($size > 50 || $page > 1000000)
        patient_input_invalid('page');
    $state = $input['state'] ?? 'active';
    if (!in_array($state, ['active', 'archived'], true))
        patient_input_invalid('state');
    $care = $input['care_status'] ?? '';
    if (!in_array($care, ['', 'inpatient', 'outpatient', 'emergency', 'discharged'], true))
        patient_input_invalid('care_status');
    $q = settings_text($input['q'] ?? '', 'q', 150);
    $where = ' WHERE p.status=?';
    $args = [$state];
    if ($care !== '') {
        $where .= ' AND e.care_status=?';
        $args[] = $care;
    }
    if ($q !== '') {
        $escape = static fn(string $s): string => '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s) . '%';
        $where .= " AND (p.full_name LIKE ? ESCAPE '!' OR p.national_id LIKE ? ESCAPE '!' OR p.phone LIKE ? ESCAPE '!' OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(p.phone,' ',''),'-',''),'(',''),')',''),'+','') LIKE ? ESCAPE '!')";
        array_push(
            $args,
            $escape($q),
            $escape(preg_replace('/[.\s-]/u', '', $q) ?: $q),
            $escape($q),
            $escape(preg_match('/^[+0-9 ()-]+$/', $q) ? (preg_replace('/\D/', '', $q) ?: $q) : $q)
        );
    }
    $s = $db->prepare('SELECT COUNT(*)' . patients_joins() . $where);
    $s->execute($args);
    $total = (int) $s->fetchColumn();
    $page = min($page, max(1, (int) ceil($total / $size)));
    $offset = ($page - 1) * $size;
    $s = $db->prepare(patients_select() . patients_joins() . $where . " ORDER BY p.id DESC LIMIT $size OFFSET $offset");
    $s->execute($args);
    $rows = array_map(fn(array $p): array => patients_serialize($p), $s->fetchAll());
    patients_audit($db, $config, $actor, 'patient.listed', '0');
    return [
        'data' => $rows,
        'meta' => ['page' => $page, 'page_size' => $size, 'total' => $total, 'stats' => patients_stats($db)]
    ];
}

function patients_stats(PDO $db) : array
{
    $today = new \DateTimeImmutable(patient_input_today(), new \DateTimeZone('America/Montevideo'));
    $start = $today->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $end = $today->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $active = (int) $db->query("SELECT COUNT(*) FROM patients WHERE status='active'")->fetchColumn();
    $inpatient = (int) $db->query(
        "SELECT COUNT(*)" . patients_joins() . " WHERE p.status='active' AND e.care_status='inpatient'"
    )->fetchColumn();
    $s = $db->prepare('SELECT COUNT(DISTINCT patient_id) FROM patient_encounters WHERE discharged_at>=? AND discharged_at<?');
    $s->execute([$start, $end]);
    $discharged = (int) $s->fetchColumn();
    $s = $db->prepare("SELECT COUNT(*) FROM patient_appointments a JOIN patients p ON p.id=a.patient_id WHERE a.status='scheduled' AND p.status='active' AND a.scheduled_at>=? AND a.scheduled_at<?");
    $s->execute([$start, $end]);
    return [
        'active' => $active, 'inpatient' => $inpatient, 'discharged_today' => $discharged,
        'appointments_today' => (int) $s->fetchColumn()
    ];
}

function patients_catalogs(PDO $db, string $actor) : array
{
    patients_access($db, $actor);
    $doctors = $db->query("SELECT s.id,s.full_name FROM staff s JOIN staff_roles r ON r.staff_id=s.id AND r.role_code='doctor' LEFT JOIN doctor_profiles dp ON dp.staff_id=s.id WHERE s.status='active' AND (dp.staff_id IS NULL OR (dp.status='active' AND dp.availability<>'inactive')) ORDER BY s.full_name,s.id")->fetchAll();
    $departments = $db->query("SELECT id,name FROM departments WHERE status='active' ORDER BY name,id")->fetchAll();
    return [
        'doctors' => array_map(static fn(array $r): array => [
            'id' => (string) $r['id'], 'full_name' => $r['full_name']
        ], $doctors),
        'departments' => array_map(static fn(array $r): array => [
            'id' => (string) $r['id'], 'name' => $r['name']
        ], $departments),
        'create_token' => bin2hex(random_bytes(32))
    ];
}

function patients_save_related(PDO $db, string $id, array $f, ?array $old) : void
{
    $s = $db->prepare('INSERT INTO patient_contacts (patient_id,full_name,phone,relationship) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),phone=VALUES(phone),relationship=VALUES(relationship)');
    $s->execute([$id, $f['emergency_name'], $f['emergency_phone'], $f['emergency_relationship']]);
    $s = $db->prepare('SELECT * FROM patient_encounters WHERE patient_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $s->execute([$id]);
    $encounter = $s->fetch();
    if ($f['care_status'] === null) {
        if ($encounter)
            patient_input_invalid('care_status');
        return;
    }
    if (!$encounter && $f['care_status'] === 'discharged')
        patient_input_invalid('care_status');
    $newCase = !$encounter || ($encounter['care_status'] === 'discharged' && $f['care_status'] !== 'discharged');
    if ($f['doctor_staff_id'] !== null) {
        $s = $db->prepare("SELECT s.id FROM staff s JOIN staff_roles r ON r.staff_id=s.id AND r.role_code='doctor' LEFT JOIN doctor_profiles dp ON dp.staff_id=s.id WHERE s.id=? AND s.status='active' AND (dp.staff_id IS NULL OR (dp.status='active' AND dp.availability<>'inactive')) FOR UPDATE");
        $s->execute([$f['doctor_staff_id']]);
        if (!$s->fetchColumn() && ($newCase || (string) $encounter['doctor_staff_id'] !== $f['doctor_staff_id']))
            patient_input_invalid('doctor_staff_id');
    }
    $department = null;
    if ($f['department'] !== '') {
        $s = $db->prepare('INSERT INTO departments (name) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
        $s->execute([$f['department']]);
        $department = $db->lastInsertId();
        $s = $db->prepare('SELECT status FROM departments WHERE id=?');
        $s->execute([$department]);
        if ($s->fetchColumn() !== 'active' && ($newCase || (string) $encounter['department_id'] !== $department))
            patient_input_invalid('department');
    }
    $discharge = $f['care_status'] === 'discharged'
        ? ($encounter['discharged_at'] ?? gmdate('Y-m-d H:i:s')) : null;
    $values = [
        $f['care_status'], $f['room'], $department, $f['doctor_staff_id'], $f['doctor_specialty'],
        $f['admission_date'], $discharge, $f['diagnosis'], $f['clinical_state']
    ];
    if ($newCase) {
        $s = $db->prepare('INSERT INTO patient_encounters (care_status,room,department_id,doctor_staff_id,doctor_specialty,admission_date,discharged_at,diagnosis,clinical_state,patient_id) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $values[] = $id;
    } else {
        $s = $db->prepare('UPDATE patient_encounters SET care_status=?,room=?,department_id=?,doctor_staff_id=?,doctor_specialty=?,admission_date=?,discharged_at=?,diagnosis=?,clinical_state=? WHERE id=?');
        $values[] = $encounter['id'];
    }
    $s->execute($values);
}

function patients_identity_values(array $f, ?array $old) : array
{
    $recorded = $f['age'] === null ? null : (
        $old && $old['date_of_birth'] === null && $old['estimated_age'] !== null
        && (int) $old['estimated_age'] === $f['age']
        ? $old['age_recorded_on'] : patient_input_today()
    );
    return [
        $f['full_name'], $f['national_id'], $f['date_of_birth'], $f['sex'], $f['age'], $recorded,
        $f['phone'], $f['blood_group'], $f['allergies']
    ];
}

function patients_create(PDO $db, array $config, string $actor, array $input) : array
{
    $f = patient_input_fields($input);
    $token = $input['create_token'] ?? null;
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token))
        throw new ApiException(
            422, 'CREATE_TOKEN_REQUIRED',
            \Hospital\I18n::t("errors.se_requiere_una_referencia_de_creacion_valida")
        );
    $key = hash('sha256', $actor . ':' . $token);
    $fingerprint = hash('sha256', json_encode($f, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $id = patients_transaction($db, $config, $actor, function() use ($actor, $f, $key, $fingerprint, $db, $config) {
        $s = $db->prepare('SELECT id,create_fingerprint FROM patients WHERE create_key=?');
        $s->execute([$key]);
        $existing = $s->fetch();
        if ($existing) {
            if (!hash_equals($existing['create_fingerprint'], $fingerprint))
                throw new ApiException(
                    409, 'CREATE_TOKEN_REUSED',
                    \Hospital\I18n::t("errors.la_referencia_ya_se_utilizo_con_otros_datos")
                );
            return (string) $existing['id'];
        }
        $values = patients_identity_values($f, null);
        array_push($values, $key, $fingerprint);
        $s = $db->prepare('INSERT INTO patients (full_name,national_id,date_of_birth,sex,estimated_age,age_recorded_on,phone,blood_group,allergies,create_key,create_fingerprint) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $s->execute($values);
        $id = $db->lastInsertId();
        patients_save_related($db, $id, $f, null);
        patients_audit($db, $config, $actor, 'patient.created', $id);
        return $id;
    });
    return patients_get($db, $config, $actor, $id);
}

function patients_update(PDO $db, array $config, string $actor, string $id, array $input) : array
{
    $f = patient_input_fields($input);
    $version = patient_input_version($input['version'] ?? null);
    patients_transaction($db, $config, $actor, function() use ($actor, $id, $f, $version, $db, $config) {
        $p = patients_lock($db, $id, $version);
        if ($p['status'] !== 'active')
            throw new ApiException(
                409, 'PATIENT_ARCHIVED', \Hospital\I18n::t("errors.restaura_el_paciente_antes_de_editarlo")
            );
        $values = patients_identity_values($f, $p);
        $values[] = $id;
        $s = $db->prepare('UPDATE patients SET full_name=?,national_id=?,date_of_birth=?,sex=?,estimated_age=?,age_recorded_on=?,phone=?,blood_group=?,allergies=?,version=version+1 WHERE id=?');
        $s->execute($values);
        patients_save_related($db, $id, $f, $p);
        patients_audit($db, $config, $actor, 'patient.updated', $id);
    });
    return patients_get($db, $config, $actor, $id);
}

function patients_archive(
    PDO $db, array $config, ?string $actor, string $id, int $version, bool $restore = false
) : ?array
{
    patients_transaction($db, $config, $actor, function() use ($actor, $id, $version, $restore, $db, $config) {
        $p = patients_lock($db, $id, $version);
        $wanted = $restore ? 'active' : 'archived';
        if ($p['status'] === $wanted)
            throw new ApiException(
                409, 'PATIENT_STATE_CONFLICT', \Hospital\I18n::t("errors.el_estado_del_registro_cambio")
            );
        if (!$restore) {
            $s = $db->prepare("SELECT id FROM transfers WHERE patient_id=? AND status NOT IN ('completed','cancelled') LIMIT 1");
            $s->execute([$id]);
            if ($s->fetchColumn())
                throw new ApiException(
                    409, 'PATIENT_IN_USE',
                    \Hospital\I18n::t("errors.el_paciente_tiene_un_traslado_pendiente_o_en_curso")
                );
            $s = $db->prepare("SELECT id FROM patient_appointments WHERE patient_id=? AND status='scheduled' AND scheduled_at>=UTC_TIMESTAMP() LIMIT 1");
            $s->execute([$id]);
            if ($s->fetchColumn())
                throw new ApiException(
                    409, 'PATIENT_IN_USE', \Hospital\I18n::t("errors.el_paciente_tiene_una_cita_pendiente")
                );
        }
        $s = $db->prepare('UPDATE patients SET status=?,archived_at=?,version=version+1 WHERE id=?');
        $s->execute([$wanted, $restore ? null : gmdate('Y-m-d H:i:s'), $id]);
        patients_audit($db, $config, $actor, $restore ? 'patient.restored' : 'patient.archived', $id);
    });
    return $actor === null ? null : patients_get($db, $config, $actor, $id);
}

function patients_add_identity(PDO $db, array $config, string $ci, string $name) : string
{
    $ci = patient_input_identity($ci);
    $name = settings_text($name, 'full_name', 150, false);
    return patients_transaction($db, $config, null, function() use ($ci, $name, $db, $config) {
        $s = $db->prepare('INSERT INTO patients (national_id,full_name) VALUES (?,?)');
        $s->execute([$ci, $name]);
        $id = $db->lastInsertId();
        patients_audit($db, $config, null, 'patient.created', $id);
        return $id;
    });
}
