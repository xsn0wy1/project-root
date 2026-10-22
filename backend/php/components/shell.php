<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/autoload.php';

const ASSET_VERSION = '20261022-frontend';

function page_language(): string
{
    if (isset($GLOBALS['page_locale'])) return $GLOBALS['page_locale'];
    $language = $_GET['lang'] ?? null;
    if (in_array($language, ['es', 'es-UY', 'en'], true)) return $language === 'en' ? 'en' : 'es-UY';
    return Hospital\I18n::locale();
}

function page_security_headers(): void
{
    header("Content-Security-Policy: frame-ancestors 'self'");
    header('X-Frame-Options: SAMEORIGIN');
    header('Content-Language: ' . page_language());
}

function page_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/** HTML-safe asset URL; update this version when publishing changed browser assets. */
function asset_url(string $path): string
{
    $separator = strpos($path, '?') === false ? '?' : '&';
    return page_escape($path . $separator . 'v=' . ASSET_VERSION);
}

/** Shared staff head and navigation; each view keeps its content and dialogs. */
function staff_page_head(array $page): void
{
    page_security_headers();
    $module = $page['module'];
    $src = $page['src_prefix'] ?? '../../';
    $assets = $page['assets_prefix'] ?? '../../../assets/';
    $styles = $page['stylesheets'] ?? [$page['stylesheet']];
    $font = $page['font_stylesheet'] ?? 'inclusive-regular';
    ?>
<!DOCTYPE html>
<html lang="<?= page_language() ?>" data-theme="dark" data-theme-styles="<?= page_escape($page['theme_styles'] ?? 'main-menu ' . $module) ?>">
<head>
  <script src="<?= asset_url($src . 'api-connection/theme.js') ?>"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php foreach ($page['meta'] ?? [] as $name => $content): ?>
  <meta name="<?= page_escape($name) ?>" content="<?= page_escape($content) ?>">
<?php endforeach; ?>
  <title data-i18n="<?= page_escape($page['title_key']) ?>"><?= page_escape($page['title']) ?></title>
  <link rel="icon" type="image/x-icon" href="<?= $assets ?>hospital-dark.ico">
  <link rel="stylesheet" href="<?= asset_url($src . 'css/base.css') ?>">
<?php foreach ($page['vendor_styles'] ?? [] as $style): ?>
  <link rel="stylesheet" href="<?= asset_url($style['href']) ?>" integrity="<?= page_escape($style['integrity']) ?>" crossorigin="anonymous">
<?php endforeach; ?>
  <link rel="stylesheet" href="<?= asset_url($src . 'css/main-menu.css') ?>">
<?php foreach ($styles as $style): ?>
  <link rel="stylesheet" href="<?= asset_url($src . 'css/' . $style) ?>">
<?php endforeach; ?>
  <link rel="stylesheet" href="<?= asset_url($assets . 'vendor/fonts/' . $font . '.css') ?>">
  <script src="<?= asset_url($src . 'api-connection/i18n.js') ?>" defer></script>
  <script src="<?= asset_url($src . 'api-connection/api-client.js') ?>" defer></script>
  <script src="<?= asset_url($src . 'api-connection/auth-session.js') ?>" defer></script>
<?php if ($page['main_menu'] ?? true): ?>
  <script src="<?= asset_url($src . 'js/main-menu.js') ?>" defer></script>
<?php endif; ?>
<?php if ($page['search'] ?? true): ?>
  <script src="<?= asset_url($src . 'api-connection/universal-search.js') ?>" defer></script>
<?php endif; ?>
  <script src="<?= asset_url($src . 'js/ui.js') ?>" defer></script>
<?php if (!empty($page['script'])): ?>
  <script src="<?= asset_url($src . 'js/' . $page['script']) ?>" defer></script>
<?php endif; ?>
<?php foreach ($page['extra_scripts'] ?? [] as $script): ?>
  <script src="<?= asset_url(is_array($script) ? $script['src'] : $script) ?>"<?= is_array($script) ? ' integrity="' . page_escape($script['integrity']) . '" crossorigin="anonymous"' : '' ?> defer></script>
<?php endforeach; ?>
  <link rel="stylesheet" href="<?= asset_url($src . 'css/theme.css') ?>">
  <link rel="stylesheet" href="<?= asset_url($src . 'css/dropdowns.css') ?>">
  <link rel="stylesheet" href="<?= asset_url($src . 'css/responsive.css') ?>">
  <script src="<?= asset_url($src . 'js/dropdowns.js') ?>" defer></script>
</head>
<body<?= ($page['body_class'] ?? ($module === 'management' ? '' : $module . '-page')) === '' ? '' : ' class="' . page_escape($page['body_class'] ?? $module . '-page') . '"' ?>>
<?php
}

