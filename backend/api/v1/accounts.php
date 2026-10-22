<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/php/bootstrap.php';

use Hospital\Http\ApiException;
use Hospital\Http\Request;
use Hospital\Http\Response;
use Hospital\Security\Csrf;

$action = $_GET['action'] ?? '';
if (!is_string($action) || !in_array($action, ['directory', 'manage', 'reset-password'], true)) {
    throw new ApiException(404, 'NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_el_endpoint'));
}

$write = $action !== 'directory';
Request::method($write ? 'POST' : 'GET');
if ($write) Csrf::verify();
$actor = auth_require_admin($db, $config);

switch ($action) {
    case 'directory':

        if (array_diff(array_keys($_GET), ['action'])) throw new Hospital\Http\ApiException(422,'UNEXPECTED_FIELDS',\Hospital\I18n::t("errors.filtro_no_permitido"));
        Response::json(accounts_listing($db));
        break;
    case 'manage':

        $body=Request::body(['user_id','version','action','roles'],['roles']);
        $action=$body['action']??null;if(!is_string($action)||!is_array($body['roles']??[]))throw new Hospital\Http\ApiException(422,'VALIDATION_FAILED',\Hospital\I18n::t("errors.accion_no_valida"));
        accounts_manage($db, Request::id($body['user_id']??null),\Hospital\Http\Request::version($body['version']??null),$action,$body['roles']??[],(string)$actor['id'],$GLOBALS['request_id']);
        Response::json(['saved'=>true]);
        break;
    case 'reset-password':

        $body=Request::body(['user_id','version','new_password']);
        password_change($db, $config, (string)$actor['id'],Request::id($body['user_id']??null),$body,true);
        Response::json(['changed'=>true]);
        break;
}
