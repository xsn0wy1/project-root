<?php
declare(strict_types=1);

use Hospital\Http\ApiException;

// Transfer personnel directory.
const PERSONNEL_FIELDS = [
    'full_name',
    'national_id',
    'phone',
    'responsibility',
    'driving_license',
    'availability'
];

function personnel_access(PDO $db, array $config, string $actor): void
{
    transfer_require_dispatch($db, $actor);
}

function personnel_transaction(PDO $db, array $config, string $actor, callable $work)
{
    return transfer_catalog_transaction(
        $db,
        $config,
        $actor,
        $work,
        'PERSONNEL_CONFLICT',
        "errors.ya_existe_un_registro_con_ese_documento_o_licencia_revisa_tambien_los_archivados"
    );
}

function personnel_fields(array $input): array
{
    $f = [
        'full_name' => settings_text($input['full_name'] ?? null, 'full_name', 100, false),
        'national_id' => patient_input_identity($input['national_id'] ?? null),
        'phone' => patient_input_phone($input['phone'] ?? null, 'phone'),
        'responsibility' => transfer_input_choice($input['responsibility'] ?? null, ['driver', 'copilot']),
        'availability' => transfer_input_choice($input['availability'] ?? null, ['active', 'inactive'])
    ];
    if (strlen(preg_replace('/\D/', '', $f['phone'])) < 8)
        throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.ingresa_un_telefono_valido"));
    $license = strtoupper(preg_replace(
        '/[\s.-]/u',
        '',
        settings_text($input['driving_license'] ?? null, 'driving_license', 30)
    ));
    if (
        ($f['responsibility'] === 'driver' && $license === '') ||
        ($license !== '' && !preg_match('/^[A-Z0-9]{2,30}$/', $license))
    )
        throw new ApiException(
            422,
            'VALIDATION_FAILED',
            \Hospital\I18n::t("errors.ingresa_una_licencia_valida_para_el_chofer")
        );
    $f['driving_license'] = $license === '' ? null : $license;
    return $f;
}

function personnel_query(): string
{
    // Include legacy crew imported before personnel profiles existed.
    return "SELECT s.id,s.full_name,s.national_id,s.phone,s.status AS staff_status,s.version AS staff_version,
        COALESCE(p.responsibility,CASE WHEN EXISTS(SELECT 1 FROM staff_roles r WHERE r.staff_id=s.id AND r.role_code='driver') THEN 'driver' ELSE 'copilot' END) AS responsibility,
        p.driving_license,p.availability,p.status,p.version,p.archived_at
        FROM staff s LEFT JOIN personnel_profiles p ON p.staff_id=s.id
        WHERE (p.staff_id IS NOT NULL OR EXISTS(SELECT 1 FROM staff_roles r WHERE r.staff_id=s.id AND r.role_code IN ('driver','copilot')))";
}

function personnel_serialize(array $r): array
{
    $r['id'] = (string) $r['id'];
    $r['version'] = (int) ($r['version'] ?? 0) + (int) $r['staff_version'];
    unset($r['staff_version']);
    $r['phone'] = $r['phone'] ?? '';
    $r['driving_license'] = $r['driving_license'] ?? '';
    $r['availability'] = $r['availability'] ?? 'active';
    $r['status'] = $r['status'] ?? 'active';
    $r['archived_at'] = $r['archived_at'] === null ? null : users_date($r['archived_at']);
    return $r;
}

function personnel_listing(PDO $db, array $config, string $actor): array
{
    personnel_access($db, $config, $actor);
    return array_map(
        fn (array $r): array => personnel_serialize($r),
        $db->query(personnel_query() . ' ORDER BY s.full_name,s.id')->fetchAll()
    );
}

function personnel_get(PDO $db, array $config, string $actor, string $id): array
{
    personnel_access($db, $config, $actor);
    $s = $db->prepare(personnel_query() . ' AND s.id=?');
    $s->execute([$id]);
    $r = $s->fetch();
    if (!$r)
        throw new ApiException(
            404,
            'PERSONNEL_NOT_FOUND',
            \Hospital\I18n::t("errors.no_se_encontro_el_registro_de_personal")
        );
    return personnel_serialize($r);
}

