<?php
declare(strict_types=1);

use Hospital\Http\ApiException;
use Hospital\Http\Request;

function tracking_position(array $row): array
{
    return [
        'id' => (string)$row['id'],
        'transfer_id' => (string)$row['transfer_id'],
        'run_id' => $row['run_id'] === null ? null : (string)$row['run_id'],
        'generation' => (int)$row['generation'],
        'progress' => (float)$row['progress'],
        'latitude' => (float)$row['latitude'],
        'longitude' => (float)$row['longitude'],
        'recorded_at' => users_date($row['recorded_at']),
        'source' => 'simulated'
    ];
}

function tracking_latest(PDO $db, string $id): ?array
{
    $preparedStatement = $db->prepare('SELECT p.* FROM transfer_latest_positions l JOIN transfer_positions p ON p.id=l.position_id WHERE l.transfer_id=?');
    $preparedStatement->execute([$id]);
    $row = $preparedStatement->fetch();
    return $row ? tracking_position($row) : null;
}

function tracking_sample(PDO $db, string $id, array $points, float $progress, ?string $run, int $generation): void
{
    if (count($points) < 2) {
        $preparedStatement = $db->prepare('DELETE FROM transfer_latest_positions WHERE transfer_id=?');
        $preparedStatement->execute([$id]);
        return;
    }
    $progress = max(0, min(1, $progress));
    $lengths = [];
    $total = 0;
    for ($pointIndex = 1;$pointIndex < count($points);$pointIndex++) {
        $length = transfer_input_distance($points[$pointIndex - 1], $points[$pointIndex]);
        $lengths[] = $length;
        $total += $length;
    }
    $remaining = $total * $progress;
    $point = $points[count($points) - 1];
    foreach ($lengths as $pointIndex => $length) {
        if ($remaining <= $length) {
            $fraction = $length > 0 ? $remaining / $length : 0;
            $point = [
                'latitude' => $points[$pointIndex]['latitude'] + ($points[$pointIndex + 1]['latitude'] - $points[$pointIndex]['latitude']) * $fraction,
                'longitude' => $points[$pointIndex]['longitude'] + ($points[$pointIndex + 1]['longitude'] - $points[$pointIndex]['longitude']) * $fraction
            ];
            break;
        }$remaining -= $length;
    }
    $preparedStatement = $db->prepare('INSERT INTO transfer_positions (transfer_id,run_id,generation,progress,latitude,longitude) VALUES (?,?,?,?,?,?)');
    $preparedStatement->execute([$id, $run, $generation, $progress, $point['latitude'], $point['longitude']]);
    $position = $db->lastInsertId();
    $preparedStatement = $db->prepare('INSERT INTO transfer_latest_positions (transfer_id,position_id) VALUES (?,?) ON DUPLICATE KEY UPDATE position_id=VALUES(position_id)');
    $preparedStatement->execute([$id, $position]);
}

function tracking_status(PDO $db): array
{
    $row = $db->query('SELECT s.version AS state_version,r.* FROM simulation_state s LEFT JOIN simulation_runs r ON r.id=s.current_run_id WHERE s.id=1')->fetch();
    $heartbeat = $row['heartbeat_at'] === null ? null : strtotime($row['heartbeat_at'] . ' UTC');
    return [
        'version' => (int)$row['state_version'],
        'run_id' => $row['id'] === null ? null : (string)$row['id'],
        'status' => $row['status'] ?? 'stopped',
        'speed' => $row['speed'] === null ? 1 : (float)$row['speed'],
        'generation' => $row['generation'] === null ? 1 : (int)$row['generation'],
        'heartbeat_at' => users_date($row['heartbeat_at']),
        'stale' => $heartbeat === null || time() - $heartbeat > 15,
        'source' => 'simulated'
    ];
}

