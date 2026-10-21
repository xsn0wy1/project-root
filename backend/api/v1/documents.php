<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/php/bootstrap.php';

use Hospital\Http\ApiException;
use Hospital\Http\FileResponse;
use Hospital\Http\Request;
use Hospital\Http\Response;
use Hospital\Security\Csrf;
use Hospital\Security\RateLimiter;

$action = $_GET['action'] ?? '';
if (!is_string($action) || !in_array($action, ['list', 'get', 'create-token', 'create', 'update', 'replace-file', 'archive', 'restore', 'bulk-archive', 'content', 'patient-link', 'public-file'], true)) {
    throw new ApiException(404, 'NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_el_endpoint'));
}
$query = $_GET;
unset($query['action']);
$binary = in_array($action, ['content', 'public-file'], true);
// A patient URL is a bearer credential; never forward it as a referrer.
if ($action === 'public-file') header('Referrer-Policy: no-referrer');
$write = !in_array($action, ['list', 'get', 'create-token', 'content', 'public-file'], true);
Request::method(...($binary ? ['GET', 'HEAD'] : [$write ? 'POST' : 'GET']));
if ($write) Csrf::verify();
if ($action !== 'public-file') {
    $user = auth_require_user($db, $config, $write);
    $actor = (string) $user['id'];
}

switch ($action) {
    case 'list':
        $result = documents_listing($db, $actor, $query);
        Response::json($result['items'], 200, ['page' => $result['page'], 'page_size' => $result['page_size'], 'total' => $result['total']]);
        break;
    case 'get':
        if (array_diff(array_keys($query), ['id'])) throw new ApiException(422, 'UNEXPECTED_FIELDS', Hospital\I18n::t('errors.parametro_no_permitido'));
        Response::json(documents_get($db, $actor, Request::id($query['id'] ?? null)));
        break;
    case 'create-token':
        Response::json(['create_token' => bin2hex(random_bytes(32))]);
        break;
    case 'create':
    case 'replace-file':
        $fields = $action === 'create' ? ['create_token'] : ['id', 'version'];
        $body = Request::body(array_merge(['title', 'description', 'patient_id', 'category_id', 'document_date'], $fields), [], 11 * 1024 * 1024);
        if (array_keys($_FILES) !== ['file'] || !is_array($_FILES['file'])) throw new ApiException(422, 'INVALID_UPLOAD', Hospital\I18n::t('errors.se_requiere_un_archivo'));
        (new RateLimiter($db, $config['app_key']))->consume('document-upload:' . $actor, 60, 3600);
        $result = $action === 'create'
            ? documents_create($db, $config, $actor, $body, $_FILES['file'])
            : documents_update($db, $config, $actor, Request::id($body['id'] ?? null), $body, $_FILES['file']);
        Response::json($result, $action === 'create' ? 201 : 200);
        break;
    case 'update':
        $body = Request::body(['id', 'version', 'title', 'description', 'patient_id', 'category_id', 'document_date']);
        if ($_FILES) throw new ApiException(422, 'UNEXPECTED_FIELDS', Hospital\I18n::t('errors.usa_el_reemplazo_de_archivo'));
        Response::json(documents_update($db, $config, $actor, Request::id($body['id'] ?? null), $body));
        break;
    case 'archive':
    case 'restore':
        $body = Request::body(['id', 'version']);
        Response::json(documents_archive($db, $actor, Request::id($body['id'] ?? null), $body['version'] ?? null, $action === 'restore'));
        break;
    case 'bulk-archive':
        $body = Request::body(['documents'], ['documents']);
        $items = $body['documents'] ?? null;
        if (!is_array($items) || array_keys($items) !== range(0, count($items) - 1) || count($items) > 100) throw new ApiException(422, 'VALIDATION_FAILED', Hospital\I18n::t('errors.selecciona_entre_1_y_100_documentos'));
        // Validate the whole selection before mutating; valid items then succeed independently.
        $seen = []; $targets = [];
        foreach ($items as $item) {
            if (!is_array($item) || array_diff(array_keys($item), ['id', 'version'])) throw new ApiException(422, 'VALIDATION_FAILED', Hospital\I18n::t('errors.seleccion_invalida'));
            $id = Request::id($item['id'] ?? null);
            if (isset($seen[$id])) throw new ApiException(422, 'VALIDATION_FAILED', Hospital\I18n::t('errors.seleccion_duplicada'));
            $seen[$id] = true; $targets[] = ['id' => $id, 'version' => $item['version'] ?? null];
        }
        $results = [];
        foreach ($targets as $item) {
            try {
                documents_archive($db, $actor, $item['id'], $item['version']);
                $results[] = ['id' => $item['id'], 'success' => true];
            } catch (ApiException $error) {
                $results[] = ['id' => $item['id'], 'success' => false, 'error' => ['code' => $error->errorCode, 'status' => $error->status, 'message' => $error->getMessage()]];
            }
        }
        Response::json($results);
        break;
    case 'patient-link':
        $body = Request::body(['id', 'version', 'file_version', 'action', 'token']);
        $linkAction = $body['action'] ?? '';
        if (!in_array($linkAction, ['create', 'revoke'], true) || ($linkAction === 'revoke' && (!is_string($body['token'] ?? null) || !preg_match('/^[a-f0-9]{64}$/', $body['token']))) || ($linkAction === 'create' && array_key_exists('token', $body))) {
            throw new ApiException(422, 'VALIDATION_FAILED', Hospital\I18n::t('errors.accion_no_valida'));
        }
        Response::json(documents_patient_link($db, $config, $actor, Request::id($body['id'] ?? null), $body['version'] ?? null, Request::id($body['file_version'] ?? null), $linkAction === 'revoke' ? $body['token'] : null));
        break;
    case 'content':
    case 'public-file':
        $allowed = $action === 'content' ? ['id', 'version', 'download'] : ['token', 'download'];
        if (array_diff(array_keys($query), $allowed)) throw new ApiException(422, 'UNEXPECTED_FIELDS', Hospital\I18n::t('errors.parametro_no_permitido'));
        $download = $query['download'] ?? '0';
        if (!in_array($download, ['0', '1'], true) || ($action === 'public-file' && !is_string($query['token'] ?? ''))) throw new ApiException(422, 'VALIDATION_FAILED', Hospital\I18n::t('errors.parametro_invalido'));
        $file = $action === 'content'
            ? documents_content($db, $config, $actor, Request::id($query['id'] ?? null), isset($query['version']) ? Request::id($query['version']) : null, $download === '1')
            : documents_patient_content($db, $config, $query['token'] ?? '');
        FileResponse::send($file, $download === '1');
        break;
}
