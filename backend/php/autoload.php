<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    $prefix = 'Hospital\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0)
    return;
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = __DIR__ . '/' . $relative . '.php';
    if (is_file($path))
        require $path;
});
date_default_timezone_set('UTC');

// Procedural domains; definitions only. Dependencies receive the current PDO/config explicitly.
require_once __DIR__ . '/modules/auth.php';
require_once __DIR__ . '/modules/mail.php';
require_once __DIR__ . '/modules/settings.php';
require_once __DIR__ . '/modules/ambulances.php';
require_once __DIR__ . '/modules/patients.php';
require_once __DIR__ . '/modules/doctors.php';
require_once __DIR__ . '/modules/personnel.php';
require_once __DIR__ . '/modules/destinations.php';
require_once __DIR__ . '/modules/routes.php';
require_once __DIR__ . '/modules/document-files.php';
require_once __DIR__ . '/modules/documents.php';
require_once __DIR__ . '/modules/transfer-access.php';
require_once __DIR__ . '/modules/transfer-input.php';
require_once __DIR__ . '/modules/transfers.php';
require_once __DIR__ . '/modules/tracking.php';
require_once __DIR__ . '/modules/surveys.php';
