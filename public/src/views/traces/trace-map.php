<?php
declare(strict_types=1);
require dirname(__DIR__, 4) . '/backend/php/core/page.php';
require_staff_page('traces/trace-map.php');
require dirname(__DIR__, 4) . '/backend/php/components/shell.php';
$simulation = tracking_status($db);
$historical = false;
if (isset($_GET['transfer'])) {
    try {
        $transfer = transfers_row($db, (string) $GLOBALS['page_user']['id'], Hospital\Http\Request::id($_GET['transfer']));
        $historical = in_array($transfer['status'], ['completed', 'cancelled'], true);
    } catch (Hospital\Http\ApiException $error) {
        // The browser retains its existing inaccessible-transfer fallback.
    }
}
if ($historical) $simulationKey = 'trace_map.historico_simulado';
elseif ($simulation['status'] === 'stopped') $simulationKey = 'trace_map.simulacion_detenida';
elseif ($simulation['stale']) $simulationKey = 'trace_map.simulacion_sin_actualizacion';
elseif ($simulation['status'] === 'paused') $simulationKey = 'trace_map.simulacion_pausada';
else $simulationKey = 'trace_map.simulacion';
staff_page_head([
    'module' => 'trace-map',
    'theme_styles' => 'main-menu trace-map',
    'title_key' => 'trace_map.mapa_de_traslados_hospital_de_clinicas',
    'title' => 'Mapa de Traslados · Hospital de Clínicas',
    'stylesheets' => ['traces/trace-map.css'],
    'font_stylesheet' => 'interface',
    'body_class' => '',
    'background' => false,
    'described_nav' => true,
    'nav_section' => 'transfer-map',
    'extra_scripts' => ['../../js/traces/trace-api.js', ['src' => '../../../assets/vendor/leaflet/leaflet.js', 'integrity' => 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo='], '../../js/traces/trace-map-shared.js', '../../js/traces/trace-map.js'],
    'main_menu' => false,
    'search' => false,
    'meta' => ['theme-color' => '#101732', 'description' => 'Mapa de traslados de ambulancias en Montevideo. Consulta rutas, estados y llegadas estimadas.', 'carto-basemap-key' => 'cb1_4mcs_1_f3754a0a1cbb6bf814c69178'],
    'vendor_styles' => [['href' => '../../../assets/vendor/leaflet/leaflet.css', 'integrity' => 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=']],
]);
?>

  <a class="skip-link" href="#map-heading" data-i18n="trace_map.ir_al_mapa_de_traslados">Ir al mapa de traslados</a>
  <div class="app-shell" id="app-shell">
    <aside class="sidebar" id="navigation" aria-label="Navegación principal" data-i18n-aria-label="trace_map.navegacion_principal">
      <button class="sidebar-action" id="menu-toggle" type="button" aria-label="Abrir menú" data-i18n-aria-label="main_menu.abrir_menu"
        aria-expanded="false" aria-controls="sidebar-links">
        <span></span><span></span><span></span>
      </button>
      <nav class="sidebar-nav" id="sidebar-links" aria-label="Secciones" data-i18n-aria-label="trace_map.secciones" aria-hidden="true" inert>
        <div class="nav-indicator" aria-hidden="true"></div>
        <a class="nav-item" href="../main-menu.php" title="Inicio" data-i18n-title="main_menu.inicio">
          <span class="icon" style="--icon-image: url('../../../assets/Icons/house-icon.svg')" aria-hidden="true"></span><span data-i18n="main_menu.inicio">Inicio</span>
        </a>
        <a class="nav-item" href="../documents/documents.php" title="Documentos" data-i18n-title="main_menu.documentos">
          <span class="icon" style="--icon-image: url('../../../assets/Icons/folder-icon.svg')" aria-hidden="true"></span><span data-i18n="main_menu.documentos">Documentos</span>
        </a>
        <a class="nav-item active" href="trace.php" aria-current="page" title="Ambulancias" data-i18n-title="main_menu.ambulancias">
          <span class="icon" style="--icon-image: url('../../../assets/Icons/location-icon.svg')" aria-hidden="true"></span><span data-i18n="main_menu.ambulancias">Ambulancias</span>
        </a>
        <a class="nav-item" href="../surveys/survey.php" title="Encuestas" data-i18n-title="main_menu.encuestas">
          <span class="icon" style="--icon-image: url('../../../assets/Icons/survey-icon.svg')" aria-hidden="true"></span><span data-i18n="main_menu.encuestas">Encuestas</span>
        </a>
        <a class="nav-item" href="../management/management.php" title="Gestión" data-i18n-title="main_menu.gestion">
          <span class="icon" style="--icon-image: url('../../../assets/Icons/database-cog.svg')" aria-hidden="true"></span><span data-i18n="main_menu.gestion">Gestión</span>
        </a>
        <div class="nav-spacer"></div>
        <a class="nav-item" href="../settings.php" title="Configuración" data-i18n-title="main_menu.configuracion">
          <span class="icon" style="--icon-image: url('../../../assets/Icons/settings-icon.svg')" aria-hidden="true"></span><span data-i18n="main_menu.configuracion">Configuración</span>
        </a>
      </nav>
    </aside>

    <main class="content-card">
      <section class="map-page" aria-labelledby="map-heading">
        <div class="map-toolbar">
          <div class="heading-copy">
            <div class="page-title">
              <h1 id="map-heading" tabindex="-1" data-i18n="trace_map.mapa_de_traslados">Mapa de Traslados</h1>
              <span class="live-indicator<?= !$historical && $simulation['stale'] ? ' stale' : '' ?>"><span class="status-dot"></span><i18n-text data-i18n="<?= $simulationKey ?>"><?= page_escape(Hospital\I18n::t($simulationKey)) ?></i18n-text></span>
            </div>
            <div class="metrics-pill" aria-label="Resumen de traslados" data-i18n-aria-label="trace_map.resumen_de_traslados" id="metrics">
              <span class="metric metric-total"><span class="icon" style="--icon-image: url('../../../assets/Icons/steering-wheel.svg')" aria-hidden="true"></span><strong id="total-count">0</strong> <i18n-text data-i18n="trace_map.traslados">traslados</i18n-text></span>
              <span class="metric"><i class="metric-dot progress-dot"></i><strong id="progress-count">0</strong> <i18n-text data-i18n="trace_map.en_curso">en curso</i18n-text></span>
              <span class="metric"><i class="metric-dot scheduled-dot"></i><strong id="scheduled-count">0</strong> <i18n-text data-i18n="trace_map.programado">programado</i18n-text></span>
              <span class="metric"><i class="metric-dot delayed-dot"></i><strong id="delayed-count">0</strong> <i18n-text data-i18n="trace_map.demorado">demorado</i18n-text></span>
            </div>
          </div>
          <div class="toolbar-actions">
            <a class="toolbar-button return-link" href="trace.php">
              <span class="icon" style="--icon-image: url('../../../assets/Icons/arrow-left.svg')" aria-hidden="true"></span><i18n-text data-i18n="trace_map.ver_traslados">Ver traslados</i18n-text>
            </a>
            <div class="filter-wrapper">
              <button class="toolbar-button" id="filter-toggle" type="button" aria-expanded="false" aria-controls="filter-panel">
                <span class="filter-icon" aria-hidden="true"></span><i18n-text data-i18n="trace_map.filtros">Filtros</i18n-text><span class="filter-count" id="filter-count" hidden>0</span>
              </button>
              <form class="popover filter-panel" id="filter-panel" hidden>
                <div class="popover-heading"><h2 data-i18n="trace_map.filtrar_traslados">Filtrar traslados</h2><button class="text-button" id="reset-filters" type="button" data-i18n="trace_map.restablecer">Restablecer</button></div>
                <p class="popover-eyebrow" data-i18n="trace_map.estado_del_traslado">ESTADO DEL TRASLADO</p>
                <label><input type="checkbox" name="status" value="in_progress" checked><span class="metric-dot progress-dot"></span><i18n-text data-i18n="trace_map.en_curso_2">En curso</i18n-text><span class="filter-number">0</span></label>
                <label><input type="checkbox" name="status" value="returning" checked><span class="metric-dot progress-dot"></span><i18n-text data-i18n="trace_view.retornando">Retornando</i18n-text><span class="filter-number">0</span></label>
                <label><input type="checkbox" name="status" value="scheduled" checked><span class="metric-dot scheduled-dot"></span><i18n-text data-i18n="trace_map.programado_2">Programado</i18n-text><span class="filter-number">0</span></label>
                <label><input type="checkbox" name="status" value="delayed" checked><span class="metric-dot delayed-dot"></span><i18n-text data-i18n="trace_map.demorado_2">Demorado</i18n-text><span class="filter-number">0</span></label>
                <p class="filter-note" data-i18n="trace_map.los_filtros_se_aplican_al_mapa_y_a_la_lista">Los filtros se aplican al mapa y a la lista.</p>
              </form>
            </div>
            <div class="map-search" id="map-search" role="search">
              <input class="map-search-input" id="transfer-search" type="search" placeholder="Buscar traslado, ambulancia u hospital..." data-i18n-placeholder="trace_map.buscar_traslado_ambulancia_u_hospital"
                autocomplete="off" aria-label="Buscar por traslado, ambulancia u hospital" data-i18n-aria-label="trace_map.buscar_por_traslado_ambulancia_u_hospital" aria-controls="transfer-list"
                aria-hidden="true" tabindex="-1" hidden>
              <button class="toolbar-button search-toggle" id="search-toggle" type="button" aria-label="Abrir búsqueda" data-i18n-aria-label="main_menu.abrir_busqueda"
                aria-expanded="false" aria-controls="transfer-search" title="Buscar traslados" data-i18n-title="trace_map.buscar_traslados">
                <span class="icon" style="--icon-image: url('../../../assets/Icons/search-icon.svg')" aria-hidden="true"></span>
              </button>
            </div>
          </div>
        </div>

        <section class="map-stage" id="map-stage" aria-label="Seguimiento de ambulancias en Montevideo" data-i18n-aria-label="trace_map.seguimiento_de_ambulancias_en_montevideo">
          <div id="fleet-map" aria-label="Mapa interactivo de Montevideo" data-i18n-aria-label="trace_map.mapa_interactivo_de_montevideo"></div>
          <div class="map-loading" id="map-loading" role="status"><span class="loading-spinner"></span><i18n-text data-i18n="trace_map.cargando_mapa_de_montevideo">Cargando mapa de Montevideo…</i18n-text></div>

          <div class="map-sidebar" id="map-sidebar">
            <aside class="transfer-panel glass-panel" id="transfer-panel" aria-labelledby="transfer-panel-title">
              <div class="transfer-panel-header">
                <div><span class="icon" style="--icon-image: url('../../../assets/Icons/steering-wheel.svg')" aria-hidden="true"></span><h2 id="transfer-panel-title" data-i18n="trace_map.traslados_activos">Traslados activos</h2></div>
                <span class="panel-count" id="visible-count">0</span>
              </div>
              <div class="transfer-list" id="transfer-list" aria-label="Seleccionar un traslado" data-i18n-aria-label="trace_map.seleccionar_un_traslado"></div>
              <div class="empty-state" id="empty-state" hidden>
                <span class="icon" style="--icon-image: url('../../../assets/Icons/search-icon.svg')" aria-hidden="true"></span>
                <h3 data-i18n="trace_map.sin_traslados">Sin traslados</h3><p data-i18n="trace_map.no_hay_resultados_para_estos_filtros">No hay resultados para estos filtros.</p>
                <button class="text-button" id="clear-search" type="button" data-i18n="trace_map.limpiar_busqueda_y_filtros">Limpiar búsqueda y filtros</button>
              </div>
              <footer class="transfer-panel-footer"><span class="status-dot"></span><span data-i18n="trace_map.seguimiento_de_flota">Seguimiento de flota</span><span class="panel-update" id="panel-update"></span></footer>
            </aside>
            <div class="map-location glass-panel"><span class="icon" style="--icon-image: url('../../../assets/Icons/location-icon.svg')" aria-hidden="true"></span><div><strong data-i18n="trace_map.montevideo_uruguay">Montevideo, Uruguay</strong></div><button id="reset-view" class="icon-button" type="button" aria-label="Restablecer vista de Montevideo" data-i18n-aria-label="trace_map.restablecer_vista_de_montevideo" title="Restablecer vista" data-i18n-title="trace_map.restablecer_vista"><span class="icon" style="--icon-image: url('../../../assets/Icons/reload-icon.svg')" aria-hidden="true"></span></button></div>
          </div>

          <div class="map-controls glass-panel" role="group" aria-label="Controles del mapa" data-i18n-aria-label="trace_map.controles_del_mapa">
            <button class="map-control list-toggle" id="list-toggle" type="button" aria-label="Ocultar lista de traslados" data-i18n-aria-label="trace_map.ocultar_lista_de_traslados"
              aria-expanded="true" aria-controls="transfer-panel" title="Ocultar lista de traslados" data-i18n-title="trace_map.ocultar_lista_de_traslados"><span class="icon" style="--icon-image: url('../../../assets/Icons/list-icon.svg')" aria-hidden="true"></span></button>
            <span class="control-divider"></span>
            <button class="map-control" id="zoom-in" type="button" aria-label="Acercar mapa" data-i18n-aria-label="trace_map.acercar_mapa" title="Acercar" data-i18n-title="trace_map.acercar"><span class="icon" style="--icon-image: url('../../../assets/Icons/zoom-in.svg')" aria-hidden="true"></span></button>
            <button class="map-control" id="zoom-out" type="button" aria-label="Alejar mapa" data-i18n-aria-label="trace_map.alejar_mapa" title="Alejar" data-i18n-title="trace_map.alejar"><span class="icon" style="--icon-image: url('../../../assets/Icons/zoom-out.svg')" aria-hidden="true"></span></button>
            <span class="control-divider"></span>
            <button class="map-control" id="locate" type="button" aria-label="Centrar en la ambulancia seleccionada" data-i18n-aria-label="trace_map.centrar_en_la_ambulancia_seleccionada" title="Centrar ambulancia" data-i18n-title="trace_map.centrar_ambulancia"><span class="icon" style="--icon-image: url('../../../assets/Icons/focus-icon.svg')" aria-hidden="true"></span></button>
            <button class="map-control" id="layers-toggle" type="button" aria-label="Capas del mapa" data-i18n-aria-label="trace_map.capas_del_mapa" aria-expanded="false" aria-controls="layers-panel" title="Capas" data-i18n-title="trace_map.capas"><span class="icon" style="--icon-image: url('../../../assets/Icons/layers-icon.svg')" aria-hidden="true"></span></button>
            <span class="control-divider"></span>
            <button class="map-control" id="fullscreen-toggle" type="button" aria-label="Pantalla completa" data-i18n-aria-label="trace_map.pantalla_completa" aria-pressed="false" title="Pantalla completa" data-i18n-title="trace_map.pantalla_completa"><span class="icon" style="--icon-image: url('../../../assets/Icons/full-screen.svg')" aria-hidden="true"></span></button>
          </div>
          <div class="popover layers-panel" id="layers-panel" hidden>
            <h2 data-i18n="trace_map.capas_del_mapa">Capas del mapa</h2>
            <label><input type="checkbox" id="show-routes" checked><i18n-text data-i18n="trace_map.rutas_de_traslados">Rutas de traslados</i18n-text></label>
            <label><input type="checkbox" id="show-destinations" checked><i18n-text data-i18n="trace_map.origenes_y_destinos">Orígenes y destinos</i18n-text></label>
            <label><input type="checkbox" id="show-neighborhoods" checked><i18n-text data-i18n="trace_map.barrios_de_montevideo">Barrios de Montevideo</i18n-text></label>
            <button class="text-button" id="fit-fleet" type="button"><i18n-text data-i18n="trace_map.ver_toda_la_flota">Ver toda la flota</i18n-text> <span aria-hidden="true">↗</span></button>
          </div>

          <aside class="route-legend glass-panel" aria-labelledby="legend-heading">
            <h2 id="legend-heading" data-i18n="trace_map.rutas_en_el_mapa">RUTAS EN EL MAPA</h2>
            <div id="legend-list"></div>
          </aside>
          <div class="toast glass-panel" id="toast" role="status" hidden></div>
          <span class="visually-hidden" id="result-announcement" role="status" aria-live="polite"></span>
          <noscript><p class="noscript-message" data-i18n="trace_map.activa_javascript_para_usar_el_mapa_y_consultar_los_traslados">Activa JavaScript para usar el mapa y consultar los traslados.</p></noscript>
        </section>
      </section>
    </main>
  </div>

  <dialog class="detail-dialog" id="detail-dialog" aria-labelledby="detail-title">
    <div class="detail-dialog-content" id="detail-content"></div>
    <button class="dialog-close icon-button close-control" id="close-details" type="button" aria-label="Cerrar detalles" data-i18n-aria-label="trace_map.cerrar_detalles"><span class="icon" style="--icon-image: url('../../../assets/Icons/cross-icon.svg')" aria-hidden="true"></span></button>
  </dialog>
</body>

</html>