function personnel_lock(PDO $db, string $id, int $version): array
{
    $s = $db->prepare('SELECT * FROM staff WHERE id=? FOR UPDATE');
    $s->execute([$id]);
    $staff = $s->fetch();
    if (!$staff)
        throw new ApiException(
            404,
            'PERSONNEL_NOT_FOUND',
            \Hospital\I18n::t("errors.no_se_encontro_el_registro_de_personal")
        );
    $s = $db->prepare('SELECT * FROM personnel_profiles WHERE staff_id=? FOR UPDATE');
    $s->execute([$id]);
    $profile = $s->fetch();
    $s = $db->prepare(
        "SELECT role_code FROM staff_roles WHERE staff_id=? AND role_code IN ('driver','copilot') ORDER BY role_code"
    );
    $s->execute([$id]);
    $roles = $s->fetchAll(\PDO::FETCH_COLUMN);
    if (!$profile && !$roles)
        throw new ApiException(
            404,
            'PERSONNEL_NOT_FOUND',
            \Hospital\I18n::t("errors.no_se_encontro_el_registro_de_personal")
        );
    if ((int) ($profile['version'] ?? 0) + (int) $staff['version'] !== $version)
        throw new ApiException(
            409,
            'VERSION_CONFLICT',
            \Hospital\I18n::t("errors.el_registro_cambio_volve_a_abrirlo_antes_de_guardar")
        );
    return ['staff' => $staff, 'profile' => $profile, 'roles' => $roles];
}

function personnel_check_transfers(PDO $db, string $id): void
{
    $s = $db->prepare(
        "SELECT t.id FROM transfers t JOIN transfer_crew c ON c.transfer_id=t.id WHERE c.staff_id=? AND c.responsibility IN ('driver','copilot') AND t.status NOT IN ('completed','cancelled') LIMIT 1"
    );
    $s->execute([$id]);
    if ($s->fetchColumn())
        throw new ApiException(
            409,
            'PERSONNEL_IN_USE',
            \Hospital\I18n::t("errors.el_personal_tiene_un_traslado_pendiente_o_en_curso")
        );
}

// Called inside personnel_transaction; these helpers never commit independently.

function personnel_create_identity(PDO $db, array $f): string
{
    $s = $db->prepare('SELECT id,status FROM staff WHERE national_id=? FOR UPDATE');
    $s->execute([$f['national_id']]);
    $staff = $s->fetch();
    if ($staff) {
        $id = (string) $staff['id'];
        if ($staff['status'] !== 'active')
            throw new ApiException(
                409,
                'STAFF_INACTIVE',
                \Hospital\I18n::t("errors.la_identidad_del_personal_esta_inactiva")
            );
        $s = $db->prepare(
            "SELECT id FROM staff WHERE id=? AND (EXISTS(SELECT 1 FROM personnel_profiles WHERE staff_id=staff.id) OR EXISTS(SELECT 1 FROM staff_roles WHERE staff_id=staff.id AND role_code IN ('driver','copilot')))"
        );
        $s->execute([$id]);
        if ($s->fetchColumn())
            throw new ApiException(
                409,
                'PERSONNEL_CONFLICT',
                \Hospital\I18n::t(
                    "errors.el_personal_ya_esta_registrado_abrilo_desde_el_listado_para_editarlo"
                )
            );
    } else {
        $s = $db->prepare('INSERT INTO staff (full_name,national_id) VALUES (?,?)');
        $s->execute([$f['full_name'], $f['national_id']]);
        $id = $db->lastInsertId();
    }
    return $id;
}

