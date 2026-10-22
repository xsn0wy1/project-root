<?php
declare(strict_types=1);

use Hospital\Http\ApiException;
use Hospital\I18n;

// Transactional recovery email; an optional transport keeps focused checks offline.
function mail_configuration_valid(array $config): bool
{
    $url = parse_url($config['app_url'] ?? '');
    $provider = parse_url($config['mail_api_url'] ?? '');
    return !(trim($config['brevo_api_key'] ?? '') === '' ||
        preg_match('/[\r\n]/', $config['brevo_api_key']) ||
        !filter_var($config['mail_from_email'] ?? '', FILTER_VALIDATE_EMAIL) ||
        !$url || !in_array($url['scheme'] ?? '', ['http', 'https'], true) || empty($url['host']) ||
        isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) ||
        rtrim($url['path'] ?? '', '/') !== ($config['base_path'] ?? '') ||
        !$provider || empty($provider['host']) || isset($provider['user']) || isset($provider['pass']) || isset($provider['query']) || isset($provider['fragment']) ||
        !(($provider['scheme'] ?? '') === 'https' || (($provider['scheme'] ?? '') === 'http' && in_array($provider['host'], ['127.0.0.1', '[::1]', 'localhost'], true))));
}

/** Only capability/status information; never addresses, keys or probe output. */

function mail_readiness(array $config, bool $worker = true, bool $prepare = false): array
{
    if (($config['brevo_api_key'] ?? '') === '' && ($config['mail_from_email'] ?? '') === '') {
        return ['status' => 'unconfigured', 'checks' => []];
    }
    $checks = ['configuration' => mail_configuration_valid($config)];
    try {
        if ($checks['configuration']) {
            $directory = (new Hospital\Storage\PrivateStorage($config))->directory('mail', $prepare);
            $checks['storage'] = is_readable($directory) && is_writable($directory);
            if ($worker) {
                $checks['process_functions'] = true;
                foreach (['proc_open', 'proc_get_status', 'proc_terminate', 'proc_close'] as $function) {
                    $checks['process_functions'] = $checks['process_functions'] && function_exists($function);
                }
                $program = mail_worker_program($config);
                $checks['php_cli'] = $checks['process_functions'] && $program !== '' && mail_worker_probe($program);
            }
        }
    } catch (Throwable $error) {
        $checks['storage_or_runtime'] = false;
    }
    return ['status' => in_array(false, $checks, true) ? 'unavailable' : 'ready', 'checks' => $checks];
}

function mail_require_configuration(array $config, bool $worker = true): void
{
    if (mail_readiness($config, $worker, true)['status'] !== 'ready') {
        throw new ApiException(503, 'RECOVERY_UNAVAILABLE', I18n::t('recovery.unavailable'));
    }
}

function mail_send_reset(array $config, string $email, string $url, ?callable $transport = null): void
{
    $payload = [
        'sender' => ['email' => $config['mail_from_email'], 'name' => $config['mail_from_name']],
        'to' => [['email' => $email]],
        'subject' => I18n::t('recovery.email_subject'),
        // Plain text keeps the recovery URL readable/copyable without an HTML template.
        'textContent' => I18n::t('recovery.email_body', ['url' => $url]),
    ];
    if ($transport !== null) {
        ($transport)($payload);
        return;
    }
    $handle = curl_init($config['mail_api_url']);
    curl_setopt_array(
        $handle,
        [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'api-key: ' . $config['brevo_api_key']],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
        ]
    );
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    if ($response === false || $status !== 201 || !is_string(json_decode($response, true)['messageId'] ?? null)) {
        // Provider responses may contain addresses or request details; never log them.
        throw new \RuntimeException('Email delivery was not accepted.');
    }
}

function mail_worker_program(array $config): string
{
    $override = trim($config['mail_php_binary'] ?? '');
    $name = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
    $candidates = $override !== '' ? [$override] : [
        PHP_SAPI === 'cli' ? PHP_BINARY : '',
        PHP_BINDIR . DIRECTORY_SEPARATOR . $name,
        dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . $name,
    ];
    foreach (array_unique($candidates) as $path) {
        $absolute = PHP_OS_FAMILY === 'Windows'
            ? preg_match('~^(?:[a-zA-Z]:[\\\\/]|\\\\\\\\[^\\\\]+[\\\\])~', $path)
            : str_starts_with($path, '/');
        if ($absolute && !preg_match('/[\x00-\x1f]/', $path) && is_file($path) && is_executable($path))
            return $path;
    }
    return '';
}

/** Bounded dry probe: no application configuration, DB connection, job or email. */

function mail_worker_probe(string $program): bool
{
    $code = 'if (PHP_SAPI !== "cli" || PHP_VERSION_ID < 80000) exit(1);'
        . 'foreach (["pdo_mysql","curl","mbstring"] as $extension) if (!extension_loaded($extension)) exit(1);'
        . 'foreach (["curl_init","curl_setopt_array","curl_exec","curl_getinfo","curl_close","flock","ftruncate"] as $function) if (!function_exists($function)) exit(1);'
        . 'echo "hospital-mail-ready";';
    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $environment = ['PATH' => getenv('PATH') ?: '', 'SystemRoot' => getenv('SystemRoot') ?: ''];
    $process = @proc_open(
        [$program, '-r', $code],
        [0 => ['file', $null, 'r'], 1 => ['pipe', 'w'], 2 => ['file', $null, 'a']],
        $pipes,
        null,
        $environment
    );
    if (!is_resource($process))
        return false;
    try {
        stream_set_blocking($pipes[1], false);
        $output = '';
        $deadline = microtime(true) + 2;
        do {
            $output .= fread($pipes[1], 256);
            $status = proc_get_status($process);
            if (!$status || !$status['running'] || strlen($output) > 128)
            break;
            usleep(10000);
        } while (microtime(true) < $deadline);
        if (!$status || $status['running']) {
            proc_terminate($process, 9);
            return false;
        }
        $output .= stream_get_contents($pipes[1]);
        return $status['exitcode'] === 0 && $output === 'hospital-mail-ready';
    } finally {
        fclose($pipes[1]);
        proc_close($process);
    }
}

