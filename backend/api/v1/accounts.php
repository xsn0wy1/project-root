<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/php/bootstrap.php';

use Hospital\Http\ApiException;
use Hospital\Http\Request;
use Hospital\Http\Response;
use Hospital\Security\Csrf;

$action = $_GET['action'] ?? '';
if (!is_string($action) || !in_array($action, ['approve', 'directory', 'disable', 'list', 'manage', 'reset-password'], true)) {
    throw new ApiException(404, 'NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_el_endpoint'));
}

$write = !in_array($action, ['directory', 'list'], true);
Request::method($write ? 'POST' : 'GET');
if ($write) Csrf::verify();
$actor = auth_require_admin($db, $config);

switch ($action) {
    case 'approve':


        $body = Request::body(['user_id', 'role']);
        $id = Request::id($body['user_id'] ?? null);
        $role = $body['role'] ?? 'staff';
        if (!is_string($role)) throw new Hospital\Http\ApiException(422, 'INVALID_ROLE', \Hospital\I18n::t("errors.el_rol_no_es_valido"));
        accounts_approve($db, $id, $role, (string) $actor['id'], $GLOBALS['request_id']);
        Response::json(['user_id' => $id, 'status' => 'active']);
        break;
    case 'directory':

        if (array_diff(array_keys($_GET), ['action'])) throw new Hospital\Http\ApiException(422,'UNEXPECTED_FIELDS',\Hospital\I18n::t("errors.filtro_no_permitido"));
        Response::json(accounts_listing($db));
        break;
    case 'disable':


        $body = Request::body(['user_id']);
        $id = Request::id($body['user_id'] ?? null);
        accounts_disable($db, $id, (string) $actor['id'], $GLOBALS['request_id']);
        Response::json(['user_id' => $id, 'status' => 'disabled']);
        break;
    case 'list':


        Response::json(users_pending($db));
        break;
    case 'manage':

        $body=Request::body(['user_id','version','action','roles'],['roles']);
        $action=$body['action']??null;if(!is_string($action)||!is_array($body['roles']??[]))throw new Hospital\Http\ApiException(422,'VALIDATION_FAILED',\Hospital\I18n::t("errors.accion_no_valida"));
        accounts_manage($db, Request::id($body['user_id']??null),transfer_input_version($body['version']??null),$action,$body['roles']??[],(string)$actor['id'],$GLOBALS['request_id']);
        Response::json(['saved'=>true]);
        break;
    case 'reset-password':

        $body=Request::body(['user_id','version','new_password']);
        password_change($db, $config, (string)$actor['id'],Request::id($body['user_id']??null),$body,true);
        Response::json(['changed'=>true]);
        break;
}
