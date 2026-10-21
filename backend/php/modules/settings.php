<?php
declare(strict_types=1);

use Hospital\Http\ApiException;
use Hospital\Http\Request;

// Profile, preferences and private notifications.
function settings_text($value, string $field, int $max, bool $blank = true) : string
{
    if (!is_string($value)) throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.campo_no_valido"), [$field => \Hospital\I18n::t("errors.se_esperaba_texto")]);
    $value = preg_replace('/\s+/u', ' ', trim($value));
    if ($value === null || (!$blank && $value === '') || mb_strlen($value, 'UTF-8') > $max || preg_match('/\p{C}/u', $value)) {
        throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.campo_no_valido"), [$field => \Hospital\I18n::t("errors.revisa_el_texto_y_su_longitud")]);
    }
    return $value;
}

function settings_lock(PDO $db, string $id, $version) : array
{
    if ((!is_int($version) && !is_string($version)) || !preg_match('/^[1-9][0-9]{0,9}$/', (string) $version)) throw new ApiException(422, 'VERSION_REQUIRED', \Hospital\I18n::t("errors.se_requiere_la_version_actual"));
    $user = users_by_id($db, $id, true);
    if (!$user || $user['status'] !== 'active' || $user['staff_status'] !== 'active' || (int)$user['auth_version']!==(int)($_SESSION['auth_version']??0)) throw new ApiException(401, 'UNAUTHENTICATED', \Hospital\I18n::t("errors.la_cuenta_no_esta_activa"));
    $stmt = $db->prepare('SELECT * FROM user_settings WHERE user_id = ? FOR UPDATE'); $stmt->execute([$id]);
    $settings = $stmt->fetch();
    if (!$settings || (int) $settings['version']+(int)$user['staff_version'] !== (int) $version) throw new ApiException(409, 'VERSION_CONFLICT', \Hospital\I18n::t("errors.la_configuracion_cambio_en_otra_sesion"));
    return $user;
}

function settings_changed(PDO $db, string $id, string $action) : void
{
    $stmt = $db->prepare('UPDATE user_settings SET version = version + 1 WHERE user_id = ?'); $stmt->execute([$id]);
    users_audit($db, $id, $action, $id, $GLOBALS['request_id']);
}

