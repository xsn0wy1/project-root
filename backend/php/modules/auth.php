<?php
declare(strict_types=1);

use Hospital\Http\ApiException;
use Hospital\Security\Csrf;
use Hospital\Security\RateLimiter;
use Hospital\I18n;

// Authentication and session lifecycle.
function auth_registration_fields(array $input): array
{
    $fields = [];
    $errors = [];
    foreach (['username', 'email', 'password', 'national_id'] as $name) {
        if (!isset($input[$name]) || !is_string($input[$name])) {
            $errors[$name] = \Hospital\I18n::t("validation.required");
        }
    }
    if ($errors) {
        throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.revisa_los_campos_indicados"), $errors);
    }
    $fields['username'] = strtolower(trim($input['username']));
    $fields['email'] = strtolower(trim($input['email']));
    $fields['national_id'] = preg_replace('/[.\s-]/', '', trim($input['national_id']));
    $fields['password'] = $input['password'];
    if (!preg_match('/^[a-z0-9][a-z0-9_.-]{2,49}$/', $fields['username'])) {
        $errors['username'] = \Hospital\I18n::t("errors.usa_entre_3_y_50_letras_sin_tildes_numeros_puntos_guiones_o_guiones_bajos");
    }
    if (strlen($fields['email']) > 254 || !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = \Hospital\I18n::t("errors.ingresa_un_email_valido");
    }
    if (!preg_match('/^[0-9]{6,12}$/', $fields['national_id'])) {
        $errors['national_id'] = \Hospital\I18n::t("errors.ingresa_una_ci_de_6_a_12_digitos_con_o_sin_puntos_y_guion");
    }
    $length = function_exists('mb_strlen') ? mb_strlen($fields['password'], 'UTF-8') : preg_match_all('/./us', $fields['password']);
    if ($length < 12 || strlen($fields['password']) > 72 || str_contains($fields['password'], "\0")) {
        $errors['password'] = \Hospital\I18n::t("errors.usa_al_menos_12_caracteres_y_un_maximo_de_72_bytes_se_permiten_espacios");
    }
    if ($errors) {
        throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.revisa_los_campos_indicados"), $errors);
    }
    return $fields;
}

function auth_register(PDO $db, array $config, array $input): array
{
    if (isset($_SESSION['user_id'])) {
        throw new ApiException(409, 'ALREADY_AUTHENTICATED', \Hospital\I18n::t("errors.cerra_tu_sesion_antes_de_registrar_otra_cuenta"));
    }
    (new RateLimiter($db, $config['app_key']))->consume('register:' . ($_SERVER['REMOTE_ADDR'] ?? 'local'), 10, 3600);
    $fields = auth_registration_fields($input);
    $hash = password_hash($fields['password'], PASSWORD_BCRYPT, ['cost' => $config['bcrypt_cost']]);
    $db->beginTransaction();
    try {
        $id = users_create_pending($db, $fields, $hash);
        users_audit($db, null, 'account.registered', $id, $GLOBALS['request_id']);
        $db->commit();
    } catch (\PDOException $caughtError) {
        $db->rollBack();
        if (($caughtError->errorInfo[1] ?? 0) === 1062) {
            throw new ApiException(409, 'ACCOUNT_CONFLICT', \Hospital\I18n::t("errors.ya_existe_una_solicitud_o_cuenta_con_esos_datos"));
        }
        throw $caughtError;
    } catch (\Throwable $caughtError) {
        $db->rollBack();
        throw $caughtError;
    }
    return [
        'user_id' => $id,
        'status' => 'pending',
        'message' => \Hospital\I18n::t("errors.solicitud_registrada_un_administrador_debe_aprobar_tu_cuenta_antes_de_que_puedas_ingresar")
    ];
}