function tracking_control(PDO $db, array $config, string $command, $version, $speed = 1): array
{
    if (PHP_SAPI !== 'cli') {
        throw new ApiException(403, 'FORBIDDEN', Hospital\I18n::t('errors.operacion_no_permitida'));
    }
    transfer_input_choice($command, ['start', 'pause', 'resume', 'stop', 'reset']);
    if ((!is_int($speed) && !is_float($speed)) || !is_finite((float)$speed) || $speed < 0.1 || $speed > 100) {
        throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.velocidad_de_simulacion_no_valida"));
    }
    transfer_serialized(
        $db,
        $config,
        function () use ($command, $version, $speed, $db) {
            $db->beginTransaction();
            try {
                $state = $db->query('SELECT * FROM simulation_state WHERE id=1 FOR UPDATE')->fetch();
                if ((int)$state['version'] !== \Hospital\Http\Request::version($version)) {
                    throw new ApiException(409, 'VERSION_CONFLICT', \Hospital\I18n::t("errors.la_simulacion_cambio"));
                }
                $run = null;
                if ($state['current_run_id'] !== null) {
                    $preparedStatement = $db->prepare('SELECT * FROM simulation_runs WHERE id=? FOR UPDATE');
                    $preparedStatement->execute([$state['current_run_id']]);
                    $run = $preparedStatement->fetch();
                }
                if ($command === 'start') {
                    if ($run && $run['status'] !== 'stopped') {
                        throw new ApiException(409, 'SIMULATION_STATE', \Hospital\I18n::t("errors.la_simulacion_ya_esta_iniciada"));
                    }
                    $preparedStatement = $db->prepare("INSERT INTO simulation_runs (speed) VALUES (?)");
                    $preparedStatement->execute([$speed]);
                    $id = $db->lastInsertId();
                    $preparedStatement = $db->prepare('UPDATE simulation_state SET current_run_id=?,version=version+1 WHERE id=1');
                    $preparedStatement->execute([$id]);
                } else {
                    if (
                        !$run
                        || $run['status'] === 'stopped'
                        || ($command === 'pause'
                        && $run['status'] !== 'running')
                        || ($command === 'resume'
                        && $run['status'] !== 'paused')
                    ) {
                        throw new ApiException(409, 'SIMULATION_STATE', \Hospital\I18n::t("errors.el_estado_de_la_simulacion_no_permite_esa_accion"));
                    }
                    $status = ['pause' => 'paused', 'resume' => 'running', 'stop' => 'stopped', 'reset' => $run['status']][$command];
                    $preparedStatement = $db->prepare('UPDATE simulation_runs SET status=?,last_tick_at=NULL,version=version+1,generation=generation+? WHERE id=?');
                    $preparedStatement->execute([$status, $command === 'reset' ? 1 : 0, $run['id']]);
                    if ($command === 'reset') {
                        $rows = $db->query("SELECT id,geometry FROM transfers WHERE status='in_progress' ORDER BY id FOR UPDATE")->fetchAll();
                        foreach ($rows as $row) {
                            tracking_sample(
                                $db,
                                (string)$row['id'],
                                json_decode($row['geometry'], true),
                                0,
                                (string)$run['id'],
                                (int)$run['generation'] + 1
                            );
                        }
                    }
                    $db->exec('UPDATE simulation_state SET version=version+1 WHERE id=1');
                }
                transfer_audit($db, null, 'simulation.' . $command, null, 'simulation');
                $db->commit();
            } catch (\Throwable $caughtError) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $caughtError;
            }
        }
    );
    return tracking_status($db);
}