function settings_profile(PDO $db, string $id, array $input) : void
{
    if (count($input) < 2) throw new ApiException(422, 'EMPTY_UPDATE', \Hospital\I18n::t("errors.no_se_indicaron_cambios"));
    $name = array_key_exists('full_name', $input) ? settings_text($input['full_name'], 'full_name', 150) : null;
    $email = null;
    if (array_key_exists('email', $input)) {
        $email = strtolower(settings_text($input['email'], 'email', 254, false));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.email_no_valido"), ['email' => \Hospital\I18n::t("errors.ingresa_un_email_valido")]);
    }
    $facility = null;
    if (array_key_exists('default_facility_id', $input) && $input['default_facility_id'] !== null) $facility = Request::id($input['default_facility_id']);
    $specialties = null;
    if (array_key_exists('specialties', $input)) {
        $list = $input['specialties'];
        if (!is_array($list) || count($list) > 10 || ($list !== [] && array_keys($list) !== range(0, count($list) - 1))) throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.usa_una_lista_de_hasta_10_especializaciones"));
        $specialties = [];
        foreach ($input['specialties'] as $label) {
            $label = settings_text($label, 'specialties', 80, false);
            $specialties[mb_strtolower($label, 'UTF-8')] = $label;
        }
    }
    $db->beginTransaction();
    try {
        $user = settings_lock($db, $id, $input['version'] ?? null);
        if ($name !== null) {
            // The locked shared staff row also serializes doctor/personnel creation.
            // Include archived profiles and legacy crew that have only staff_roles.
            $stmt = $db->prepare("SELECT EXISTS(SELECT 1 FROM doctor_profiles WHERE staff_id = ?) OR EXISTS(SELECT 1 FROM personnel_profiles WHERE staff_id = ?) OR EXISTS(SELECT 1 FROM staff_roles WHERE staff_id = ? AND role_code IN ('driver','copilot'))");
            $stmt->execute([$user['staff_id'], $user['staff_id'], $user['staff_id']]);
            if ($stmt->fetchColumn()) $name = settings_text($name, 'full_name', 100, false);
        }
        if ($email !== null) {
            if ($email !== $user['email']) {
                $stmt = $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?'); $stmt->execute([$id]);
            }
            $stmt = $db->prepare('UPDATE users SET email_verified_at = IF(email = ?, email_verified_at, NULL), email = ?, version = version + 1 WHERE id = ?'); $stmt->execute([$email, $email, $id]);
        }
        if ($name !== null) {
            $stmt = $db->prepare('UPDATE users SET display_name = ?, version = version + 1 WHERE id = ?'); $stmt->execute([$name, $id]);
            $stmt = $db->prepare('UPDATE staff SET full_name = ? WHERE id = ?'); $stmt->execute([$name, $user['staff_id']]);
        }
        if (array_key_exists('default_facility_id', $input)) {
            if ($facility !== null) {
                $stmt = $db->prepare("SELECT id FROM facilities WHERE id = ? AND status = 'active' AND availability = 'active' LOCK IN SHARE MODE"); $stmt->execute([$facility]);
                if (!$stmt->fetchColumn()) throw new ApiException(422, 'FACILITY_UNAVAILABLE', \Hospital\I18n::t("errors.el_centro_no_esta_disponible"));
            }
            $stmt = $db->prepare('UPDATE user_settings SET default_facility_id = ? WHERE user_id = ?'); $stmt->execute([$facility, $id]);
        }
        if ($specialties !== null) {
            $stmt = $db->prepare('DELETE FROM staff_specialties WHERE staff_id = ?'); $stmt->execute([$user['staff_id']]);
            foreach ($specialties as $label) {
                $stmt = $db->prepare('INSERT INTO specialties (name) VALUES (?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'); $stmt->execute([$label]);
                $specialtyId = $db->lastInsertId();
                $stmt = $db->prepare("SELECT id FROM specialties WHERE id = ? AND status = 'active' LOCK IN SHARE MODE"); $stmt->execute([$specialtyId]);
                if (!$stmt->fetchColumn()) throw new ApiException(422, 'SPECIALTY_UNAVAILABLE', \Hospital\I18n::t("errors.la_especializacion_no_esta_disponible"));
                $stmt = $db->prepare('INSERT IGNORE INTO staff_specialties (staff_id, specialty_id) VALUES (?, ?)'); $stmt->execute([$user['staff_id'], $specialtyId]);
            }
        }
        if ($name !== null || $specialties !== null) {
            $stmt = $db->prepare('UPDATE staff SET version = version + 1 WHERE id = ?'); $stmt->execute([$user['staff_id']]);
        }
        settings_changed($db, $id, 'settings.profile_updated'); $db->commit();
    } catch (\PDOException $error) {
        $db->rollBack();
        if (($error->errorInfo[1] ?? 0) === 1062) throw new ApiException(409, 'ACCOUNT_CONFLICT', \Hospital\I18n::t("errors.los_datos_ya_estan_en_uso"));
        throw $error;
    } catch (\Throwable $error) { $db->rollBack(); throw $error; }
}

function settings_preferences(PDO $db, string $id, array $input) : void
{
    if (count($input) < 2) throw new ApiException(422, 'EMPTY_UPDATE', \Hospital\I18n::t("errors.no_se_indicaron_cambios"));
    $fields = [];
    foreach (['locale' => ['es-UY', 'en'], 'theme' => ['dark', 'light']] as $key => $values) {
        if (array_key_exists($key, $input)) {
            if (!in_array($input[$key], $values, true)) throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.preferencia_no_valida"));
            $fields[$key] = $input[$key];
        }
    }
    foreach (['photo_visible_to_staff', 'optional_diagnostics', 'security_notifications', 'automatic_logout'] as $key) {
        if (array_key_exists($key, $input)) {
            if (!is_bool($input[$key])) throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.se_esperaba_un_booleano"));
            $fields[$key] = (int) $input[$key];
        }
    }
    $db->beginTransaction();
    try {
        settings_lock($db, $id, $input['version'] ?? null);
        // Keys come only from the fixed allowlist above.
        $sql = implode(', ', array_map(static fn(string $key): string => $key . ' = ?', array_keys($fields)));
        $stmt = $db->prepare('UPDATE user_settings SET ' . $sql . ' WHERE user_id = ?'); $stmt->execute([...array_values($fields), $id]);
        if (($fields['optional_diagnostics'] ?? null) === 0) { $stmt = $db->prepare('DELETE FROM user_diagnostics WHERE user_id = ?'); $stmt->execute([$id]); }
        settings_changed($db, $id, 'settings.preferences_updated'); $db->commit();
    } catch (\Throwable $error) { $db->rollBack(); throw $error; }
}