function mail_worker_environment(array $config): array
{
    // Secrets travel only in the child environment, never command arguments.
    $environment = ['PATH' => getenv('PATH') ?: '', 'SystemRoot' => getenv('SystemRoot') ?: ''];
    foreach (['SSL_CERT_FILE', 'SSL_CERT_DIR', 'CURL_CA_BUNDLE'] as $name) {
        if (getenv($name) !== false)
            $environment[$name] = getenv($name);
    }
    foreach (
        [
            'app_key',
            'base_path',
            'app_url',
            'storage_path',
            'brevo_api_key',
            'mail_from_email',
            'mail_from_name',
            'mail_api_url'
        ] as $name
    ) {
        $environment['HOSPITAL_' . strtoupper($name)] = (string) $config[$name];
    }
    foreach ($config['db'] as $name => $value)
        $environment['HOSPITAL_DB_' . strtoupper($name)] = (string) $value;
    return $environment;
}

/** Queue outside public/, then launch one bounded CLI delivery without waiting. */

function mail_queue_reset(array $config, string $id, string $email, string $url, string $hash): void
{
    $storage = new Hospital\Storage\PrivateStorage($config);
    $key = bin2hex(random_bytes(32));
    $path = $storage->path('mail', $key);
    $temporary = $path . '.tmp';
    $job = ['user_id' => $id, 'email' => $email, 'url' => $url, 'hash' => $hash, 'locale' => I18n::locale(), 'created_at' => time()];
    $old = umask(0077);
    try {
        $file = fopen($temporary, 'x');
        if (!$file)
            throw new RuntimeException('Mail queue unavailable.');
        try {
            $data = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (fwrite($file, $data) !== strlen($data))
                throw new RuntimeException('Mail queue write failed.');
        } finally {
            fclose($file);
        }
        if (!rename($temporary, $path))
            throw new RuntimeException('Mail queue publish failed.');
    } catch (Throwable $error) {
        @unlink($temporary);
        throw $error;
    } finally {
        umask($old);
    }
    try {
        $environment = mail_worker_environment($config);
        $program = mail_worker_program($config);
        if ($program === '')
            throw new RuntimeException('Mail worker unavailable.');
        $command = escapeshellarg($program) . ' ' . escapeshellarg(dirname(__DIR__) . '/cli/send-reset-mail.php') . ' ' . escapeshellarg($key);
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $command = PHP_OS_FAMILY === 'Windows' ? 'start /B "" ' . $command : $command . ' < /dev/null > /dev/null 2>&1 &';
        $process = proc_open($command, [0 => ['file', $null, 'r'], 1 => ['file', $null, 'a'], 2 => ['file', $null, 'a']], $pipes, null, $environment);
        if (!is_resource($process) || proc_close($process) !== 0)
            throw new RuntimeException('Mail worker unavailable.');
    } catch (Throwable $error) {
        @unlink($path);
        throw $error;
    }
}

/** CLI only; lock prevents a manual drain and the spawned worker sending twice. */

function mail_deliver_job(PDO $db, array $config, string $key): bool
{
    if (PHP_SAPI !== 'cli' || !preg_match('/^[a-f0-9]{64}$/D', $key))
        throw new RuntimeException('Invalid mail job.');
    $path = (new Hospital\Storage\PrivateStorage($config))->path('mail', $key);
    if (!is_file($path))
        return false;
    $file = @fopen($path, 'r+');
    if (!$file)
        return false;
    if (!flock($file, LOCK_EX | LOCK_NB)) {
        fclose($file);
        return false;
    }
    $job = null;
    $credential = false;
    try {
        if (!is_file($path))
            return false;
        $job = json_decode(stream_get_contents($file, 8192), true, 16, JSON_THROW_ON_ERROR);
        $credential = is_array($job) && is_string($job['user_id'] ?? null) && preg_match('/^[1-9][0-9]*$/D', $job['user_id']) && is_string($job['hash'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $job['hash']);
        if (!$credential || !is_int($job['created_at'] ?? null) || $job['created_at'] < time() - 1800)
            return false;
        $stmt = $db->prepare('SELECT token_hash, auth_version, expires_at FROM password_reset_tokens WHERE user_id = ?');
        $stmt->execute([$job['user_id']]);
        $reset = $stmt->fetch();
        $user = users_by_id($db, $job['user_id']);
        if (
            !$reset
            || !hash_equals($reset['token_hash'], $job['hash'])
            || strtotime($reset['expires_at'] . ' UTC') <= time()
            || !password_recovery_eligible($user)
            || (int)$reset['auth_version'] !== (int)$user['auth_version']
            || $user['email'] !== $job['email']
        )
            return false;
        $_SERVER['HTTP_X_HOSPITAL_LOCALE'] = $job['locale'] === 'en' ? 'en' : 'es-UY';
        mail_send_reset($config, $job['email'], $job['url']);
        return true;
    } catch (Throwable $error) {
        if ($credential) {
            $stmt = $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? AND token_hash = ?');
            $stmt->execute([$job['user_id'], $job['hash']]);
        }
        error_log('Hospital password recovery delivery failed.');
        return false;
    } finally {
        ftruncate($file, 0);
        fflush($file);
        @unlink($path);
        flock($file, LOCK_UN);
        fclose($file);
        @unlink($path);
    }
}
