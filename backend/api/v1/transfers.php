<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/php/bootstrap.php';

use Hospital\Http\ApiException;
use Hospital\Http\Request;
use Hospital\Http\Response;
use Hospital\Security\Csrf;

$action = $_GET['action'] ?? '';
if (!is_string($action) || !in_array($action, ['catalogs', 'create-token', 'create', 'draft', 'get', 'list', 'patients', 'status', 'update'], true)) {
    throw new ApiException(404, 'NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_el_endpoint'));
}
$query = $_GET;
unset($query['action']);
$write = in_array($action, ['create', 'update', 'status'], true);
$post = $write || in_array($action, ['list', 'patients'], true);
Request::method(...($action === 'draft' ? ['GET', 'POST'] : [$post ? 'POST' : 'GET']));
if ($post) Csrf::verify();
$user = auth_require_user($db, $config, $write);
$actor = (string) $user['id'];

switch ($action) {
    case 'catalogs':
    case 'create-token':
        transfer_require_dispatch($db, $actor);
        Response::json($action === 'catalogs' ? transfer_catalog_selection($db) : ['create_token' => bin2hex(random_bytes(32))]);
        break;
    case 'patients':
        transfer_require_dispatch($db, $actor);
        if ($query) throw new ApiException(422, 'UNEXPECTED_FIELDS', Hospital\I18n::t('errors.parametro_no_permitido'));
        $body = Request::body(['q']);
        Response::json(transfer_catalog_patients($db, $body['q'] ?? ''));
        break;
    case 'get':
        if (array_diff(array_keys($query), ['id'])) throw new ApiException(422, 'UNEXPECTED_FIELDS', Hospital\I18n::t('errors.parametro_no_permitido'));
        Response::json(transfers_get($db, $actor, Request::id($query['id'] ?? null)));
        break;
    case 'list':
        $body = Request::body(['page', 'page_size', 'state', 'type', 'sort', 'q']);
        $result = transfers_listing($db, $actor, $body);
        Response::json($result['data'], 200, $result['meta']);
        break;
    case 'create':
        $body = Request::body(array_merge(TRANSFER_FIELDS, ['create_token', 'draft_version']));
        Response::json(transfers_create($db, $config, $actor, $body), 201);
        break;
    case 'update':
        $body = Request::body(array_merge(TRANSFER_FIELDS, ['id', 'version', 'refresh_route']));
        Response::json(transfers_update($db, $config, $actor, Request::id($body['id'] ?? null), $body));
        break;
    case 'status':
        $body = Request::body(['id', 'version', 'status']);
        $status = transfer_input_choice($body['status'] ?? null, ['in_progress', 'arrived', 'completed', 'cancelled']);
        Response::json(transfers_change_status($db, $config, $actor, Request::id($body['id'] ?? null), $body['version'] ?? null, $status));
        break;
    case 'draft':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            Response::json(transfer_draft_get($db, $actor));
        } else {
            Csrf::verify();
            $body = Request::body(['version', 'payload', 'clear'], ['payload']);
            if (isset($body['clear']) && !is_bool($body['clear'])) throw new ApiException(422, 'VALIDATION_FAILED', Hospital\I18n::t('errors.opcion_no_valida'));
            Response::json(transfer_draft_save($db, $config, $actor, $body, $body['clear'] ?? false));
        }
        break;
}