function auth_login(PDO $db, array $config, array $input): array
{
    $username = is_string($input['username'] ?? null) ? strtolower(trim($input['username'])) : '';
    $password = is_string($input['password'] ?? null) ? $input['password'] : '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'local';
    $limiter = new RateLimiter($db, $config['app_key']);
    $source = 'login-failure-source:' . $ip . ':' . $username;
    $limiter->reserve([['login-failure-ip:' . $ip, 60, 900], [$source, 10, 900]]);
    $credentialsFailed = false;
    try {
        // A valid dummy hash gives unknown accounts the same verification work.
        $dummy = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        $row = strlen($username) <= 50 ? users_by_username($db, $username) : null;
        $valid = strlen($password) <= 72 && !str_contains($password, "\0") && password_verify($password, $row['password_hash'] ?? $dummy);
        if (!$row || !$valid) {
            $credentialsFailed = true;
            throw new ApiException(401, 'INVALID_CREDENTIALS', \Hospital\I18n::t("errors.usuario_o_contrasena_incorrectos"));
        }
        if ($row['status'] === 'pending') {
            throw new ApiException(
                403,
                'ACCOUNT_PENDING',
                \Hospital\I18n::t("errors.tu_cuenta_esta_pendiente_de_aprobacion_por_un_administrador")
            );
        }
        if ($row['status'] !== 'active' || !$row['staff_id']) {
            throw new ApiException(403, 'ACCOUNT_DISABLED', \Hospital\I18n::t("errors.tu_cuenta_no_tiene_acceso_al_sistema"));
        }
        $verified = $row;
        // Serialize with account changes without adopting credentials verified before a reset.
        $db->beginTransaction();
        $row = users_by_id($db, (string) $row['id'], true);
        if (
            !$row
            || $row['password_hash'] !== $verified['password_hash']
            || (int) $row['auth_version'] !== (int) $verified['auth_version']
        ) {
            throw new ApiException(401, 'INVALID_CREDENTIALS', \Hospital\I18n::t("errors.usuario_o_contrasena_incorrectos"));
        }
        if ($row['status'] !== 'active' || $row['staff_status'] !== 'active') {
            throw new ApiException(403, 'ACCOUNT_DISABLED', \Hospital\I18n::t("errors.tu_cuenta_no_tiene_acceso_al_sistema"));
        }
        $rehash = password_needs_rehash($row['password_hash'], PASSWORD_BCRYPT, ['cost' => $config['bcrypt_cost']]) ? password_hash($password, PASSWORD_BCRYPT, ['cost' => $config['bcrypt_cost']]) : null;
        users_login_recorded($db, (string) $row['id'], $rehash);
        users_audit($db, (string) $row['id'], 'session.login', (string) $row['id'], $GLOBALS['request_id']);
        $stmt = $db->prepare("INSERT INTO user_notifications (user_id, kind) SELECT user_id, 'new_login' FROM user_settings WHERE user_id = ? AND security_notifications = 1");
        $stmt->execute([$row['id']]);
        // Keep a bounded private inbox; auditing remains independent of this preference.
        $stmt = $db->prepare('DELETE FROM user_notifications WHERE user_id = ? AND id NOT IN (SELECT id FROM (SELECT id FROM user_notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100) recent)');
        $stmt->execute([$row['id'], $row['id']]);
        $limiter->refund($source);
        $db->commit();
        $limiter->confirmRefund();
    } catch (\Throwable $caughtError) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if (!$credentialsFailed) {
            auth_release_attempt($limiter);
        }
        throw $caughtError;
    }
    if (!session_regenerate_id(true)) {
        throw new \RuntimeException('Session rotation failed.');
    }
    $_SESSION = [
        'user_id' => (string) $row['id'],
        'auth_version' => (int) $row['auth_version'],
        '_created_at' => time(),
        '_auth_started' => time(),
        '_last_activity' => time(),
        '_idle_seconds' => auth_idle_seconds($db, $config, (string) $row['id'])
    ];
    return auth_payload($db, $config, users_by_id($db, (string) $row['id']));
}

function auth_release_attempt(RateLimiter $limiter): void
{
    try {
        $limiter->refund();
    } catch (\Throwable $error) {
        error_log('Hospital authentication attempt refund failed.');
    }
}

function auth_require_user(PDO $db, array $config, bool $touch = true): array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!is_string($id)) {
        throw new ApiException(401, 'UNAUTHENTICATED', \Hospital\I18n::t("errors.inicia_sesion_para_continuar"));
    }
    $row = users_by_id($db, $id);
    $idle = auth_idle_seconds($db, $config, $id);
    $_SESSION['_idle_seconds'] = $idle;
    $expired = time() - (int) ($_SESSION['_last_activity'] ?? 0) >= $idle ||
        time() - (int) ($_SESSION['_auth_started'] ?? 0) >= $config['session_absolute_seconds'];
    if (!$row || $row['status'] !== 'active' || $row['staff_status'] !== 'active' ||
        (int) $row['auth_version'] !== (int) ($_SESSION['auth_version'] ?? 0) || $expired ||
        !sessions_is_live($db, hash('sha256', session_id()), $id)) {
        auth_clear_session($config);
        throw new ApiException(401, 'SESSION_EXPIRED', \Hospital\I18n::t("errors.tu_sesion_vencio_o_fue_cerrada_inicia_sesion_nuevamente"));
    }
    if ($touch) {
        $_SESSION['_last_activity'] = time();
    }
    return $row;
}

