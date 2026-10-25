<?php
declare(strict_types=1);

/** Standalone HTML errors; API entry points keep their JSON responses. */
function render_error_page(int $status): void
{
    global $db, $config;
    if (!isset($GLOBALS['request_id']))
        require_once dirname(__DIR__, 3) . '/backend/php/bootstrap.php';
    require_once dirname(__DIR__, 3) . '/backend/php/components/shell.php';
    $status = in_array($status, [403, 404, 500, 503], true) ? $status : 404;
    $user = $GLOBALS['page_user'] ?? null;
    try {
        if (!$user && isset($db, $config, $_SESSION['user_id']))
            $user = auth_require_user($db, $config, false);
        if ($user && !isset($GLOBALS['page_theme'])) {
            $settings = $db->prepare('SELECT locale, theme FROM user_settings WHERE user_id = ?');
            $settings->execute([(string) $user['id']]);
            $saved = $settings->fetch();
            $GLOBALS['page_locale'] = ($saved['locale'] ?? 'es-UY') === 'en' ? 'en' : 'es-UY';
            $GLOBALS['page_theme'] = ($saved['theme'] ?? 'dark') === 'light' ? 'light' : 'dark';
        }
    } catch (Throwable $ignored) {
        // Error pages still work when account preferences cannot be read.
    }
    try {
        if (session_status() === PHP_SESSION_ACTIVE)
            session_write_close();
    } catch (Throwable $ignored) {
        // A failed session write must not prevent the original error from rendering.
    }
    $GLOBALS['page_locale'] = page_language();
    $mount = rtrim($config['base_path'] ?? (getenv('HOSPITAL_BASE_PATH') ?: ''), '/');
    if (!preg_match('#^(?:/[a-zA-Z0-9_-]+)*$#', $mount)) $mount = '';
    $title = match ($status) {
        403 => 'error_page.forbidden_title',
        404 => 'error_page.not_found_title',
        default => 'error_page.server_title',
    };
    $description = match ($status) {
        403 => 'error_page.forbidden_description',
        404 => 'error_page.not_found_description',
        default => 'error_page.server_description',
    };
    $button = $user ? 'error_page.home' : 'error_page.login';
    $target = $mount . ($user ? '/src/views/main-menu.php' : '/index.php');
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    page_security_headers();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') return;
    ?>
<!DOCTYPE html>
<html lang="<?= page_language() ?>" class="i18n-ready" data-theme="<?= $GLOBALS['page_theme'] ?? 'dark' ?>" data-theme-styles="log-reg auth-interactions"<?= isset($GLOBALS['page_theme']) ? '' : ' data-cached-theme' ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="<?= asset_url($mount . '/src/api-connection/theme.js') ?>"></script>
  <title><?= page_escape(Hospital\I18n::t($title)) ?></title>
  <link rel="icon" type="image/x-icon" href="<?= page_escape($mount) ?>/assets/hospital-gradient.ico">
  <link rel="stylesheet" href="<?= asset_url($mount . '/assets/vendor/fonts/inclusive-regular.css') ?>">
  <script src="<?= asset_url($mount . '/src/js/ui.js') ?>" defer></script>
<?php foreach (['base.css', 'login-register/log-reg.css', 'login-register/auth-interactions.css', 'theme.css', 'responsive.css'] as $style): ?>
  <link rel="stylesheet" href="<?= asset_url($mount . '/src/css/' . $style) ?>">
<?php endforeach; ?>
</head>
<body>
  <main class="tarjeta error-card">
    <img src="<?= page_escape($mount) ?>/assets/Icons/alert-icon.svg" alt="" aria-hidden="true">
    <h1><?= page_escape(Hospital\I18n::t($title)) ?></h1>
    <p><?= page_escape(Hospital\I18n::t($description)) ?></p>
    <form action="<?= page_escape($target) ?>" method="get"><button type="submit"><?= page_escape(Hospital\I18n::t($button)) ?></button></form>
<?php if (in_array($status, [500, 503], true)): ?>
    <p><small><?= page_escape(Hospital\I18n::t('error_page.request_id', ['value0' => $GLOBALS['request_id']])) ?></small></p>
<?php endif; ?>
  </main>
</body>
</html>
<?php
}

if (!debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)) {
    $status = (int) ($_SERVER['REDIRECT_STATUS'] ?? 0);
    if (isset($_SERVER['REDIRECT_URL'])) {
        $originalPath = parse_url($_SERVER['REDIRECT_URL'], PHP_URL_PATH) ?: '';
        if ($status === 404 && !str_ends_with($originalPath, '.php')) {
            http_response_code(404);
            return;
        }
        parse_str($_SERVER['REDIRECT_QUERY_STRING'] ?? '', $originalQuery);
        $_GET['lang'] ??= $originalQuery['lang'] ?? null;
    }
    render_error_page(in_array($status, [403, 404, 500, 503], true) ? $status : (int) ($_GET['status'] ?? 404));
}
