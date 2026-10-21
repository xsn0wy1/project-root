<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/php/bootstrap.php';
require dirname(__DIR__, 2) . '/php/modules/ambulances.php';

use Hospital\Http\ApiException;
use Hospital\Http\Request;
use Hospital\Http\Response;

$action = $_GET['action'] ?? '';
if (!is_string($action) || !in_array($action, ['list', 'get', 'create', 'update', 'archive', 'restore'], true)) {
    throw new ApiException(404, 'NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_el_endpoint'));
}
$write = !in_array($action, ['list', 'get'], true);
Request::method($write ? 'POST' : 'GET');
if ($write) Hospital\Security\Csrf::verify();
$user = auth_require_user($db, $config, $write);
$actor = (string) $user['id'];

transfer_require_dispatch($db, $actor);
if (array_diff(array_keys($_GET), $action === 'get' ? ['action', 'id'] : ['action'])) {
    throw new ApiException(422, 'UNEXPECTED_FIELDS', Hospital\I18n::t('errors.filtro_no_permitido'));
}
$fields = ['plate', 'model', 'capacity', 'operational_status'];
switch ($action) {
    case 'list':
        Response::json(ambulance_list($db));
        break;
    case 'get':
        Response::json(ambulance_get($db, Request::id($_GET['id'] ?? null)));
        break;
    case 'create':
        Response::json(ambulance_save($db, $config, $actor, Request::body($fields)), 201);
        break;
    case 'update':
        $body = Request::body(array_merge($fields, ['id', 'version']));
        Response::json(ambulance_save($db, $config, $actor, $body, Request::id($body['id'] ?? null)));
        break;
    case 'archive':
    case 'restore':
        $body = Request::body(['id', 'version']);
        Response::json(ambulance_archive($db, $config, $actor, Request::id($body['id'] ?? null), transfer_input_version($body['version'] ?? null), $action === 'restore'));
        break;
}