function tracking_tick(PDO $db, array $config): int
{
    return transfer_serialized(
        $db,
        $config,
        function () use ($db) {
            $db->beginTransaction();
            try {
                $preparedStatement = $db->query('SELECT r.*,UTC_TIMESTAMP() AS now_utc FROM simulation_state s JOIN simulation_runs r ON r.id=s.current_run_id WHERE s.id=1 FOR UPDATE');
                $run = $preparedStatement->fetch();
                if (!$run || $run['status'] === 'stopped') {
                    $db->commit();
                    return 0;
                }
                $seconds = $run['last_tick_at'] === null ? 0 : max(0, min(5, strtotime($run['now_utc'] . ' UTC') - strtotime($run['last_tick_at'] . ' UTC')));
                $preparedStatement = $db->prepare('UPDATE simulation_runs SET heartbeat_at=UTC_TIMESTAMP(),last_tick_at=UTC_TIMESTAMP() WHERE id=?');
                $preparedStatement->execute([$run['id']]);
                $count = 0;
                if ($run['status'] === 'running') {
                    $rows = $db->query("SELECT t.*,p.progress FROM transfers t LEFT JOIN transfer_latest_positions l ON l.transfer_id=t.id LEFT JOIN transfer_positions p ON p.id=l.position_id WHERE t.status='in_progress' ORDER BY t.id FOR UPDATE")->fetchAll();
                    foreach ($rows as $row) {
                        if ($row['duration_seconds'] === null || (int)$row['duration_seconds'] <= 0) {
                            continue;
                        }
                        $progress = min(1, (float)($row['progress'] ?? 0) + $seconds * (float)$run['speed'] / (int)$row['duration_seconds']);
                        tracking_sample(
                            $db,
                            (string)$row['id'],
                            json_decode($row['geometry'], true),
                            $progress,
                            (string)$run['id'],
                            (int)$run['generation']
                        );
                        $count++;
                        if ($progress >= 1) {
                            $preparedStatement = $db->prepare("UPDATE transfers SET status='arrived',arrived_at=UTC_TIMESTAMP(),version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?");
                            $preparedStatement->execute([$row['id']]);
                            transfers_event($db, (string)$row['id'], null, 'simulation_arrived', 'in_progress', 'arrived');
                            transfer_audit($db, null, 'transfer.simulation_arrived', (string)$row['id']);
                        }
                    }
                }
                $db->commit();
                return $count;
            } catch (\Throwable $caughtError) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $caughtError;
            }
        }
    );
}

function tracking_snapshot(PDO $db, string $actor, array $query): array
{
    if (array_diff(array_keys($query), ['after_id', 'transfer_id'])) {
        throw new ApiException(422, 'UNEXPECTED_FIELDS', \Hospital\I18n::t("errors.filtro_no_permitido"));
    }
    $args = [];
    $scope = transfer_scope($db, $actor, $args);
    $where = [$scope];
    if (isset($query['transfer_id'])) {
        $where[] = 't.id=?';
        $args[] = Request::id($query['transfer_id']);
        transfers_row($db, $actor, (string)$args[count($args) - 1]);
    } else {
        $where[] = "t.status IN ('scheduled','in_progress','arrived')";
    }
    if (isset($query['after_id'])) {
        $where[] = 't.id>?';
        $args[] = Request::id($query['after_id']);
    }
    $preparedStatement = $db->prepare('SELECT t.* FROM transfers t WHERE ' . implode(' AND ', $where) . ' ORDER BY t.id LIMIT 101');
    $preparedStatement->execute($args);
    $rows = $preparedStatement->fetchAll();
    $more = count($rows) > 100;
    $rows = array_slice($rows, 0, 100);
    $data = [];
    foreach ($rows as $row) {
        $item = transfers_payload($db, $row);
        unset($item['crew']);
        $item['geometry'] = json_decode($row['geometry'], true);
        $item['position'] = tracking_latest($db, (string)$row['id']);
        $data[] = $item;
    }
    return [
        'transfers' => $data,
        'next_cursor' => $more ? (string)$rows[count($rows) - 1]['id'] : null,
        'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
        'simulation' => tracking_status($db)
    ];
}

function tracking_prune(PDO $db): int
{
    return $db->exec('DELETE FROM transfer_positions WHERE recorded_at<UTC_TIMESTAMP()-INTERVAL 7 DAY AND id NOT IN (SELECT position_id FROM transfer_latest_positions) LIMIT 1000');
}
