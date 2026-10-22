<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/autoload.php';

try {
    $config = Hospital\Config::load();
    mail_require_configuration($config, false);
    $db = Hospital\Database::connect($config);
    $directory = (new Hospital\Storage\PrivateStorage($config))->directory('mail');
    if (!isset($argv[1]))
        foreach (glob($directory . '/*.bin.tmp') as $file) {
            if (
                preg_match('/^[a-f0-9]{64}\.bin\.tmp$/D', basename($file))
                && !is_link($file)
                && filemtime($file) < time() - 1800
            )
                @unlink($file);
        }
    $keys = isset($argv[1]) ? [$argv[1]] : array_map(static fn (string $file): string => basename($file, '.bin'), glob($directory . '/*.bin'));
    $sent = 0;
    foreach ($keys as $key)
        if (mail_deliver_job($db, $config, $key))
            $sent++;
    echo 'Completed mail jobs: ' . $sent . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Mail worker failed; check private configuration and storage.\n");
    exit(1);
}
