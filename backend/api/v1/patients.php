<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/php/bootstrap.php';

use Hospital\Http\ApiException;
use Hospital\Http\Request;
use Hospital\Http\Response;
use Hospital\Security\Csrf;

$action = $_GET['action'] ?? '';
if (
    !is_string($action)
    || !in_array($action, ['archive', 'catalogs', 'create', 'get', 'list', 'restore', 'update'], true)
) {
    throw new ApiException(404, 'NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_el_endpoint'));
}
$write = !in_array($action, ['list', 'get', 'catalogs'], true);
Request::method(($write || $action === 'list') ? 'POST' : 'GET');
if ($write || $action === 'list')
    Csrf::verify();
$user = auth_require_user($db, $config, $write);
$actor = (string) $user['id'];
patients_access($db, $actor);
if (array_diff(array_keys($_GET), $action === 'get' ? ['action', 'id'] : ['action'])) {
    throw new ApiException(422, 'UNEXPECTED_FIELDS', Hospital\I18n::t('errors.filtro_no_permitido'));
}

switch ($action) {
    case 'archive':
    case 'restore':
        $body = Request::body(['id', 'version']);
        Response::json(patients_archive(
            $db,
            $config,
            $actor,
            Request::id($body['id'] ?? null),
            patient_input_version($body['version'] ?? null),
            $action === 'restore'
        ));
        break;
    case 'catalogs':
        Response::json(patients_catalogs($db, $actor));
        break;
    case 'create':
        $body = Request::body(array_merge(PATIENT_INPUT_FIELDS, ['create_token']));
        Response::json(patients_create($db, $config, $actor, $body), 201);
        break;
    case 'get':
        Response::json(patients_get($db, $config, $actor, Request::id($_GET['id'] ?? null)));
        break;
    case 'list':
        $body = Request::body(['q', 'care_status', 'state', 'page', 'page_size']);
        $result = patients_listing($db, $config, $actor, $body);
        Response::json($result['data'], 200, $result['meta']);
        break;
    case 'update':
        $body = Request::body(array_merge(PATIENT_INPUT_FIELDS, ['id', 'version']));
        Response::json(patients_update($db, $config, $actor, Request::id($body['id'] ?? null), $body));
        break;
}
