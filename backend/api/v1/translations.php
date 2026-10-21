<?php
declare(strict_types=1);

// The browser consumes a raw dictionary; this public endpoint never opens SQL or a session.
$language = $_GET['lang'] ?? 'es';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo '{"success":false,"error":{"code":"METHOD_NOT_ALLOWED"}}';
    exit;
}
if (!is_string($language) || !in_array($language, ['es', 'en'], true)) {
    http_response_code(400);
    echo '{"success":false,"error":{"code":"INVALID_LANGUAGE"}}';
    exit;
}
header('Content-Language: ' . ($language === 'es' ? 'es-UY' : 'en'));
readfile(dirname(__DIR__, 3) . '/db/json/' . $language . '.json');
