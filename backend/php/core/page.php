<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

/** Shared guard for staff PHP views. */
function require_staff_page(string $view): void
{
    global $db, $config;
    try {
        $user = auth_require_user($db, $config, false);
    } catch (Hospital\Http\ApiException $error) {
        if ($error->status !== 401) {
            throw $error;
        }
        header('Location: ' . $config['base_path'] . '/index.php?reason=session_expired', true, 303);
        exit;
    }

    $page = preg_replace('/\.php$/', '', $view);
    $permissions = [
        'management/patients' => 'records_manager',
        'management/doctors' => 'records_manager',
        'management/ambulances' => 'dispatcher',
        'management/personnel' => 'dispatcher',
        'management/destinations' => 'dispatcher',
        'management/routes' => 'dispatcher',
        'traces/trace-new' => 'dispatcher',
        'management/accounts' => 'admin',
    ];
    if (isset($permissions[$page])) {
        $roles = users_roles($db, (string) $user['id']);
        if (!in_array($permissions[$page], $roles, true)) {
            $GLOBALS['page_user'] = $user;
            require_once dirname(__DIR__, 3) . '/public/src/views/error.php';
            render_error_page(403);
            exit;
        }
    }
    $settings = $db->prepare('SELECT locale, theme, photo_visible_to_staff, optional_diagnostics, security_notifications, automatic_logout FROM user_settings WHERE user_id = ?');
    $settings->execute([(string)$user['id']]);
    $saved = $settings->fetch();
    $GLOBALS['page_preferences'] = $saved ?: [
        'locale' => 'es-UY',
        'theme' => 'dark',
        'photo_visible_to_staff' => false,
        'optional_diagnostics' => false,
        'security_notifications' => true,
        'automatic_logout' => true,
    ];
    $GLOBALS['page_user'] = $user;
    $GLOBALS['page_has_settings'] = (bool) $saved;
    $GLOBALS['page_locale'] = $GLOBALS['page_preferences']['locale'] === 'en' ? 'en' : 'es-UY';
    $GLOBALS['page_theme'] = $GLOBALS['page_preferences']['theme'] === 'light' ? 'light' : 'dark';
    session_write_close();
    if (in_array($page, ['traces/trace-view', 'traces/trace-map'], true)) {
        header('Referrer-Policy: strict-origin');
    }
    header('Content-Type: text/html; charset=utf-8');
}