function auth_require_admin(PDO $db, array $config): array
{
    $user = auth_require_user($db, $config);
    if (!user_has_role($db, (string) $user['id'], 'admin')) {
        throw new ApiException(403, 'FORBIDDEN', \Hospital\I18n::t("errors.no_tenes_permiso_para_esta_accion"));
    }
    return $user;
}

function auth_payload(PDO $db, array $config, array $row): array
{
    $id = (string) $row['id'];
    $_SESSION['_idle_seconds'] = auth_idle_seconds($db, $config, $id);
    return [
        'user' => [
            'id' => $id,
            'staff_id' => $row['staff_id'] === null ? null : (string) $row['staff_id'],
            'username' => $row['username'],
            'email' => $row['email'],
            'full_name' => $row['staff_name'] ?? $row['display_name'],
            'national_id' => $row['staff_national_id'] ?? $row['registration_national_id'],
            'status' => $row['status'],
            'roles' => users_roles($db, $id),
            'version' => (int) $row['version'],
            'created_at' => users_date($row['created_at']),
            'last_login_at' => users_date($row['last_login_at'])
        ],
        'settings' => users_settings($db, $id),
        'csrf_token' => Csrf::token(),
        'session' => [
            'idle_timeout_seconds' => $_SESSION['_idle_seconds'],
            'idle_expires_at' => gmdate(
                'Y-m-d\TH:i:s\Z',
                min(
                    (int) $_SESSION['_last_activity'] + $_SESSION['_idle_seconds'],
                    (int) $_SESSION['_auth_started'] + $config['session_absolute_seconds']
                )
            ),
            'absolute_expires_at' => gmdate('Y-m-d\TH:i:s\Z', (int) $_SESSION['_auth_started'] + $config['session_absolute_seconds'])
        ]
    ];
}

function auth_idle_seconds(PDO $db, array $config, string $id): int
{
    $stmt = $db->prepare('SELECT automatic_logout FROM user_settings WHERE user_id = ?');
    $stmt->execute([$id]);
    return $stmt->fetchColumn() ? min(300, $config['session_idle_seconds']) : $config['session_idle_seconds'];
}

function auth_logout(PDO $db, array $config): void
{
    if (isset($_SESSION['user_id'])) {
        users_audit($db, $_SESSION['user_id'], 'session.logout', $_SESSION['user_id'], $GLOBALS['request_id']);
    }
    auth_clear_session($config);
}

function auth_clear_session(array $config): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 3600,
            'path' => ($config['base_path'] ?: '') . '/',
            'secure' => $config['secure_cookies'],
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

function auth_sessions(PDO $db, array $config): array
{
    $user = auth_require_user($db, $config, false);
    return sessions_list_own($db, (string) $user['id'], hash('sha256', session_id()));
}

function auth_revoke_session(PDO $db, array $config, string $id): array
{
    $user = auth_require_user($db, $config);
    $db->beginTransaction();
    try {
        $hash = sessions_revoke_own($db, $id, (string) $user['id']);
        if ($hash === null) {
            throw new ApiException(404, 'SESSION_NOT_FOUND', \Hospital\I18n::t("errors.no_se_encontro_esa_sesion"));
        }
        users_audit($db, (string) $user['id'], 'session.revoked', (string) $user['id'], $GLOBALS['request_id']);
        $db->commit();
    } catch (\Throwable $caughtError) {
        $db->rollBack();
        throw $caughtError;
    }
    $current = hash_equals(hash('sha256', session_id()), $hash);
    if ($current) {
        auth_clear_session($config);
    }
    return ['revoked' => true, 'current_session_revoked' => $current];
}

// Account queries and safe payloads.

function users_by_username(PDO $db, string $username): ?array
{
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    return $stmt->fetch() ?: null;
}

