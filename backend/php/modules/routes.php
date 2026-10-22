<?php
declare(strict_types=1);

use Hospital\Http\ApiException;

// Reusable routes and saved mapping snapshots.
const ROUTES_FIELDS = [
    'name',
    'category',
    'direction',
    'availability',
    'origin_name',
    'origin_address',
    'destination_name',
    'destination_address',
    'stops',
    'distance_meters',
    'duration_seconds',
    'notes'
];

function routes_access(PDO $db, array $config, string $actor): void
{
    transfer_require_dispatch($db, $actor);
}

function routes_transaction(PDO $db, array $config, string $actor, callable $work)
{
    return transfer_serialized(
        $db,
        $config,
        function () use ($actor, $work, $db, $config) {
            $db->beginTransaction();
            try {
                transfer_actor($db, $actor);
                $result = $work();
                $db->commit();
                return $result;
            } catch (\Throwable $e) {
                if ($db->inTransaction())
                    $db->rollBack();
                throw $e;
            }
        }
    );
}

function routes_fields(array $input): array
{
    $f = [];
    foreach ([
        'name' => 80,
        'origin_name' => 100,
        'origin_address' => 160,
        'destination_name' => 100,
        'destination_address' => 160
    ] as $key => $max)
        $f[$key] = settings_text($input[$key] ?? null, $key, $max, false);
    $f['category'] = transfer_input_choice(
        $input['category'] ?? null,
        ['interhospital', 'consultation', 'treatment', 'discharge']
    );
    $f['direction'] = transfer_input_choice($input['direction'] ?? null, ['one_way', 'round_trip']);
    $f['availability'] = transfer_input_choice($input['availability'] ?? null, ['active', 'inactive']);
    $f['notes'] = transfer_input_notes($input['notes'] ?? '');
    if (mb_strlen($f['notes']) > 500)
        throw new ApiException(
            422,
            'VALIDATION_FAILED',
            \Hospital\I18n::t("errors.las_indicaciones_admiten_hasta_500_caracteres")
        );
    foreach (['distance_meters' => [100, 2000000], 'duration_seconds' => [60, 172800]] as $key => [$min, $max]) {
        $value = $input[$key] ?? null;
        if (
            (!is_int($value) && !is_string($value)) || !preg_match('/^[0-9]+$/', (string) $value)
            || (int) $value < $min || (int) $value > $max
        )
            throw new ApiException(
                422,
                'VALIDATION_FAILED',
                \Hospital\I18n::t("errors.revisa_las_estimaciones_del_recorrido")
            );
        $f[$key] = (int) $value;
    }
    $stops = $input['stops'] ?? [];
    if (
        !is_array($stops) || count($stops) > 6
        || ($stops && array_keys($stops) !== range(0, count($stops) - 1))
    )
        throw new ApiException(
            422,
            'VALIDATION_FAILED',
            \Hospital\I18n::t("errors.se_admiten_hasta_seis_paradas_ordenadas")
        );
    $f['stops'] = [];
    foreach ($stops as $stop) {
        if (!is_array($stop) || array_diff(array_keys($stop), ['name', 'address']))
            throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.parada_no_valida"));
        $f['stops'][] = [
            'name' => settings_text($stop['name'] ?? null, 'name', 100, false),
            'address' => settings_text($stop['address'] ?? null, 'address', 160, false)
        ];
    }
    $points = routes_places($f);
    $same = static fn (array $a, array $b): bool =>
        mb_strtolower($a['name']) === mb_strtolower($b['name'])
        && mb_strtolower($a['address']) === mb_strtolower($b['address']);
    if ($same($points[0], $points[count($points) - 1]))
        throw new ApiException(
            422,
            'VALIDATION_FAILED',
            \Hospital\I18n::t("errors.el_origen_y_destino_deben_ser_diferentes")
        );
    for ($i = 1; $i < count($points); $i++)
        if ($same($points[$i - 1], $points[$i]))
            throw new ApiException(
                422,
                'VALIDATION_FAILED',
                \Hospital\I18n::t("errors.dos_paradas_consecutivas_deben_ser_diferentes")
            );
    return $f;
}

function routes_places(array $f): array
{
    return array_merge(
        [['name' => $f['origin_name'], 'address' => $f['origin_address']]],
        $f['stops'],
        [['name' => $f['destination_name'], 'address' => $f['destination_address']]]
    );
}