function settings_notifications(PDO $db, string $id) : array
{
    $stmt = $db->prepare('SELECT n.id, n.kind, n.created_at, n.read_at FROM user_notifications n JOIN user_settings s ON s.user_id = n.user_id WHERE n.user_id = ? AND s.security_notifications = 1 ORDER BY n.id DESC LIMIT 20'); $stmt->execute([$id]);
    return array_map(static fn(array $row): array => ['id' => (string) $row['id'], 'kind' => $row['kind'], 'created_at' => users_date($row['created_at']), 'read_at' => users_date($row['read_at'])], $stmt->fetchAll());
}

function settings_read_notifications(PDO $db, string $id, string $through) : void
{
    $stmt = $db->prepare('UPDATE user_notifications SET read_at = COALESCE(read_at, UTC_TIMESTAMP()) WHERE user_id = ? AND id <= ?'); $stmt->execute([$id, $through]);
}

function settings_diagnostics(PDO $db, string $id, $duration) : void
{
    if (!is_int($duration) || $duration < 0 || $duration > 120000) throw new ApiException(422, 'VALIDATION_FAILED', \Hospital\I18n::t("errors.duracion_no_valida"));
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT optional_diagnostics FROM user_settings WHERE user_id = ? FOR UPDATE'); $stmt->execute([$id]);
        if (!(bool) $stmt->fetchColumn()) throw new ApiException(403, 'DIAGNOSTICS_DISABLED', \Hospital\I18n::t("errors.el_analisis_opcional_esta_desactivado"));
        $stmt = $db->prepare('INSERT INTO user_diagnostics (user_id, sample_count, total_load_ms, last_recorded_at) VALUES (?, 1, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE sample_count = sample_count + 1, total_load_ms = total_load_ms + VALUES(total_load_ms), last_recorded_at = UTC_TIMESTAMP()'); $stmt->execute([$id, $duration]);
        $db->commit();
    } catch (\Throwable $error) { $db->rollBack(); throw $error; }
}

// Private, normalized profile images.
function avatar_directory(array $config, bool $create = false) : string
{
    return (new \Hospital\Storage\PrivateStorage($config))->directory('users', $create);
}

function avatar_path(array $config, string $key) : string
{
    if (!preg_match('/^[a-f0-9]{64}$/', $key)) throw new \RuntimeException('Invalid storage key.');
    return avatar_directory($config) . '/' . $key . '.png';
}

function avatar_normalize(array $file) : string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!is_int($error) || $error !== UPLOAD_ERR_OK) throw new ApiException(in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 413 : 422, 'INVALID_UPLOAD', \Hospital\I18n::t("errors.no_se_recibio_una_imagen_valida"));
    if (!is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new ApiException(422, 'INVALID_UPLOAD', \Hospital\I18n::t("errors.no_se_recibio_una_imagen_valida"));
    if (filesize($file['tmp_name']) > 5 * 1024 * 1024) throw new ApiException(413, 'FILE_TOO_LARGE', \Hospital\I18n::t("errors.la_imagen_supera_5_mib"));
    $bytes = file_get_contents($file['tmp_name']);
    $info = @getimagesizefromstring($bytes);
    if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true) || $info[0] > 4096 || $info[1] > 4096 || $info[0] * $info[1] > 8000000) throw new ApiException(415, 'INVALID_IMAGE', \Hospital\I18n::t("errors.usa_una_imagen_png_jpeg_o_webp_de_dimensiones_permitidas"));
    if (!function_exists('imagecreatefromstring')) throw new \RuntimeException('GD extension required.');
    $source = @imagecreatefromstring($bytes);
    if (!$source) throw new ApiException(415, 'INVALID_IMAGE', \Hospital\I18n::t("errors.no_se_pudo_decodificar_la_imagen"));
    $scale = min(1, 512 / max($info[0], $info[1]));
    $width = max(1, (int) round($info[0] * $scale)); $height = max(1, (int) round($info[1] * $scale));
    $image = imagecreatetruecolor($width, $height); imagealphablending($image, false); imagesavealpha($image, true);
    imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
    ob_start(); imagepng($image, null, 6); $normalized = ob_get_clean();
    imagedestroy($source); imagedestroy($image);
    if (!is_string($normalized) || $normalized === '') throw new \RuntimeException('Image encoding failed.');
    return $normalized;
}