function users_by_id(PDO $db, string $id, bool $lock = false): ?array
{
    $stmt = $db->prepare('SELECT u.*, s.full_name AS staff_name, s.national_id AS staff_national_id, s.status AS staff_status, s.version AS staff_version FROM users u LEFT JOIN staff s ON s.id = u.staff_id WHERE u.id = ?' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function users_create_pending(PDO $db, array $fields, string $hash): string
{
    $stmt = $db->prepare('INSERT INTO users (username, email, display_name, registration_national_id, password_hash) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$fields['username'], $fields['email'], '', $fields['national_id'], $hash]);
    $id = $db->lastInsertId();
    $stmt = $db->prepare('INSERT INTO user_settings (user_id) VALUES (?)');
    $stmt->execute([$id]);
    return $id;
}

function users_roles(PDO $db, string $id): array
{
    $stmt = $db->prepare('SELECT r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? ORDER BY r.code');
    $stmt->execute([$id]);
    return $stmt->fetchAll(\PDO::FETCH_COLUMN);
}

// Effective access never changes the roles stored or displayed for the account.
function user_has_role(PDO $db, string $actor, string $role): bool
{
    $roles = users_roles($db, $actor);
    return in_array('admin', $roles, true) || in_array($role, $roles, true);
}

function users_settings(PDO $db, string $id): array
{
    $stmt = $db->prepare('SELECT us.*,COALESCE(s.version,0) AS staff_revision FROM user_settings us JOIN users u ON u.id=us.user_id LEFT JOIN staff s ON s.id=u.staff_id WHERE us.user_id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new \RuntimeException('Missing settings row.');
    }
    $stmt = $db->prepare('SELECT c.id, c.name FROM staff_specialties ss JOIN specialties c ON c.id = ss.specialty_id JOIN users u ON u.staff_id = ss.staff_id WHERE u.id = ? ORDER BY c.name');
    $stmt->execute([$id]);
    $specialties = array_map(static fn (array $item): array => ['id' => (string) $item['id'], 'name' => $item['name']], $stmt->fetchAll());
    $stmt = $db->prepare('SELECT name, status FROM facilities WHERE id = ?');
    $stmt->execute([$row['default_facility_id']]);
    $facility = $stmt->fetch();
    return [
        'locale' => $row['locale'],
        'theme' => $row['theme'],
        'photo_visible_to_staff' => (bool) $row['photo_visible_to_staff'],
        'optional_diagnostics' => (bool) $row['optional_diagnostics'],
        'security_notifications' => (bool) $row['security_notifications'],
        'automatic_logout' => (bool) $row['automatic_logout'],
        'avatar_url' => $row['avatar_file_id'] === null ? null : 'api/v1/settings.php?action=photo&user_id=' . $id . '&v=' . $row['avatar_file_id'],
        'default_facility_id' => $row['default_facility_id'] === null ? null : (string) $row['default_facility_id'],
        'default_facility_name' => $facility ? $facility['name'] : '',
        'default_facility_active' => $facility ? $facility['status'] === 'active' : false,
        'specialties' => $specialties,
        'version' => (int) $row['version'] + (int)$row['staff_revision']
    ];
}

function users_login_recorded(PDO $db, string $id, ?string $rehash): void
{
    $stmt = $db->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP(), password_hash = COALESCE(?, password_hash) WHERE id = ?');
    $stmt->execute([$rehash, $id]);
}

function users_audit(PDO $db, ?string $actor, string $action, ?string $resource, string $request): void
{
    $stmt = $db->prepare("INSERT INTO audit_events (actor_user_id, action, resource_type, resource_id, request_id) VALUES (?, ?, 'user', ?, ?)");
    $stmt->execute([$actor, $action, $resource, $request]);
}

function users_pending(PDO $db): array
{
    $rows = $db->query("SELECT id, username, email, registration_national_id AS national_id, status, created_at FROM users WHERE status = 'pending' ORDER BY id LIMIT 100")->fetchAll();
    return array_map(
        static function (array $row): array {
            $row['id'] = (string) $row['id'];
            $row['created_at'] = users_date($row['created_at']);
            return $row;
        },
        $rows
    );
}

function users_date(?string $date): ?string
{
    return $date === null ? null : str_replace(' ', 'T', $date) . 'Z';
}

// Session ownership and revocation.

function sessions_is_live(PDO $db, string $hash, string $userId): bool
{
    $stmt = $db->prepare('SELECT id FROM user_sessions WHERE session_hash = ? AND user_id = ? AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP() AND absolute_expires_at > UTC_TIMESTAMP()');
    $stmt->execute([$hash, $userId]);
    return (bool) $stmt->fetchColumn();
}

function sessions_list_own(PDO $db, string $userId, string $currentHash): array
{
    $stmt = $db->prepare('SELECT id, session_hash, user_agent, created_at, last_seen_at, expires_at FROM user_sessions WHERE user_id = ? AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP() AND absolute_expires_at > UTC_TIMESTAMP() ORDER BY id DESC');
    $stmt->execute([$userId]);
    return array_map(
        static function (array $row) use ($currentHash): array {
            return [
                'id' => (string) $row['id'],
                'is_current' => hash_equals($currentHash, $row['session_hash']),
                'user_agent' => $row['user_agent'],
                'created_at' => users_date($row['created_at']),
                'last_seen_at' => users_date($row['last_seen_at']),
                'expires_at' => users_date($row['expires_at'])
            ];
        },
        $stmt->fetchAll()
    );
}

function sessions_revoke_own(PDO $db, string $id, string $userId): ?string
{
    $stmt = $db->prepare('SELECT session_hash FROM user_sessions WHERE id = ? AND user_id = ? FOR UPDATE');
    $stmt->execute([$id, $userId]);
    $hash = $stmt->fetchColumn();
    if (!$hash) {
        return null;
    }
    $stmt = $db->prepare('UPDATE user_sessions SET revoked_at = COALESCE(revoked_at, UTC_TIMESTAMP()), payload = ? WHERE id = ? AND user_id = ?');
    $stmt->execute(['', $id, $userId]);
    return $hash;
}

function sessions_revoke_all(PDO $db, string $userId): void
{
    $stmt = $db->prepare('UPDATE user_sessions SET revoked_at = COALESCE(revoked_at, UTC_TIMESTAMP()), payload = ? WHERE user_id = ?');
    $stmt->execute(['', $userId]);
}

// Account approval and administration.

function accounts_lock_admin_role(PDO $db): void
{
    $db->query("SELECT id FROM roles WHERE code = 'admin' FOR UPDATE")->fetchColumn();
}

function accounts_active_admin_count(PDO $db): int
{
    return (int) $db->query("SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id JOIN users u ON u.id = ur.user_id WHERE r.code = 'admin' AND u.status = 'active'")->fetchColumn();
}

function accounts_assert_actor(PDO $db, ?string $actor): void
{
    // null is reserved for trusted CLI operations. HTTP always supplies its actor.
    if ($actor === null) {
        return;
    }
    $user = users_by_id($db, $actor, true);
    if (
        !$user
        || $user['status'] !== 'active'
        || $user['staff_status'] !== 'active'
        || (int)$user['auth_version'] !== (int)($_SESSION['auth_version'] ?? 0)
        || !user_has_role($db, $actor, 'admin')
    ) {
        throw new ApiException(403, 'FORBIDDEN', \Hospital\I18n::t("errors.no_tenes_permiso_para_esta_accion"));
    }
}

function accounts_listing(PDO $db): array
{
    $rows = $db->query('SELECT id,username,email,status,version,created_at FROM users ORDER BY id DESC')->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (string)$row['id'];
        $row['version'] = (int)$row['version'];
        $row['created_at'] = users_date($row['created_at']);
        $row['roles'] = users_roles($db, $row['id']);
    } unset($row);
    return $rows;
}

function accounts_manage(PDO $db, string $id, int $version, string $action, array $roles, ?string $actor, string $request): void
{
    if (!in_array($action, ['approve', 'roles', 'disable', 'reactivate', 'revoke_sessions'], true)) {
        throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.accion_no_valida"));
    }
    if (count($roles) > 4 || array_values($roles) !== $roles) {
        throw new ApiException(422, 'INVALID_ROLE', \Hospital\I18n::t("errors.roles_no_validos"));
    }
    foreach ($roles as $role) {
        if (!is_string($role) || !in_array($role, ['admin', 'staff', 'records_manager', 'dispatcher'], true)) {
            throw new ApiException(422, 'INVALID_ROLE', \Hospital\I18n::t("errors.rol_no_valido"));
        }
    }
    $roles = array_values(array_unique($roles));
    if (in_array($action, ['approve', 'roles'], true) && !$roles) {
        throw new ApiException(422, 'INVALID_ROLE', \Hospital\I18n::t("errors.selecciona_al_menos_un_rol"));
    }
    $db->beginTransaction();
    try {
        accounts_lock_admin_role($db);
        accounts_assert_actor($db, $actor);
        $user = users_by_id($db, $id, true);
        if (!$user) {
            throw new ApiException(404, 'USER_NOT_FOUND', \Hospital\I18n::t("errors.no_se_encontro_la_cuenta"));
        }
        if ((int)$user['version'] !== $version) {
            throw new ApiException(409, 'VERSION_CONFLICT', \Hospital\I18n::t("errors.la_cuenta_cambio_volve_a_abrirla"));
        }
        $current = users_roles($db, $id);
        $removesAdmin = $action === 'disable' || ($action === 'roles' && !in_array('admin', $roles, true));
        if (
            $user['status'] === 'active'
            && in_array('admin', $current, true)
            && $removesAdmin
            && accounts_active_admin_count($db) <= 1
        ) {
            throw new ApiException(409, 'LAST_ADMIN', \Hospital\I18n::t("errors.conserva_al_menos_un_administrador_activo"));
        }
        if ($action === 'approve') {
            accounts_activate($db, $user, $roles[0], $actor, $request);
        } elseif ($action === 'roles' && $user['status'] !== 'active') {
            throw new ApiException(409, 'ACCOUNT_NOT_ACTIVE', \Hospital\I18n::t("errors.la_cuenta_debe_estar_activa"));
        } elseif ($action === 'reactivate') {
            if ($user['status'] !== 'disabled' || $user['staff_status'] !== 'active' || !$current) {
                throw new ApiException(409, 'ACCOUNT_NOT_DISABLED', \Hospital\I18n::t("errors.no_se_puede_reactivar_esta_cuenta"));
            }
            $preparedStatement = $db->prepare("UPDATE users SET status='active' WHERE id=?");
            $preparedStatement->execute([$id]);
        } elseif ($action === 'disable') {
            if ($user['status'] !== 'active') {
                throw new ApiException(409, 'ACCOUNT_NOT_ACTIVE', \Hospital\I18n::t("errors.la_cuenta_debe_estar_activa"));
            }
            $preparedStatement = $db->prepare("UPDATE users SET status='disabled' WHERE id=?");
            $preparedStatement->execute([$id]);
        }
        if (in_array($action, ['approve', 'roles'], true)) {
            $preparedStatement = $db->prepare('DELETE FROM user_roles WHERE user_id=?');
            $preparedStatement->execute([$id]);
            $preparedStatement = $db->prepare('INSERT INTO user_roles (user_id,role_id) SELECT ?,id FROM roles WHERE code=?');
            foreach ($roles as $role) {
                $preparedStatement->execute([$id, $role]);
            }
        }
        $preparedStatement = $db->prepare('UPDATE users SET auth_version=auth_version+1,version=version+1 WHERE id=?');
        $preparedStatement->execute([$id]);
        sessions_revoke_all($db, $id);
        users_audit($db, $actor, 'account.' . $action, $id, $request);
        $db->commit();
    } catch (\Throwable $caughtError) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $caughtError;
    }
}

function accounts_activate(PDO $db, array $user, string $role, ?string $actor, string $request): void
{
    $stmt = $db->prepare('SELECT id FROM roles WHERE code = ?');
    $stmt->execute([$role]);
    $roleId = $stmt->fetchColumn();
    if (!$roleId) {
        throw new ApiException(422, 'INVALID_ROLE', \Hospital\I18n::t("errors.el_rol_no_es_valido"));
    }
    if ($user['status'] !== 'pending') {
        throw new ApiException(409, 'ACCOUNT_NOT_PENDING', \Hospital\I18n::t("errors.la_cuenta_no_esta_pendiente_de_aprobacion"));
    }
    $stmt = $db->prepare('SELECT id FROM staff WHERE national_id = ? FOR UPDATE');
    $stmt->execute([$user['registration_national_id']]);
    $staffId = $stmt->fetchColumn();
    if (!$staffId) {
        $stmt = $db->prepare('INSERT INTO staff (national_id, full_name) VALUES (?, ?)');
        $stmt->execute([$user['registration_national_id'], $user['display_name']]);
        $staffId = $db->lastInsertId();
    } else {
        $stmt = $db->prepare("SELECT status FROM staff WHERE id = ?");
        $stmt->execute([$staffId]);
        if ($stmt->fetchColumn() !== 'active') {
            throw new ApiException(409, 'STAFF_INACTIVE', \Hospital\I18n::t("errors.el_funcionario_esta_inactivo"));
        }
    }
    $stmt = $db->prepare("UPDATE users SET staff_id = ?, status = 'active', approved_by = ?, approved_at = UTC_TIMESTAMP(), auth_version = auth_version + 1, version = version + 1 WHERE id = ?");
    $stmt->execute([$staffId, $actor, $user['id']]);
    $stmt = $db->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
    $stmt->execute([$user['id'], $roleId]);
    users_audit($db, $actor, 'account.approved', (string) $user['id'], $request);
}

function accounts_create_first_admin(PDO $db, array $input, int $cost, string $request): string
{
    $fields = auth_registration_fields($input);
    $hash = password_hash($fields['password'], PASSWORD_BCRYPT, ['cost' => $cost]);
    $db->beginTransaction();
    try {
        accounts_lock_admin_role($db);
        if (accounts_active_admin_count($db) > 0) {
            throw new ApiException(409, 'ADMIN_EXISTS', \Hospital\I18n::t("errors.ya_existe_un_administrador_usa_el_flujo_de_aprobacion"));
        }
        $id = users_create_pending($db, $fields, $hash);
        accounts_activate($db, users_by_id($db, $id, true), 'admin', null, $request);
        $db->commit();
        return $id;
    } catch (\Throwable $caughtError) {
        $db->rollBack();
        throw $caughtError;
    }
}

// Password changes and recovery.

function password_password_hash(array $config, $password): string
{
    if (
        !is_string($password)
        || !mb_check_encoding($password, 'UTF-8')
        || mb_strlen($password) < 12
        || strlen($password) > 72
        || str_contains($password, "\0")
    ) {
        throw new ApiException(422, 'VALIDATION_FAILED', I18n::t('errors.usa_entre_12_caracteres_y_72_bytes'));
    }
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => $config['bcrypt_cost']]);
}

