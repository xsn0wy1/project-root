<?php
declare(strict_types=1);
require_once __DIR__ . '/shell.php';
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function auth_page_open(string $titleKey, string $title, string $src, string $assets, bool $recovery = false): void
{
    page_security_headers();
    $style = $recovery ? 'pass-restore' : 'log-reg';
    ?>
<!DOCTYPE html>
<html lang="<?= page_language() ?>" data-theme="dark" data-theme-styles="<?= $style ?> auth-interactions">
<head>
  <script src="<?= $src ?>api-connection/theme.js?v=20261019-light"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if ($recovery): ?>
  <meta name="referrer" content="no-referrer">
<?php endif; ?>
  <title data-i18n="<?= page_escape($titleKey) ?>"><?= page_escape($title) ?></title>
  <link rel="stylesheet" href="<?= $src ?>css/login-register/<?= $style ?>.css?v=20261019-auth-effects">
  <link rel="stylesheet" href="<?= $src ?>css/login-register/auth-interactions.css?v=20261019-recovery-field">
  <link rel="icon" type="image/x-icon" href="<?= $assets ?>hospital-gradient.ico">
  <script src="<?= $src ?>api-connection/i18n.js?v=20261019-i18n" defer></script>
  <script src="<?= $src ?>api-connection/api-client.js?v=20261019-i18n" defer></script>
<?php if ($recovery): ?>
  <script src="<?= $src ?>js/login-register/recovery.js?v=20261019-recovery" defer></script>
<?php else: ?>
  <script src="<?= $src ?>api-connection/auth-session.js?v=20261019-light" defer></script>
<?php endif; ?>
  <link rel="stylesheet" href="<?= $src ?>css/theme.css?v=ui-review-2">
  <link rel="stylesheet" href="<?= $src ?>css/dropdowns.css?v=ui-review-2">
  <link rel="stylesheet" href="<?= $src ?>css/responsive.css?v=ui-review-2">
  <script src="<?= $src ?>js/dropdowns.js?v=ui-repairs" defer></script>
</head>
<body>
  <div class="appearance-switch" role="group" aria-label="Apariencia" data-i18n-aria-label="theme.appearance"><button type="button" data-public-theme="dark" aria-pressed="true" aria-label="Tema oscuro" data-i18n-aria-label="settings.tema_oscuro" title="Tema oscuro" data-i18n-title="settings.tema_oscuro"><img src="<?= $assets ?>Icons/moon-icon.svg" alt=""></button><button type="button" data-public-theme="light" aria-pressed="false" aria-label="Tema claro" data-i18n-aria-label="settings.tema_claro" title="Tema claro" data-i18n-title="settings.tema_claro"><img src="<?= $assets ?>Icons/sun-icon.svg" alt=""></button></div>
<?php
}
