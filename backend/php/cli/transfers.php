<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/autoload.php';
try {
    $config = Hospital\Config::load();
    $db = Hospital\Database::connect($config);
    $command = $argv[1] ?? '';

    if ($command === 'catalogs') {
        $result = transfer_catalog_selection($db);
    } elseif (in_array($command, ['import', 'seed-demo'], true)) {
        $path = $command === 'seed-demo' ? dirname(__DIR__, 3) . '/db/json/fixtures/traces-demo.json' : ($argv[2] ?? '');
        if (!is_file($path) || filesize($path) > 1048576) {
            throw new RuntimeException('Invalid catalog file.');
        }
        $input = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        $result = transfer_serialized($db, $config, fn () => transfer_catalog_import($db, $input));
    } else {
        fwrite(STDERR, "Commands: catalogs | import PRIVATE_JSON_FILE | seed-demo (explicit fictional catalog only)\n");
        exit(1);
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $caughtError) {
    fwrite(STDERR, "Transfer catalog operation failed. Check the private configuration and input.\n");
    exit(1);
}