function password_replace_password(PDO $db, string $id, string $hash, ?string $actor, string $action): void
{
    $preparedStatement = $db->prepare('UPDATE users SET password_hash=?,auth_version=auth_version+1,version=version+1 WHERE id=?');
    $preparedStatement->execute([$hash, $id]);
    $preparedStatement = $db->prepare('DELETE FROM password_reset_tokens WHERE user_id=?');
    $preparedStatement->execute([$id]);
    sessions_revoke_all($db, $id);
    users_audit($db, $actor, $action, $id, $GLOBALS['request_id'] ?? 'cli');
}

function password_recovery_eligible(?array $user): bool
{
    return $user !== null && ($user['status'] === 'pending' || ($user['status'] === 'active' && $user['staff_status'] === 'active'));
}

function password_request_reset(PDO $db, array $config, array $input, ?callable $transport = null, ?callable $defer = null): array
{

    $email = is_string($input['email'] ?? null) ? strtolower(trim($input['email'])) : '';
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new ApiException(
            422,
            'VALIDATION_FAILED',
            I18n::t('errors.email_no_valido'),
            ['email' => I18n::t('errors.ingresa_un_email_valido')]
        );
    }
    // Check global availability before discovering whether this address exists.
    mail_require_configuration($config);
    $limiter = new RateLimiter($db, $config['app_key']);
    $limiter->consume('reset-request-ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'local'), 20, 3600);
    $limiter->consume('reset-request-email:' . $email, 3, 900);
    $result = ['requested' => true, 'message' => I18n::t('recovery.requested')];
    $preparedStatement = $db->prepare('SELECT id FROM users WHERE email=?');
    $preparedStatement->execute([$email]);
    $id = $preparedStatement->fetchColumn();
    if ($id === false) {
        return $result;
    }
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $db->beginTransaction();
    try {
        // Every reset path locks user before token, also serializing email edits.
        $user = users_by_id($db, (string)$id, true);
        if (!password_recovery_eligible($user) || $user['email'] !== $email) {
            $db->commit();
            return $result;
        }
        $preparedStatement = $db->prepare('INSERT INTO password_reset_tokens (user_id,token_hash,auth_version,created_at,expires_at) VALUES (?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()+INTERVAL 30 MINUTE) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),auth_version=VALUES(auth_version),created_at=VALUES(created_at),expires_at=VALUES(expires_at)');
        $preparedStatement->execute([$id, $hash, $user['auth_version']]);
        users_audit($db, null, 'account.password_reset_requested', (string)$id, $GLOBALS['request_id'] ?? 'cli');
        $db->commit();
    } catch (\Throwable $caughtError) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $caughtError;
    }
    $url = $config['app_url'] . 'src/views/register-restore/restore-password.php' . (I18n::locale() === 'en' ? '?lang=en' : '') . '#token=' . $token;
    $deliver = static function () use ($db, $config, $id, $email, $url, $hash, $transport): void {
        try {
            if ($transport !== null) {
                mail_send_reset($config, $email, $url, $transport);
            } else {
                mail_queue_reset($config, (string)$id, $email, $url, $hash);
            }
        } catch (\Throwable $error) {
            // A failed older delivery must never delete a newer request's token.
            $stmt = $db->prepare('DELETE FROM password_reset_tokens WHERE user_id=? AND token_hash=?');
            $stmt->execute([$id, $hash]);
            error_log('Hospital password recovery delivery failed; request ' . ($GLOBALS['request_id'] ?? 'cli'));
        }
    };
    if ($defer !== null) {
        $defer($deliver);
    } else {
        $deliver();
    }
    return $result;
}