function avatar_replace(PDO $db, array $config, string $id, $version, ?array $file) : void
{
    $newPath = null; $key = null; $bytes = null;
    if ($file !== null) {
        $bytes = avatar_normalize($file); $key = bin2hex(random_bytes(32));
        $newPath = avatar_directory($config, true) . '/' . $key . '.png';
        $oldMask = umask(0077);
        try {
            $handle = fopen($newPath, 'xb');
            if (!$handle) throw new \RuntimeException('Private storage unavailable.');
            try { if (fwrite($handle, $bytes) !== strlen($bytes)) throw new \RuntimeException('Incomplete private file write.'); }
            finally { fclose($handle); }
        } catch (\Throwable $error) {
            if (is_file($newPath)) unlink($newPath);
            throw $error;
        } finally { umask($oldMask); }
    }
    $oldKey = null;
    $db->beginTransaction();
    try {
         settings_lock($db, $id, $version);
        $stmt = $db->prepare('SELECT f.id, f.storage_key FROM user_settings s JOIN stored_files f ON f.id = s.avatar_file_id AND f.owner_user_id = s.user_id WHERE s.user_id = ?'); $stmt->execute([$id]); $old = $stmt->fetch();
        $fileId = null;
        if ($bytes !== null) {
            $stmt = $db->prepare("INSERT INTO stored_files (owner_user_id, storage_key, mime_type, byte_size, sha256) VALUES (?, ?, 'image/png', ?, ?)"); $stmt->execute([$id, $key, strlen($bytes), hash('sha256', $bytes)]); $fileId = $db->lastInsertId();
        }
        $stmt = $db->prepare('UPDATE user_settings SET avatar_file_id = ? WHERE user_id = ?'); $stmt->execute([$fileId, $id]);
        if ($old) { $stmt = $db->prepare("UPDATE stored_files SET state = 'retired' WHERE id = ?"); $stmt->execute([$old['id']]); $oldKey = $old['storage_key']; }
        settings_changed($db, $id, $file === null ? 'settings.photo_deleted' : 'settings.photo_updated'); $db->commit();
    } catch (\Throwable $error) {
        $db->rollBack(); if ($newPath !== null && is_file($newPath)) unlink($newPath); throw $error;
    }
    if ($oldKey !== null) {
        $oldPath = avatar_path($config, $oldKey);
        if (is_file($oldPath) && !@unlink($oldPath)) error_log('Hospital private avatar cleanup deferred.');
    }
}

function avatar_content(PDO $db, array $config, string $viewer, string $target) : array
{
    $stmt = $db->prepare("SELECT f.storage_key, f.byte_size, s.photo_visible_to_staff FROM user_settings s JOIN users u ON u.id = s.user_id JOIN staff st ON st.id = u.staff_id JOIN stored_files f ON f.id = s.avatar_file_id AND f.owner_user_id = u.id AND f.state = 'ready' WHERE u.id = ? AND u.status = 'active' AND st.status = 'active'"); $stmt->execute([$target]); $file = $stmt->fetch();
    if (!$file || ($viewer !== $target && !$file['photo_visible_to_staff'])) throw new ApiException(404, 'PHOTO_NOT_FOUND', \Hospital\I18n::t("errors.no_se_encontro_la_imagen"));
    $path = avatar_path($config, $file['storage_key']);
    if (!is_file($path)) throw new ApiException(404, 'PHOTO_NOT_FOUND', \Hospital\I18n::t("errors.no_se_encontro_la_imagen"));
    return ['path' => $path, 'size' => $file['byte_size']];
}
