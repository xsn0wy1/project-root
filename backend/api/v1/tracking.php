<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/php/bootstrap.php';

use Hospital\Http\ApiException;
use Hospital\Http\Request;
use Hospital\Http\Response;

$action = $_GET['action'] ?? '';
if (!is_string($action) || !in_array($action, ['control', 'history', 'snapshot'], true)) {
    throw new ApiException(404, 'NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_el_endpoint'));
}
$query = $_GET;
unset($query['action']);
if ($action === 'control') {
    Request::method('GET', 'POST');
    $user = auth_require_admin($db, $config);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        Response::json(tracking_status($db));
    } else {
        Hospital\Security\Csrf::verify();
        $body = Request::body(['command', 'version', 'speed']);
        $command = transfer_input_choice($body['command'] ?? null, ['start', 'pause', 'resume', 'stop', 'reset']);
        Response::json(tracking_control($db, $config, $command, $body['version'] ?? null, $body['speed'] ?? 1, (string) $user['id']));
    }
} else {
    Request::method('GET');
    $user = auth_require_user($db, $config, false);
    session_write_close();
    Response::json($action === 'history'
        ? tracking_history($db, (string) $user['id'], $query)
        : tracking_snapshot($db, (string) $user['id'], $query));
}