function password_reset_with_token(PDO $db, array $config, array $input): void
{
    (new RateLimiter($db, $config['app_key']))->consume('reset-password-ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'local'), 20, 900);
    $invalid = static fn (): ApiException => new ApiException(422, 'RESET_TOKEN_INVALID', I18n::t('recovery.invalid_token'));
    $token = $input['token'] ?? null;
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
        throw $invalid();
    }
    $tokenHash = hash('sha256', $token);
    $preparedStatement = $db->prepare('SELECT user_id FROM password_reset_tokens WHERE token_hash=?');
    $preparedStatement->execute([$tokenHash]);
    $id = $preparedStatement->fetchColumn();
    if ($id === false) {
        throw $invalid();
    }
    $hash = password_password_hash($config, $input['new_password'] ?? null);
    $db->beginTransaction();
    try {
        $user = users_by_id($db, (string)$id, true);
        $preparedStatement = $db->prepare('SELECT auth_version,expires_at FROM password_reset_tokens WHERE user_id=? AND token_hash=? FOR UPDATE');
        $preparedStatement->execute([$id, $tokenHash]);
        $reset = $preparedStatement->fetch();
        if (
            !password_recovery_eligible($user)
            || !$reset
            || (int)$reset['auth_version'] !== (int)$user['auth_version']
            || strtotime($reset['expires_at'] . ' UTC') <= time()
        ) {
            throw $invalid();
        }
        password_replace_password($db, (string)$id, $hash, null, 'account.password_reset');
        $db->commit();
    } catch (\Throwable $caughtError) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $caughtError;
    }
}

