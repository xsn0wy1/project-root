<?php
declare(strict_types=1);

use Hospital\Http\ApiException;
use Hospital\I18n;

// The domain handler authorizes reads. Writes also recheck the actor inside the booking lock.
function ambulance_serialize(array $row): array
{
    $row['id'] = (string) $row['id'];
    $row['capacity'] = (int) $row['capacity'];
    $row['version'] = (int) $row['version'];
    foreach (['created_at', 'updated_at', 'archived_at'] as $key) {
        $row[$key] = $row[$key] === null ? null : users_date($row[$key]);
    }
    return $row;
}

function ambulance_list(PDO $db): array
{
    return array_map(
        'ambulance_serialize',
        $db->query('SELECT * FROM ambulances ORDER BY id DESC')->fetchAll()
    );
}

function ambulance_get(PDO $db, string $id): array
{
    $query = $db->prepare('SELECT * FROM ambulances WHERE id=?');
    $query->execute([$id]);
    $row = $query->fetch();
    if (!$row)
        throw new ApiException(404, 'AMBULANCE_NOT_FOUND', I18n::t('errors.no_se_encontro_la_ambulancia'));
    return ambulance_serialize($row);
}

function ambulance_write(PDO $db, array $config, string $actor, callable $work): array
{
    // Same named lock as transfer booking and the simulation worker, released even on failure.
    $id = transfer_serialized($db, $config, function () use ($db, $config, $actor, $work) {
        $db->beginTransaction();
        try {
            transfer_actor($db, $actor);
            $id = $work();
            $db->commit();
            return $id;
        } catch (Throwable $error) {
            if ($db->inTransaction())
                $db->rollBack();
            if ($error instanceof PDOException && ($error->errorInfo[1] ?? 0) === 1062) {
                throw new ApiException(
                    409,
                    'AMBULANCE_CONFLICT',
                    I18n::t(
                        'errors.ya_existe_una_ambulancia_con_esa_matricula_o_codigo_revisa_tambien_las_archivadas'
                    )
                );
            }
            throw $error;
        }
    });
    transfer_require_dispatch($db, $actor);
    return ambulance_get($db, $id);
}

function ambulance_fields(array $input): array
{
    $plate = strtoupper(preg_replace(
        '/[\s-]/u', '', settings_text($input['plate'] ?? null, 'plate', 20, false)
    ));
    if (!preg_match('/^[A-Z0-9]{1,20}$/', $plate)) {
        throw new ApiException(
            422,
            'VALIDATION_FAILED',
            I18n::t('errors.ingresa_una_matricula_valida'),
            ['plate' => I18n::t('errors.valor_no_valido')]
        );
    }
    $model = settings_text($input['model'] ?? null, 'model', 100, false);
    $capacity = $input['capacity'] ?? null;
    if (
        (!is_int($capacity) && !is_string($capacity)) ||
        !preg_match('/^[1-9][0-9]?$/', (string) $capacity)
    ) {
        throw new ApiException(
            422,
            'VALIDATION_FAILED',
            I18n::t('errors.la_capacidad_debe_ser_de_1_a_99_plazas'),
            ['capacity' => I18n::t('errors.valor_no_valido')]
        );
    }
    $status = transfer_input_choice(
        $input['operational_status'] ?? null,
        ['available', 'maintenance', 'out_of_service']
    );
    return [
        'plate' => $plate,
        'model' => $model,
        'capacity' => (int) $capacity,
        'operational_status' => $status
    ];
}

function ambulance_lock(PDO $db, string $id, int $version): array
{
    $query = $db->prepare('SELECT * FROM ambulances WHERE id=? FOR UPDATE');
    $query->execute([$id]);
    $row = $query->fetch();
    if (!$row)
        throw new ApiException(404, 'AMBULANCE_NOT_FOUND', I18n::t('errors.no_se_encontro_la_ambulancia'));
    if ((int) $row['version'] !== $version)
        throw new ApiException(
            409,
            'VERSION_CONFLICT',
            I18n::t('errors.la_ambulancia_cambio_volve_a_abrirla_antes_de_guardar')
        );
    return $row;
}

function ambulance_check_transfers(PDO $db, string $id): void
{
    $query = $db->prepare(
        "SELECT id FROM transfers WHERE ambulance_id=? AND status NOT IN ('completed','cancelled') LIMIT 1"
    );
    $query->execute([$id]);
    if ($query->fetchColumn())
        throw new ApiException(
            409,
            'AMBULANCE_IN_USE',
            I18n::t('errors.la_ambulancia_tiene_un_traslado_pendiente_o_en_curso')
        );
}

function ambulance_save(PDO $db, array $config, string $actor, array $input, ?string $id = null): array
{
    $fields = ambulance_fields($input);
    $version = $id === null ? null : \Hospital\Http\Request::version($input['version'] ?? null);
    return ambulance_write($db, $config, $actor, function () use (
        $db, $config, $actor, $id, $fields, $version
    ) {
        if ($id === null) {
            $query = $db->prepare(
                'INSERT INTO ambulances (code,plate,model,capacity,operational_status) VALUES (?,?,?,?,?)'
            );
            $query->execute([
                'NEW-' . bin2hex(random_bytes(6)),
                $fields['plate'],
                $fields['model'],
                $fields['capacity'],
                $fields['operational_status']
            ]);
            $id = $db->lastInsertId();
            $query = $db->prepare('UPDATE ambulances SET code=? WHERE id=?');
            $query->execute(['AMB-' . str_pad($id, 6, '0', STR_PAD_LEFT), $id]);
        } else {
            $old = ambulance_lock($db, $id, $version);
            if ($old['status'] !== 'active')
                throw new ApiException(
                    409,
                    'AMBULANCE_ARCHIVED',
                    I18n::t('errors.restaura_la_ambulancia_antes_de_editarla')
                );
            if ($fields['operational_status'] !== 'available')
                ambulance_check_transfers($db, $id);
            $query = $db->prepare(
                'UPDATE ambulances SET plate=?,model=?,capacity=?,operational_status=?,version=version+1 WHERE id=?'
            );
            $query->execute([
                $fields['plate'], $fields['model'], $fields['capacity'], $fields['operational_status'], $id
            ]);
        }
        transfer_audit($db, $actor, $version === null ? 'ambulance.created' : 'ambulance.updated', $id, 'ambulance');
        return $id;
    });
}

function ambulance_archive(
    PDO $db,
    array $config,
    string $actor,
    string $id,
    int $version,
    bool $restore
): array
{
    return ambulance_write($db, $config, $actor, function () use (
        $db, $config, $actor, $id, $version, $restore
    ) {
        $old = ambulance_lock($db, $id, $version);
        $state = $restore ? 'active' : 'archived';
        if ($old['status'] === $state)
            throw new ApiException(
                409,
                'VERSION_CONFLICT',
                I18n::t('errors.el_estado_de_la_ambulancia_cambio')
            );
        if (!$restore)
            ambulance_check_transfers($db, $id);
        $query = $db->prepare('UPDATE ambulances SET status=?,archived_at=?,version=version+1 WHERE id=?');
        $query->execute([$state, $restore ? null : gmdate('Y-m-d H:i:s'), $id]);
        transfer_audit($db, $actor, $restore ? 'ambulance.restored' : 'ambulance.archived', $id, 'ambulance');
        return $id;
    });
}
