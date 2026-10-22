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
    || !in_array(
        $action,
        [
            'change-password',
            'csrf',
            'heartbeat',
            'login',
            'logout',
            'me',
            'register',
            'reset-password',
            'reset-request',
            'revoke-session',
            'sessions'
        ],
        true
    )
) {
    throw new ApiException(404, 'NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_el_endpoint'));
}

$write = !in_array($action, ['csrf', 'me', 'sessions'], true);
Request::method($write ? 'POST' : 'GET');
if ($write)
    Csrf::verify();

switch ($action) {
    case 'change-password':
        $user = auth_require_user($db, $config);
        $body = Request::body(['current_password', 'new_password']);
        password_change($db, $config, (string)$user['id'], (string)$user['id'], $body);
        auth_logout($db, $config);
        Response::json(['changed' => true, 'sign_in_required' => true]);
        break;
    case 'csrf':

        Response::json(['csrf_token' => Csrf::token()]);
        break;
    case 'heartbeat':


        Response::json(auth_payload($db, $config, auth_require_user($db, $config)));
        break;
    case 'login':


        Response::json(auth_login($db, $config, Request::body(['username', 'password'])));
        break;
    case 'logout':


        auth_logout($db, $config);
        Response::json(['logged_out' => true]);
        break;
    case 'me':

        Response::json(auth_payload($db, $config, auth_require_user($db, $config, false)));
        break;
    case 'register':


        Response::json(auth_register($db, $config, Request::body(['username', 'password', 'email', 'national_id'])), 201);
        break;
    case 'reset-password':


        $body = Request::body(['token', 'new_password']);
        password_reset_with_token($db, $config, $body);
        auth_clear_session($config);
        Response::json(['changed' => true, 'sign_in_required' => true]);
        break;
    case 'reset-request':
        $body = Request::body(['email']);
        $delivery = null;
        $result = password_request_reset($db, $config, $body, null, static function (callable $send) use (&$delivery): void {
            $delivery = $send;
        });
        Response::finish($result, 202);
        try {
            if ($delivery !== null)
                $delivery();
        } catch (Throwable $error) {
            error_log('Hospital password recovery dispatch failed.');
        }
        break;
    case 'revoke-session':


        $body = Request::body(['session_id']);
        Response::json(auth_revoke_session($db, $config, Request::id($body['session_id'] ?? null)));
        break;
    case 'sessions':

        Response::json(auth_sessions($db, $config));
        break;
}
