<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/php/bootstrap.php';

use Hospital\Http\ApiException;
use Hospital\Http\Request;
use Hospital\Http\Response;
use Hospital\Security\Csrf;

$action = $_GET['action'] ?? '';
if (
    !is_string($action) ||
    !in_array($action, ['archive', 'create', 'get', 'list', 'restore', 'update'], true)
) {
    throw new ApiException(404, 'NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_el_endpoint'));
}
$write = !in_array($action, ['list', 'get', 'catalogs'], true);
Request::method(($write) ? 'POST' : 'GET');
if ($write)
    Csrf::verify();
$user = auth_require_user($db, $config, $write);
$actor = (string) $user['id'];
destinations_access($db, $config, $actor);
if (array_diff(array_keys($_GET), $action === 'get' ? ['action', 'id'] : ['action'])) {
    throw new ApiException(422, 'UNEXPECTED_FIELDS', Hospital\I18n::t('errors.filtro_no_permitido'));
}

switch ($action) {
    case 'archive':
    case 'restore':
        $body = Request::body(['id', 'version']);
        Response::json(destinations_archive(
            $db,
            $config,
            $actor,
            Request::id($body['id'] ?? null),
            \Hospital\Http\Request::version($body['version'] ?? null),
            $action === 'restore'
        ));
        break;
    case 'create':
        $body = Request::body(DESTINATIONS_FIELDS);
        Response::json(destinations_save($db, $config, $actor, $body), 201);
        break;
    case 'get':
        Response::json(destinations_get($db, $config, $actor, Request::id($_GET['id'] ?? null)));
        break;
    case 'list':
        Response::json(destinations_listing($db, $config, $actor));
        break;
    case 'update':
        $body = Request::body(array_merge(DESTINATIONS_FIELDS, ['id', 'version']));
        Response::json(destinations_save(
            $db, $config, $actor, $body, Request::id($body['id'] ?? null)
        ));
        break;
}
