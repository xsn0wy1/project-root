<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/php/bootstrap.php';

use Hospital\Http\ApiException;
use Hospital\Http\Request;
use Hospital\Http\Response;
use Hospital\Security\Csrf;

$action = $_GET['action'] ?? '';
if (!is_string($action) || !in_array($action, ['diagnostics', 'get', 'notifications-read', 'notifications', 'photo-delete', 'photo', 'preferences-update', 'profile-update'], true)) {
    throw new ApiException(404, 'NOT_FOUND', Hospital\I18n::t('errors.no_se_encontro_el_endpoint'));
}

switch ($action) {
    case 'diagnostics':
        Request::method('POST'); Csrf::verify();
        $user = auth_require_user($db, $config, false); $body = Request::body(['load_ms']);
        (new Hospital\Security\RateLimiter($db, $config['app_key']))->consume('diagnostics:' . $user['id'], 60, 3600);
        settings_diagnostics($db, (string) $user['id'], $body['load_ms'] ?? null);
        Response::json(['recorded' => true]);
        break;
    case 'get':
        Request::method('GET');
        Response::json(auth_payload($db, $config, auth_require_user($db, $config, false)));
        break;
    case 'notifications-read':
        Request::method('POST'); Csrf::verify();
        $user = auth_require_user($db, $config); $body = Request::body(['through_id']);
        settings_read_notifications($db, (string) $user['id'], Request::id($body['through_id'] ?? null));
        Response::json(['read' => true]);
        break;
    case 'notifications':
        Request::method('GET');
        $user = auth_require_user($db, $config, false);
        Response::json(settings_notifications($db, (string) $user['id']));
        break;
    case 'photo-delete':
        Request::method('POST'); Csrf::verify();
        $user = auth_require_user($db, $config); $body = Request::body(['version']);
        avatar_replace($db, $config, (string) $user['id'], $body['version'] ?? null, null);
        Response::json(auth_payload($db, $config, auth_require_user($db, $config, false)));
        break;
    case 'photo':
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            $user = auth_require_user($db, $config, false);
            $target = isset($_GET['user_id']) ? Request::id($_GET['user_id']) : (string) $user['id'];
            $file = avatar_content($db, $config, (string) $user['id'], $target);
            session_write_close();
            header('Content-Type: image/png'); header('Content-Length: ' . $file['size']);
            header('Content-Disposition: inline; filename="avatar.png"'); readfile($file['path']);
        } elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            Csrf::verify(); $user = auth_require_user($db, $config);
            $body = Request::body(['version'], [], 6 * 1024 * 1024);
            if (array_keys($_FILES) !== ['photo'] || !is_array($_FILES['photo'])) throw new Hospital\Http\ApiException(422, 'INVALID_UPLOAD', \Hospital\I18n::t("errors.se_requiere_una_imagen"));
            avatar_replace($db, $config, (string) $user['id'], $body['version'] ?? null, $_FILES['photo']);
            Response::json(auth_payload($db, $config, auth_require_user($db, $config, false)));
        } else {
            header('Allow: GET, POST'); throw new Hospital\Http\ApiException(405, 'METHOD_NOT_ALLOWED', \Hospital\I18n::t("errors.metodo_no_permitido"));
        }
        break;
    case 'preferences-update':
        Request::method('POST'); Csrf::verify();
        $user = auth_require_user($db, $config);
        $body = Request::body(['version', 'locale', 'theme', 'photo_visible_to_staff', 'optional_diagnostics', 'security_notifications', 'automatic_logout'], []);
        settings_preferences($db, (string) $user['id'], $body);
        Response::json(auth_payload($db, $config, auth_require_user($db, $config, false)));
        break;
    case 'profile-update':
        Request::method('POST'); Csrf::verify();
        $user = auth_require_user($db, $config);
        $body = Request::body(['version', 'full_name', 'email', 'default_facility_id', 'specialties'], ['specialties']);
        settings_profile($db, (string) $user['id'], $body);
        Response::json(auth_payload($db, $config, auth_require_user($db, $config, false)));
        break;
}