function staff_page_open(array $page): void
{
    staff_page_head($page);
    $module = $page['module'];
    $assets = $page['assets_prefix'] ?? '../../../assets/';
    ?>
<?php if (!empty($page['background'])): ?>
  <div class="page-background"></div>
  <div class="page-glow"></div>
<?php endif; ?>
  <main class="<?= page_escape($page['main_class'] ?? 'app-shell') ?>">
<?php
    staff_sidebar($page['described_nav'], $module === 'management', $page['nav_section'] ?? 'management', $assets);
}

function staff_sidebar(bool $described, bool $hub, string $section = 'management', string $assets = '../../../assets/'): void
{
    $document = in_array($section, ['documents', 'document-form', 'document-viewer'], true);
    $transfer = in_array($section, ['transfers', 'transfer-form', 'transfer-view'], true);
    $survey = $section === 'surveys';
    $nested = $document || $transfer || $survey;
    $root = in_array($section, ['home', 'settings'], true);
    $prefix = $root ? '' : '../';
    $active = 4;
    if ($section === 'home') $active = 0;
    elseif ($section === 'settings') $active = 5;
    elseif ($document) $active = 1;
    elseif ($transfer) $active = 2;
    elseif ($survey) $active = 3;
    $documentsUrl = $prefix . 'documents/documents.php';
    if ($document) $documentsUrl = $section === 'documents' ? '#' : 'documents.php';
    $transfersUrl = $prefix . 'traces/trace.php';
    if ($transfer) $transfersUrl = $section === 'transfers' ? '#' : 'trace.php';
    $managementPrefix = '';
    if ($root) $managementPrefix = 'management/';
    elseif ($nested) $managementPrefix = '../management/';
    $links = [
        [$section === 'home' ? '#' : $prefix . 'main-menu.php', 'house-icon.svg', 'main_menu.inicio', 'Inicio'],
        [$documentsUrl, 'folder-icon.svg', 'main_menu.documentos', 'Documentos'],
        [$transfersUrl, 'location-icon.svg', 'main_menu.ambulancias', 'Ambulancias'],
        [$survey ? 'survey.php' : $prefix . 'surveys/survey.php', 'survey-icon.svg', 'main_menu.encuestas', 'Encuestas'],
        [$hub ? '#' : $managementPrefix . 'management.php', 'database-cog.svg', $root || $document || $transfer ? 'main_menu.gestion' : 'trace_map.gestion', $root || $document || $transfer ? 'Gestion' : 'Gestión'],
        [$section === 'settings' ? '#' : $prefix . 'settings.php', 'settings-icon.svg', 'main_menu.configuracion', 'Configuración'],
    ];
    ?>
    <aside class="sidebar">
      <button type="button" class="sidebar-action" aria-label="Abrir menú" data-i18n-aria-label="main_menu.abrir_menu"<?= !$described || $section === 'document-form' ? ' aria-expanded="false"' : '' ?>><span></span><span></span><span></span></button>
      <nav class="sidebar-nav"<?= $described ? '' : ' aria-label="Navegación principal" data-i18n-aria-label="trace_map.navegacion_principal"' ?>>
        <div class="nav-indicator"></div>
<?php foreach ($links as $index => [$url, $icon, $key, $label]): ?>
<?php if ($index === 4 && in_array($section, ['document-form', 'transfer-form'], true)) continue; ?>
<?php if ($index === 5): ?>
        <div class="nav-spacer"></div>
<?php endif; ?>
        <a href="<?= page_escape($url) ?>" class="nav-item<?= $index === $active ? ' active' : '' ?>"<?= $index === $active && !$hub && !$root && !in_array($section, ['documents', 'transfers', 'transfer-form'], true) ? ' aria-current="page"' : '' ?>><img src="<?= $assets ?>Icons/<?= $icon ?>" alt="<?= $described ? $label : '' ?>"<?= $described ? ' data-i18n-alt="' . $key . '"' : '' ?>><span data-i18n="<?= $key ?>"><?= $label ?></span></a>
<?php endforeach; ?>
      </nav>
    </aside>
<?php
}

