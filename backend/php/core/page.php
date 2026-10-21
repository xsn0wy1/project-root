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
        if ($error->status !== 401) throw $error;
        header('Location: ' . $config['base_path'] . '/index.php?reason=session_expired', true, 303);
        exit;
    }

    $page = preg_replace('/\.php$/', '', $view);
    $permissions = [
        'management/patients' => 'records_manager', 'management/doctors' => 'records_manager',
        'management/ambulances' => 'dispatcher', 'management/personnel' => 'dispatcher',
        'management/destinations' => 'dispatcher', 'management/routes' => 'dispatcher',
        'traces/trace-new' => 'dispatcher', 'management/accounts' => 'admin',
    ];
    if (isset($permissions[$page])) {
        $roles = users_roles($db, (string) $user['id']);
        if (!in_array($permissions[$page], $roles, true)) {
            $fallback = $page === 'traces/trace-new' ? 'traces/trace.php' : 'management/management.php';
            header('Location: ' . $config['base_path'] . '/src/views/' . $fallback, true, 303);
            exit;
        }
    }
    session_write_close();
    if (in_array($page, ['traces/trace-view', 'traces/trace-map'], true)) header('Referrer-Policy: strict-origin');
    header('Content-Type: text/html; charset=utf-8');
}