function routes_listing(PDO $db, array $config, string $actor): array
{
    routes_access($db, $config, $actor);
    $rows = $db->query('SELECT * FROM route_templates ORDER BY id')->fetchAll();
    return array_map(fn (array $r): array => routes_payload($db, $r), $rows);
}

function routes_payload(PDO $db, array $row): array
{
    $s = $db->prepare('SELECT name,address FROM route_stops WHERE route_id=? ORDER BY stop_number');
    $s->execute([$row['id']]);
    $row['stops'] = $s->fetchAll();
    $row['geometry'] = json_decode($row['geometry'], true, 32, JSON_THROW_ON_ERROR);
    foreach (['id', 'origin_facility_id', 'destination_facility_id'] as $key)
        $row[$key] = $row[$key] === null ? null : (string) $row[$key];
    foreach (['version', 'distance_meters', 'duration_seconds'] as $key)
        $row[$key] = (int) $row[$key];
    foreach (['created_at', 'updated_at', 'archived_at'] as $key)
        $row[$key] = $row[$key] === null ? null : users_date($row[$key]);
    return $row;
}

function routes_get(PDO $db, array $config, string $actor, string $id): array
{
    routes_access($db, $config, $actor);
    $s = $db->prepare('SELECT * FROM route_templates WHERE id=?');
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row)
        throw new ApiException(404, 'ROUTE_NOT_FOUND', \Hospital\I18n::t("errors.no_se_encontro_la_ruta"));
    return routes_payload($db, $row);
}

function routes_lock(PDO $db, string $id, int $version): array
{
    $s = $db->prepare('SELECT * FROM route_templates WHERE id=? FOR UPDATE');
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row)
        throw new ApiException(404, 'ROUTE_NOT_FOUND', \Hospital\I18n::t("errors.no_se_encontro_la_ruta"));
    if ((int) $row['version'] !== $version)
        throw new ApiException(
            409,
            'VERSION_CONFLICT',
            \Hospital\I18n::t("errors.la_ruta_cambio_volve_a_abrirla_antes_de_guardar")
        );
    return $row;
}

// Persistence helpers run inside the caller's routes_transaction.

function routes_store_row(PDO $db, array $f, ?string $id, string $geometry): string
{
    $row = $f;
    unset($row['stops']);
    $row['geometry'] = $geometry;
    // Optional catalog associations never create hospital centers implicitly.
    foreach (['origin', 'destination'] as $prefix) {
        $s = $db->prepare("SELECT id FROM facilities WHERE name=? AND address=? AND status='active' AND availability='active' ORDER BY id LIMIT 2");
        $s->execute([$f[$prefix . '_name'], $f[$prefix . '_address']]);
        $ids = $s->fetchAll(\PDO::FETCH_COLUMN);
        $row[$prefix . '_facility_id'] = count($ids) === 1 ? $ids[0] : null;
    }
    if ($id === null) {
        $row['code'] = 'new-' . bin2hex(random_bytes(12));
        $s = $db->prepare(
            'INSERT INTO route_templates (' . implode(',', array_keys($row)) . ') VALUES ('
            . implode(',', array_fill(0, count($row), '?')) . ')'
        );
        $s->execute(array_values($row));
        $id = $db->lastInsertId();
        $number = max(
            (int) $id,
            1 + (int) $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING(code,4) AS UNSIGNED)),0) FROM route_templates WHERE code REGEXP '^RT-[0-9]+$'")->fetchColumn()
        );
        $s = $db->prepare('UPDATE route_templates SET code=? WHERE id=?');
        $s->execute(['RT-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT), $id]);
    } else {
        $sets = array_map(static fn (string $key): string => $key . '=?', array_keys($row));
        $s = $db->prepare('UPDATE route_templates SET ' . implode(',', $sets) . ',version=version+1 WHERE id=?');
        $s->execute(array_merge(array_values($row), [$id]));
    }
    return $id;
}

function routes_store_stops(PDO $db, string $id, array $stops): void
{
    $s = $db->prepare('DELETE FROM route_stops WHERE route_id=?');
    $s->execute([$id]);
    foreach ($stops as $index => $stop) {
        $s = $db->prepare('INSERT INTO route_stops (route_id,stop_number,name,address) VALUES (?,?,?,?)');
        $s->execute([$id, $index + 1, $stop['name'], $stop['address']]);
    }
}

