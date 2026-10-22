<?php
declare(strict_types=1);

use Hospital\Http\ApiException;

// Destination catalog.
const DESTINATIONS_FIELDS = [
    'name', 'address', 'locality', 'facility_type', 'phone', 'notes', 'availability'
];

function destinations_access(PDO $db, array $config, string $actor): void
{
    transfer_require_dispatch($db, $actor);
}

function destinations_transaction(PDO $db, array $config, string $actor, callable $work)
{
    return transfer_catalog_transaction(
        $db, $config, $actor, $work, 'DESTINATION_CONFLICT',
        "errors.ya_existe_ese_centro_con_la_misma_direccion_y_localidad_revisa_tambien_los_inactivos"
    );
}

function destinations_fields(array $input): array
{
    $f = [];
    foreach (['name' => 100, 'address' => 180, 'locality' => 80, 'phone' => 25] as $key => $max)
        $f[$key] = settings_text($input[$key] ?? null, $key, $max, $key === 'phone');
    $digits = strlen(preg_replace('/\D/', '', $f['phone']));
    if (
        $f['phone'] !== '' &&
        (!preg_match('/^\+?[0-9 ().-]+$/', $f['phone']) || $digits < 8 || $digits > 15)
    )
        throw new ApiException(
            422,
            'VALIDATION_FAILED',
            \Hospital\I18n::t("errors.ingresa_un_telefono_de_8_a_15_digitos_o_dejalo_vacio")
        );
    $f['facility_type'] = transfer_input_choice(
        $input['facility_type'] ?? null,
        ['hospital', 'sanatorium', 'health_center', 'other']
    );
    $f['availability'] = transfer_input_choice($input['availability'] ?? null, ['active', 'inactive']);
    $f['notes'] = transfer_input_notes($input['notes'] ?? null);
    if (mb_strlen($f['notes']) > 400)
        throw new ApiException(
            422,
            'VALIDATION_FAILED',
            \Hospital\I18n::t("errors.la_referencia_admite_hasta_400_caracteres")
        );
    return $f;
}

function destinations_serialize(array $row): array
{
    $row['id'] = (string) $row['id'];
    $row['version'] = (int) $row['version'];
    foreach (['latitude', 'longitude'] as $key)
        $row[$key] = $row[$key] === null ? null : (float) $row[$key];
    foreach (['created_at', 'updated_at', 'archived_at'] as $key)
        $row[$key] = $row[$key] === null ? null : users_date($row[$key]);
    return $row;
}

function destinations_listing(PDO $db, array $config, string $actor): array
{
    destinations_access($db, $config, $actor);
    return array_map(
        fn(array $r): array => destinations_serialize($r),
        $db->query('SELECT * FROM facilities ORDER BY name,address,locality,id')->fetchAll()
    );
}

function destinations_get(PDO $db, array $config, string $actor, string $id): array
{
    destinations_access($db, $config, $actor);
    $s = $db->prepare('SELECT * FROM facilities WHERE id=?');
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row)
        throw new ApiException(
            404, 'DESTINATION_NOT_FOUND', \Hospital\I18n::t("errors.no_se_encontro_el_destino")
        );
    return destinations_serialize($row);
}

function destinations_lock(PDO $db, string $id, int $version): array
{
    $s = $db->prepare('SELECT * FROM facilities WHERE id=? FOR UPDATE');
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row)
        throw new ApiException(
            404, 'DESTINATION_NOT_FOUND', \Hospital\I18n::t("errors.no_se_encontro_el_destino")
        );
    if ((int) $row['version'] !== $version)
        throw new ApiException(
            409,
            'VERSION_CONFLICT',
            \Hospital\I18n::t("errors.el_destino_cambio_volve_a_abrirlo_antes_de_guardar")
        );
    return $row;
}

function destinations_save(
    PDO $db,
    array $config,
    string $actor,
    array $input,
    ?string $id = null
): array
{
    $f = destinations_fields($input);
    $version = $id === null ? null : \Hospital\Http\Request::version($input['version'] ?? null);
    $id = destinations_transaction($db, $config, $actor, function () use (
        $actor, $f, $id, $version, $db, $config
    ) {
        $values = [
            $f['name'], $f['address'], $f['locality'], $f['facility_type'],
            $f['phone'], $f['notes'], $f['availability']
        ];
        if ($id === null) {
            $s = $db->prepare(
                'INSERT INTO facilities (name,address,locality,facility_type,phone,notes,availability) VALUES (?,?,?,?,?,?,?)'
            );
            $s->execute($values);
            $id = $db->lastInsertId();
        } else {
            $old = destinations_lock($db, $id, $version);
            if ($old['status'] !== 'active')
                throw new ApiException(
                    409,
                    'DESTINATION_ARCHIVED',
                    \Hospital\I18n::t("errors.restaura_el_destino_antes_de_editarlo")
                );
            // A changed address must not keep an old map location. Trace snapshots remain intact.
            $moved = $old['address'] !== $f['address'] || $old['locality'] !== $f['locality'];
            $values[] = $id;
            $s = $db->prepare(
                'UPDATE facilities SET name=?,address=?,locality=?,facility_type=?,phone=?,notes=?,availability=?,version=version+1'
                . ($moved ? ',latitude=NULL,longitude=NULL' : '') . ' WHERE id=?'
            );
            $s->execute($values);
        }
        transfer_audit(
            $db, $actor, $version === null ? 'destination.created' : 'destination.updated', $id, 'facility'
        );
        return $id;
    });
    return destinations_get($db, $config, $actor, $id);
}

function destinations_archive(
    PDO $db,
    array $config,
    string $actor,
    string $id,
    int $version,
    bool $restore = false
): array
{
    destinations_transaction($db, $config, $actor, function () use (
        $actor, $id, $version, $restore, $db, $config
    ) {
        $old = destinations_lock($db, $id, $version);
        $state = $restore ? 'active' : 'archived';
        if ($old['status'] === $state)
            throw new ApiException(
                409,
                'VERSION_CONFLICT',
                \Hospital\I18n::t("errors.el_estado_del_destino_cambio")
            );
        $s = $db->prepare('UPDATE facilities SET status=?,archived_at=?,version=version+1 WHERE id=?');
        $s->execute([$state, $restore ? null : gmdate('Y-m-d H:i:s'), $id]);
        transfer_audit(
            $db, $actor, $restore ? 'destination.restored' : 'destination.archived', $id, 'facility'
        );
    });
    return destinations_get($db, $config, $actor, $id);
}
