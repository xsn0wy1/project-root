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
  <script src="<?= asset_url($src . 'api-connection/theme.js') ?>"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if ($recovery): ?>
  <meta name="referrer" content="no-referrer">
<?php endif; ?>
  <title data-i18n="<?= page_escape($titleKey) ?>"><?= page_escape($title) ?></title>
  <link rel="stylesheet" href="<?= asset_url($src . 'css/base.css') ?>">
  <link rel="stylesheet" href="<?= asset_url($src . 'css/login-register/' . $style . '.css') ?>">
  <link rel="stylesheet" href="<?= asset_url($src . 'css/login-register/auth-interactions.css') ?>">
  <link rel="icon" type="image/x-icon" href="<?= $assets ?>hospital-gradient.ico">
  <script src="<?= asset_url($src . 'api-connection/i18n.js') ?>" defer></script>
  <script src="<?= asset_url($src . 'api-connection/api-client.js') ?>" defer></script>
<?php if ($recovery): ?>
  <script src="<?= asset_url($src . 'js/login-register/recovery.js') ?>" defer></script>
<?php else: ?>
  <script src="<?= asset_url($src . 'api-connection/auth-session.js') ?>" defer></script>
<?php endif; ?>
  <link rel="stylesheet" href="<?= asset_url($src . 'css/theme.css') ?>">
  <link rel="stylesheet" href="<?= asset_url($src . 'css/dropdowns.css') ?>">
  <link rel="stylesheet" href="<?= asset_url($src . 'css/responsive.css') ?>">
  <script src="<?= asset_url($src . 'js/dropdowns.js') ?>" defer></script>
</head>
<body>
  <div class="appearance-switch" role="group" aria-label="Apariencia" data-i18n-aria-label="theme.appearance"><button type="button" data-public-theme="dark" aria-pressed="true" aria-label="Tema oscuro" data-i18n-aria-label="settings.tema_oscuro" title="Tema oscuro" data-i18n-title="settings.tema_oscuro"><img src="<?= $assets ?>Icons/moon-icon.svg" alt=""></button><button type="button" data-public-theme="light" aria-pressed="false" aria-label="Tema claro" data-i18n-aria-label="settings.tema_claro" title="Tema claro" data-i18n-title="settings.tema_claro"><img src="<?= $assets ?>Icons/sun-icon.svg" alt=""></button></div>
<?php
}
