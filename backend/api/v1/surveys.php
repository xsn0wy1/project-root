<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/php/bootstrap.php';

use Hospital\Http\ApiException;
use Hospital\Http\Request;
use Hospital\Http\Response;
use Hospital\Security\Csrf;
use Hospital\Security\RateLimiter;

$action = $_GET['action'] ?? '';
if (!is_string($action) || !in_array($action, ['answers', 'create', 'get', 'link-update', 'links', 'list', 'transition', 'update', 'public-get', 'public-submit'], true)) {
    throw new ApiException(404, 'NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_el_endpoint'));
}
$query = $_GET;
unset($query['action']);
$public = in_array($action, ['public-get', 'public-submit'], true);
$write = in_array($action, ['create', 'link-update', 'transition', 'update'], true);
Request::method($write || $public ? 'POST' : 'GET');
if ($write || $action === 'public-submit') Csrf::verify();
if (!$public) {
    $user = auth_require_user($db, $config, $write);
    $actor = (string) $user['id'];
}

switch ($action) {
    case 'get':
        Response::json(surveys_get($db, $actor, Request::id($query['id'] ?? null)));
        break;
    case 'list':
    case 'answers':
        $page = survey_input_integer($query['page'] ?? 1, 1, 100000);
        $size = survey_input_integer($query['page_size'] ?? ($action === 'list' ? 100 : 3), 1, $action === 'list' ? 100 : 50);
        [$rows, $total] = $action === 'list'
            ? surveys_listing($db, $actor, $page, $size, ($query['archived'] ?? '0') === '1')
            : surveys_entries($db, $actor, Request::id($query['id'] ?? null), survey_input_text($query['question_id'] ?? '', 48, true), survey_input_text($query['q'] ?? '', 150), $page, $size);
        Response::json($rows, 200, ['page' => $page, 'page_size' => $size, 'total' => $total]);
        break;
    case 'create':
    case 'update':
        $fields = $action === 'create' ? SURVEY_FIELDS : array_merge(SURVEY_FIELDS, ['id', 'version']);
        $body = Request::body($fields, ['config', 'items'], 262144);
        Response::json(surveys_save($db, $actor, $body, $action === 'update' ? Request::id($body['id'] ?? null) : null), $action === 'create' ? 201 : 200);
        break;
    case 'transition':
        $body = Request::body(['id', 'version', 'action']);
        $transition = survey_input_choice($body['action'] ?? null, ['publish', 'close', 'reopen', 'archive', 'restore']);
        Response::json(surveys_transition($db, $config, $actor, Request::id($body['id'] ?? null), $body['version'] ?? null, $transition));
        break;
    case 'links':
        Response::json(surveys_links($db, $config, $actor, Request::id($query['id'] ?? null)));
        break;
    case 'link-update':
        $body = Request::body(['id', 'version', 'link_id']);
        Response::json(surveys_change_link($db, $config, $actor, Request::id($body['id'] ?? null), $body['version'] ?? null, isset($body['link_id']) ? Request::id($body['link_id']) : null));
        break;
    case 'public-get':
    case 'public-submit':
        $body = $action === 'public-get' ? Request::body(['token']) : Request::body(['token', 'version', 'submit_token', 'participant_name', 'answers'], ['answers'], 262144);
        $token = survey_input_text($body['token'] ?? '', 64, true);
        $bucket = ($action === 'public-get' ? 'survey-read:' : 'survey-submit:') . ($_SERVER['REMOTE_ADDR'] ?? '');
        (new RateLimiter($db, $config['app_key']))->consume($bucket, $action === 'public-get' ? 180 : 120, $action === 'public-get' ? 600 : 3600);
        if (surveys_audience($db, $token) === 'staff') auth_require_user($db, $config, false);
        Response::json($action === 'public-get' ? surveys_public_get($db, $config, $token) : surveys_submit($db, $config, $token, $body), $action === 'public-get' ? 200 : 201);
        break;
}
