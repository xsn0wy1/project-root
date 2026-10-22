<?php
declare(strict_types=1);
// CLI-only controls for the existing release-fix suite; no web-accessible test API.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/backend/php/autoload.php';
$config = Hospital\Config::load();
if (!str_ends_with($config['db']['name'], '_test')) throw new RuntimeException('Disposable database required.');
$input = json_decode((string) fgets(STDIN), true, 32, JSON_THROW_ON_ERROR);

final class BudgetTestPDO extends PDO
{
    public array $control = [];
    public bool $reached = false;
    public function prepare($query, $options = []): PDOStatement|false
    {
        if (($this->control['refund_fault'] ?? false) && str_starts_with($query, 'DELETE FROM auth_rate_limits')) {
            $this->control['refund_fault'] = false;
            throw new PDOException('Injected refund cleanup failure.');
        }
        $login = ($this->control['action'] ?? '') === 'login' && str_starts_with($query, 'SELECT * FROM users WHERE username = ?');
        $change = ($this->control['action'] ?? '') === 'change' && str_starts_with($query, 'SELECT u.*, s.full_name AS staff_name') && str_ends_with($query, ' FOR UPDATE');
        if (!$this->reached && ($login || $change)) {
            $this->reached = true;
            if ($this->control['hold'] ?? false) {
                fwrite(STDERR, "charged\n");
                if (trim((string) fgets(STDIN)) !== 'continue') throw new RuntimeException('Missing barrier release.');
            }
        }
        return parent::prepare($query, $options);
    }
}

$d = $config['db'];
$dsn = $d['socket'] !== '' ? 'mysql:unix_socket=' . $d['socket'] : 'mysql:host=' . $d['host'] . ';port=' . $d['port'];
$db = new BudgetTestPDO($dsn . ';dbname=' . $d['name'] . ';charset=utf8mb4', $d['user'], $d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
$db->exec("SET time_zone = '+00:00'");
$db->control = $input;
$GLOBALS['request_id'] = 'budget-test';
$_SERVER['REMOTE_ADDR'] = $input['ip'] ?? '203.0.113.77';
session_set_save_handler(new Hospital\Security\DatabaseSessionHandler($db, $config), true);
session_start();
try {
    if ($input['action'] === 'login') {
        auth_login($db, $config, $input);
    } elseif ($input['action'] === 'change') {
        $user = users_by_id($db, $input['user_id']);
        $_SESSION = ['user_id' => $input['user_id'], 'auth_version' => $input['auth_version'] ?? (int) $user['auth_version']];
        password_change($db, $config, $input['user_id'], $input['user_id'], $input['body']);
    } elseif ($input['action'] === 'refund') {
        $limiter = new Hospital\Security\RateLimiter($db, $config['app_key']);
        $limiter->reserve([[$input['bucket'], 20, 900]]);
        $key = hash_hmac('sha256', $input['bucket'], $config['app_key']);
        if (($input['scenario'] ?? '') === 'new-window') {
            $s = $db->prepare('UPDATE auth_rate_limits SET attempts=4,resets_at=UTC_TIMESTAMP()+INTERVAL 30 MINUTE WHERE bucket_key=?');
            $s->execute([$key]);
        } elseif (($input['scenario'] ?? '') === 'zero') {
            $s = $db->prepare('UPDATE auth_rate_limits SET attempts=0 WHERE bucket_key=?'); $s->execute([$key]);
        } elseif (($input['scenario'] ?? '') === 'rollback') {
            $db->beginTransaction(); $limiter->refund(); $limiter->refund(); $db->rollBack();
        }
        $limiter->refund(); $limiter->refund();
    } else {
        throw new LogicException('Unknown test operation.');
    }
    $result = ['status' => 200];
} catch (Hospital\Http\ApiException $error) {
    $result = ['status' => $error->status, 'code' => $error->errorCode];
} catch (Throwable $error) {
    $result = ['status' => 503, 'code' => 'SERVICE_UNAVAILABLE'];
}
$result['credential_lookup_reached'] = $db->reached;
$result['authenticated'] = isset($_SESSION['user_id']);
if ($db->inTransaction()) $db->rollBack();
session_destroy();
echo json_encode($result, JSON_THROW_ON_ERROR);