function password_change(PDO $db, array $config, ?string $actor, string $id, array $input, bool $admin = false): void
{
    if ($actor === null && (PHP_SAPI !== 'cli' || !$admin)) {
        throw new ApiException(403, 'FORBIDDEN', \Hospital\I18n::t("errors.operacion_no_permitida"));
    }
    if (!$admin && $actor !== $id) {
        throw new ApiException(403, 'FORBIDDEN', \Hospital\I18n::t("errors.operacion_no_permitida"));
    }
    $limiter = new RateLimiter($db, $config['app_key']);
    $bucket = 'current-password-failure:' . $id . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'local');
    if (!$admin) {
        $limiter->reserve([[$bucket, 5, 900]]);
    }
    $credentialsFailed = false;
    try {
        $hash = password_password_hash($config, $input['new_password'] ?? null);
        if ($admin && $actor === $id) {
            throw new ApiException(422, 'USE_PASSWORD_CHANGE', \Hospital\I18n::t("errors.cambia_tu_contrasena_desde_configuracion"));
        }
        $db->beginTransaction();
        $acting = $actor === null ? null : users_by_id($db, $actor, true);
        if (
            $actor !== null
            && (!$acting
            || $acting['status'] !== 'active'
            || $acting['staff_status'] !== 'active'
            || (int)$acting['auth_version'] !== (int)($_SESSION['auth_version'] ?? 0))
        ) {
            throw new ApiException(401, 'SESSION_EXPIRED', \Hospital\I18n::t("errors.la_sesion_vencio"));
        }
        if ($admin && $actor !== null && !user_has_role($db, $actor, 'admin')) {
            throw new ApiException(403, 'FORBIDDEN', \Hospital\I18n::t("errors.se_requiere_un_administrador"));
        }
        $user = $actor === $id ? $acting : users_by_id($db, $id, true);
        if (!$user) {
            throw new ApiException(404, 'USER_NOT_FOUND', \Hospital\I18n::t("errors.no_se_encontro_la_cuenta"));
        }
        if ($admin) {
            if ((int)$user['version'] !== \Hospital\Http\Request::version($input['version'] ?? null)) {
                throw new ApiException(409, 'VERSION_CONFLICT', \Hospital\I18n::t("errors.la_cuenta_cambio_volve_a_abrirla"));
            }
            if ($user['status'] === 'pending') {
                throw new ApiException(409, 'ACCOUNT_PENDING', \Hospital\I18n::t("errors.aproba_primero_la_cuenta"));
            }
        } elseif (
            !is_string($input['current_password'] ?? null)
            || strlen($input['current_password']) > 72
            || str_contains($input['current_password'], "\0")
            || !password_verify($input['current_password'], $user['password_hash'])
        ) {
            $credentialsFailed = true;
            throw new ApiException(422, 'INVALID_CURRENT_PASSWORD', \Hospital\I18n::t("errors.la_contrasena_actual_no_coincide"));
        }
        password_replace_password($db, $id, $hash, $actor, $admin ? 'account.password_reset' : 'account.password_changed');
        if (!$admin) {
            $limiter->refund($bucket);
        }
        $db->commit();
        $limiter->confirmRefund();
    } catch (\Throwable $caughtError) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if (!$credentialsFailed) {
            auth_release_attempt($limiter);
        }
        throw $caughtError;
    }
}