function personnel_check_identity_update(PDO $db, array $f, string $id, int $version): bool
{
    $old = personnel_lock($db, $id, $version);
    $profileExists = (bool) $old['profile'];
    if (($old['profile']['status'] ?? 'active') !== 'active')
        throw new ApiException(
            409,
            'PERSONNEL_ARCHIVED',
            \Hospital\I18n::t("errors.restaura_el_registro_antes_de_editarlo")
        );
    if ($f['availability'] === 'inactive' || array_diff($old['roles'], [$f['responsibility']]))
        personnel_check_transfers($db, $id);
    $s = $db->prepare('SELECT registration_national_id FROM users WHERE staff_id=?');
    $s->execute([$id]);
    $ci = $s->fetchColumn();
    if ($ci !== false && $ci !== $f['national_id'])
        throw new ApiException(
            409,
            'IDENTITY_LINKED',
            \Hospital\I18n::t(
                "errors.el_documento_esta_vinculado_a_una_cuenta_y_no_puede_cambiarse_desde_este_directorio"
            )
        );
    return $profileExists;
}

function personnel_store_profile(PDO $db, array $f, string $id, bool $profileExists): void
{
    if ($profileExists) {
        $s = $db->prepare(
            'UPDATE personnel_profiles SET responsibility=?,driving_license=?,availability=?,version=version+1 WHERE staff_id=?'
        );
        $s->execute([$f['responsibility'], $f['driving_license'], $f['availability'], $id]);
    } else {
        $s = $db->prepare(
            'INSERT INTO personnel_profiles (staff_id,responsibility,driving_license,availability) VALUES (?,?,?,?)'
        );
        $s->execute([$id, $f['responsibility'], $f['driving_license'], $f['availability']]);
    }
    // The form owns crew responsibilities only; preserve doctor/account roles.
    $s = $db->prepare("DELETE FROM staff_roles WHERE staff_id=? AND role_code IN ('driver','copilot')");
    $s->execute([$id]);
    $s = $db->prepare('INSERT INTO staff_roles (staff_id,role_code) VALUES (?,?)');
    $s->execute([$id, $f['responsibility']]);
}

function personnel_save(PDO $db, array $config, string $actor, array $input, ?string $id = null): array
{
    $f = personnel_fields($input);
    $version = $id === null ? null : \Hospital\Http\Request::version($input['version'] ?? null);
    $id = personnel_transaction(
        $db,
        $config,
        $actor,
        function () use ($actor, $f, $id, $version, $db) {
            if ($id === null) {
                $profileExists = false;
                $id = personnel_create_identity($db, $f);
            } else {
                $profileExists = personnel_check_identity_update($db, $f, $id, $version);
            }
            $s = $db->prepare('UPDATE staff SET full_name=?,national_id=?,phone=?,version=version+1 WHERE id=?');
            $s->execute([$f['full_name'], $f['national_id'], $f['phone'], $id]);
            personnel_store_profile($db, $f, $id, $profileExists);
            transfer_audit(
                $db,
                $actor,
                $version === null ? 'personnel.created' : 'personnel.updated',
                $id,
                'personnel'
            );
            return $id;
        }
    );
    return personnel_get($db, $config, $actor, $id);
}

function personnel_archive(
    PDO $db,
    array $config,
    string $actor,
    string $id,
    int $version,
    bool $restore = false
): array {
    personnel_transaction(
        $db,
        $config,
        $actor,
        function () use (
            $actor,
            $id,
            $version,
            $restore,
            $db,
            $config
        ) {
            $old = personnel_lock($db, $id, $version);
            $state = $restore ? 'active' : 'archived';
            if (($old['profile']['status'] ?? 'active') === $state)
                throw new ApiException(
                    409,
                    'VERSION_CONFLICT',
                    \Hospital\I18n::t("errors.el_estado_del_registro_cambio")
                );
            if (!$restore)
                personnel_check_transfers($db, $id);
            if (!$old['profile']) {
                $role = in_array('driver', $old['roles'], true) ? 'driver' : 'copilot';
                $s = $db->prepare('INSERT INTO personnel_profiles (staff_id,responsibility) VALUES (?,?)');
                $s->execute([$id, $role]);
            }
            $s = $db->prepare('UPDATE personnel_profiles SET status=?,archived_at=?,version=version+1 WHERE staff_id=?');
            $s->execute([$state, $restore ? null : gmdate('Y-m-d H:i:s'), $id]);
            transfer_audit(
                $db,
                $actor,
                $restore ? 'personnel.restored' : 'personnel.archived',
                $id,
                'personnel'
            );
        }
    );
    return personnel_get($db, $config, $actor, $id);
}