function routes_save(PDO $db, array $config, string $actor, array $input, ?string $id = null): array
{
    routes_access($db, $config, $actor);
    $f = routes_fields($input);
    $version = $id === null ? null : \Hospital\Http\Request::version($input['version'] ?? null);
    $previous = $id === null ? null : routes_get($db, $config, $actor, $id);
    if ($previous && $previous['version'] !== $version)
        throw new ApiException(
            409,
            'VERSION_CONFLICT',
            \Hospital\I18n::t("errors.la_ruta_cambio_volve_a_abrirla_antes_de_guardar")
        );
    $same = $previous && routes_places($f) === routes_places($previous);
    $geometry = $same ? json_encode($previous['geometry'], JSON_THROW_ON_ERROR) : '[]';
    if (!$same || !$previous['geometry']) {
        $mapped = route_mapping_path($db, $config, routes_places($f));
        if ($mapped)
            $geometry = $mapped['geometry'];
    }
    $id = routes_transaction(
        $db,
        $config,
        $actor,
        function () use ($actor, $f, $id, $version, $geometry, $db, $config) {
            if ($id !== null && routes_lock($db, $id, $version)['status'] !== 'active')
                throw new ApiException(
                    409,
                    'ROUTE_ARCHIVED',
                    \Hospital\I18n::t("errors.restaura_la_ruta_antes_de_editarla")
                );
            $s = $db->prepare('SELECT id FROM route_templates WHERE name=? AND id<>? LIMIT 1');
            $s->execute([$f['name'], $id ?? 0]);
            if ($s->fetchColumn())
                throw new ApiException(
                    409,
                    'ROUTE_CONFLICT',
                    \Hospital\I18n::t("errors.ya_existe_una_ruta_con_ese_nombre_revisa_tambien_las_inactivas")
                );
            $id = routes_store_row($db, $f, $id, $geometry);
            routes_store_stops($db, $id, $f['stops']);
            transfer_audit($db, $actor, $version === null ? 'route.created' : 'route.updated', $id, 'route');
            return $id;
        }
    );
    return routes_get($db, $config, $actor, $id);
}

function routes_archive(
    PDO $db,
    array $config,
    string $actor,
    string $id,
    int $version,
    bool $restore = false
): array {
    routes_transaction(
        $db,
        $config,
        $actor,
        function () use ($actor, $id, $version, $restore, $db, $config) {
            $old = routes_lock($db, $id, $version);
            $state = $restore ? 'active' : 'archived';
            if ($old['status'] === $state)
                throw new ApiException(
                    409,
                    'VERSION_CONFLICT',
                    \Hospital\I18n::t("errors.el_estado_de_la_ruta_cambio")
                );
            $s = $db->prepare('UPDATE route_templates SET status=?,archived_at=?,version=version+1 WHERE id=?');
            $s->execute([$state, $restore ? null : gmdate('Y-m-d H:i:s'), $id]);
            transfer_audit($db, $actor, $restore ? 'route.restored' : 'route.archived', $id, 'route');
        }
    );
    return routes_get($db, $config, $actor, $id);
}

// Shared mapping cache and bounded lookup; Transfers also uses this implementation.

function route_mapping_path(PDO $db, array $config, array $places): ?array
{
    if (
        !($config['route_mapping_enabled'] ?? false)
        || empty($config['geocoder_url']) || empty($config['router_url'])
    )
        return null;
    $deadline = microtime(true) + 12;
    try {
        $coordinates = route_mapping_coordinates($db, $config, $places, $deadline);
        if ($coordinates === null)
            return null;
        $data = route_mapping_cached(
            $db,
            rtrim($config['router_url'] ?? '', '/') . '/' . implode(';', $coordinates)
            . '?overview=full&geometries=geojson&steps=false',
            $deadline
        );
        return route_mapping_response($data);
    } catch (\Throwable $e) {
        error_log('[Hospital route mapping] Lookup unavailable: ' . get_class($e));
        return null;
    }
}

// Every geocoder and router call receives the same absolute deadline.