/** Preserve full quick-search markup and the simpler existing catalog/header variants. */
function staff_header(string $variant, string $assets = '../../../assets/'): void
{
    $quick = $variant === 'quick';
    $modules = $variant === 'modules';
    $document = in_array($variant, ['documents', 'document-form'], true);
    $transfer = in_array($variant, ['transfers', 'transfer-form'], true);
    $described = $variant !== 'plain';
    $searchPlaceholder = 'Buscar documento...';
    $searchPlaceholderKey = 'documents.buscar_documento';
    if ($transfer) {
        if ($variant === 'transfers') {
            $searchPlaceholder = 'Buscar una acción o sección...';
            $searchPlaceholderKey = 'main_menu.buscar_una_accion_o_seccion';
        } else {
            $searchPlaceholder = 'Escribe algo...';
            $searchPlaceholderKey = 'trace_new.escribe_algo';
        }
    }
    ?>
    <header class="topbar<?= $quick ? ' quick-search-topbar' : '' ?>">
      <div class="brand"><img src="<?= $assets ?>Icons/hospital-logo.png" alt="<?= $described ? 'Hospital de Clínicas' : '' ?>"<?= $described ? ' data-i18n-alt="main_menu.hospital_de_clinicas"' : '' ?>><span data-i18n="main_menu.hospital_de_clinicas_2">HOSPITAL DE CLINICAS</span></div>
      <div class="search-wrapper"<?= $quick ? ' role="search"' : '' ?>>
<?php if ($document || $transfer): ?>
        <input type="<?= $transfer || $variant === 'document-form' ? 'search' : 'text' ?>"<?= $transfer ? ' id="' . ($variant === 'transfers' ? 'quickSearch' : 'transferSearch') . '"' : '' ?> class="search-input" placeholder="<?= $searchPlaceholder ?>" data-i18n-placeholder="<?= $searchPlaceholderKey ?>"<?= $transfer || $variant === 'document-form' ? ' autocomplete="off"' : '' ?>>
        <button type="button" class="topbar-action search-action" aria-label="Buscar" data-i18n-aria-label="trace_new.buscar"<?= $variant === 'document-form' ? ' aria-expanded="false"' : '' ?>><img src="<?= $assets ?>Icons/search-icon.svg" alt="" aria-hidden="true"></button>
<?php else: ?>
        <input type="<?= $quick ? 'text' : 'search' ?>"<?= $quick ? ' id="quick-search"' : '' ?> class="search-input" placeholder="<?= $modules ? 'Buscar módulo...' : 'Buscar una acción o sección...' ?>" data-i18n-placeholder="<?= $modules ? 'management.buscar_modulo' : 'main_menu.buscar_una_accion_o_seccion' ?>" aria-label="<?= $modules ? 'Buscar módulo de gestión' : 'Buscar una acción o sección' ?>" data-i18n-aria-label="<?= $modules ? 'management.buscar_modulo_de_gestion' : 'main_menu.buscar_una_accion_o_seccion_2' ?>"<?= $quick ? ' role="combobox" aria-autocomplete="list" aria-controls="search-results-list" aria-expanded="false" autocomplete="off" tabindex="-1"' : '' ?>>
        <button type="button" class="topbar-action search-action" aria-label="<?= $modules ? 'Buscar' : 'Abrir búsqueda' ?>" data-i18n-aria-label="<?= $modules ? 'trace_new.buscar' : 'main_menu.abrir_busqueda' ?>"<?= $quick ? ' aria-controls="quick-search"' : '' ?> aria-expanded="false"><img src="<?= $assets ?>Icons/search-icon.svg" alt=""<?= $quick || $modules ? ' aria-hidden="true"' : '' ?>></button>
<?php endif; ?>
      </div>
<?php if ($quick): ?>
      <div class="search-results" id="search-results" hidden>
        <div class="search-results-heading"><span id="search-results-title" data-i18n="main_menu.accesos_rapidos">Accesos rápidos</span></div>
        <ul id="search-results-list" class="search-results-list" role="listbox" aria-label="Resultados de búsqueda" data-i18n-aria-label="main_menu.resultados_de_busqueda"></ul>
        <p class="search-results-footer" data-i18n="main_menu.haz_clic_en_una_opcion_disponible_para_ir_a_esa_seccion">Haz clic en una opción disponible para ir a esa sección.</p>
      </div>
      <span id="search-status" class="visually-hidden" role="status" aria-live="polite"></span>
<?php endif; ?>
    </header>
<?php
}

function metric_card(string $id, string $icon, string $labelKey, string $label, string $descriptionKey, string $description): void
{
    ?>
<article class="metric-card"><span class="metric-icon"><img src="../../../assets/Icons/<?= page_escape($icon) ?>" alt="" aria-hidden="true"></span><div><strong id="<?= page_escape($id) ?>">0</strong><span data-i18n="<?= page_escape($labelKey) ?>"><?= page_escape($label) ?></span><small data-i18n="<?= page_escape($descriptionKey) ?>"><?= page_escape($description) ?></small></div></article>
<?php
}
