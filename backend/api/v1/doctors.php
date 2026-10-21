<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/php/bootstrap.php';

use Hospital\Http\ApiException;
use Hospital\Http\Request;
use Hospital\Http\Response;
use Hospital\Security\Csrf;

$action = $_GET['action'] ?? '';
if (!is_string($action) || !in_array($action, ['archive', 'catalogs', 'create', 'get', 'list', 'restore', 'update'], true)) {
    throw new ApiException(404, 'NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_el_endpoint'));
}
$write = !in_array($action, ['list', 'get', 'catalogs'], true);
Request::method(($write) ? 'POST' : 'GET');
if ($write) Csrf::verify();
$user = auth_require_user($db, $config, $write);
$actor = (string) $user['id'];
patients_access($db, $actor);
if (array_diff(array_keys($_GET), $action === 'get' ? ['action', 'id'] : ['action'])) {
    throw new ApiException(422, 'UNEXPECTED_FIELDS', Hospital\I18n::t('errors.filtro_no_permitido'));
}

switch ($action) {
    case 'archive':
    case 'restore':
        $body=Request::body(['id','version']);
        Response::json(doctors_archive($db, $config, $actor,Request::id($body['id']??null),patient_input_version($body['version']??null),$action === 'restore'));
        break;
    case 'catalogs':
        Response::json(doctors_catalogs($db, $actor));
        break;
    case 'create':
        $body=Request::body(DOCTOR_INPUT_FIELDS);
        Response::json(doctors_save($db, $config, $actor,$body),201);
        break;
    case 'get':
        Response::json(doctors_get($db, $actor,Request::id($_GET['id']??null)));
        break;
    case 'list':
        Response::json(doctors_listing($db, $actor));
        break;
    case 'update':
        $body=Request::body(array_merge(DOCTOR_INPUT_FIELDS,['id','version']));
        Response::json(doctors_save($db, $config, $actor,$body,Request::id($body['id']??null)));
        break;
}