function route_mapping_coordinates(PDO $db, array $config, array $places, float $deadline): ?array
{
    $coordinates = [];
    foreach ($places as $place) {
        // Existing exact catalog coordinates need no external lookup.
        $s = $db->prepare('SELECT latitude,longitude FROM facilities WHERE name=? AND address=? AND latitude IS NOT NULL AND longitude IS NOT NULL LIMIT 2');
        $s->execute([$place['name'], $place['address']]);
        $known = $s->fetchAll();
        if (count($known) === 1)
            $point = ['latitude' => (float) $known[0]['latitude'], 'longitude' => (float) $known[0]['longitude']];
        else {
            $query = $place['address'] !== '' ? $place['address'] : $place['name'];
            $data = route_mapping_cached(
                $db,
                ($config['geocoder_url'] ?? '') . '?' . http_build_query([
                    'q' => $query,
                    'countrycode' => $config['map_country'] ?? 'UY',
                    'limit' => 1
                ]),
                $deadline
            );
            $xy = $data['features'][0]['geometry']['coordinates'] ?? null;
            if (!is_array($xy) || count($xy) !== 2)
                return null;
            $point = ['latitude' => $xy[1], 'longitude' => $xy[0]];
        }
        foreach (['latitude' => 90, 'longitude' => 180] as $key => $limit)
            if (
                !is_numeric($point[$key]) || !is_finite((float) $point[$key])
                || abs((float) $point[$key]) > $limit
            )
                return null;
        $coordinates[] = (float) $point['longitude'] . ',' . (float) $point['latitude'];
    }
    return $coordinates;
}

function route_mapping_response(?array $data): ?array
{
    $route = $data['routes'][0] ?? null;
    if (
        ($data['code'] ?? null) !== 'Ok' || !is_array($route)
        || ($route['distance'] ?? 0) < 1 || ($route['duration'] ?? 0) < 1
    )
        return null;
    $raw = $route['geometry']['coordinates'] ?? [];
    if (!is_array($raw) || count($raw) < 2)
        return null;
    // Keep bounded storage while retaining the endpoints of long road paths.
    if (count($raw) > 1000) {
        $selected = [];
        for ($i = 0; $i < 1000; $i++)
            $selected[] = $raw[(int) round($i * (count($raw) - 1) / 999)];
        $raw = $selected;
    }
    $points = array_map(static fn (array $xy): array => ['latitude' => $xy[1], 'longitude' => $xy[0]], $raw);
    $valid = transfer_input_geometry($points);
    return [
        'geometry' => json_encode($valid['points'], JSON_THROW_ON_ERROR),
        'distance_meters' => (int) round($route['distance']),
        'duration_seconds' => max(1, (int) round($route['duration']))
    ];
}

function route_mapping_cached(PDO $db, string $url, float $deadline): ?array
{
    $key = hash('sha256', $url);
    $read = function () use ($key, $db) {
        $s = $db->prepare('SELECT payload FROM route_map_cache WHERE cache_key=? AND expires_at>UTC_TIMESTAMP()');
        $s->execute([$key]);
        return $s->fetchColumn();
    };
    $saved = $read();
    if ($saved !== false)
        return json_decode($saved, true, 64, JSON_THROW_ON_ERROR);
    if (!str_starts_with($url, 'https://') || microtime(true) > $deadline)
        return null;
    // One request at a time and at most one/second across this installation.
    $lock = 'hospital-map-http';
    $s = $db->prepare('SELECT GET_LOCK(?,2)');
    $s->execute([$lock]);
    if ((int) $s->fetchColumn() !== 1)
        return null;
    try {
        $saved = $read();
        if ($saved !== false)
            return json_decode($saved, true, 64, JSON_THROW_ON_ERROR);
        $ch = curl_init($url);
        $body = '';
        curl_setopt_array(
            $ch,
            [
                CURLOPT_USERAGENT => 'HospitalSchoolProject/1.0 (fictional route planning)',
                CURLOPT_CONNECTTIMEOUT_MS => 2000,
                CURLOPT_TIMEOUT_MS => max(1, min(4000, (int) (1000 * ($deadline - microtime(true))))),
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > 2097152)
                        return 0;
                    $body .= $chunk;
                    return strlen($chunk);
                }
            ]
        );
        $started = microtime(true);
        $ok = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $wait = 1 - (microtime(true) - $started);
        if ($wait > 0)
            usleep((int) ($wait * 1000000));
        $data = $ok !== false && $status === 200 ? json_decode($body, true, 64) : null;
        if (!is_array($data))
            $data = null;
        $missing = $data === null || (isset($data['features']) && !$data['features'])
            || (isset($data['code']) && $data['code'] !== 'Ok');
        $ttl = $missing ? 300 : 2592000;
        $s = $db->prepare('INSERT INTO route_map_cache (cache_key,payload,expires_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),expires_at=VALUES(expires_at)');
        $s->execute([$key, json_encode($data, JSON_THROW_ON_ERROR), gmdate('Y-m-d H:i:s', time() + $ttl)]);
        return $data;
    } finally {
        $s = $db->prepare('SELECT RELEASE_LOCK(?)');
        $s->execute([$lock]);
    }
}
