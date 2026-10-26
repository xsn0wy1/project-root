<?php
declare(strict_types=1);
require dirname(__DIR__, 4) . '/backend/php/core/page.php';
require_staff_page('traces/trace.php');
$dispatchHidden = '';
if (!user_has_role($db, (string)$GLOBALS['page_user']['id'], 'dispatcher')) {
    $dispatchHidden = ' hidden';
}
require dirname(__DIR__, 4) . '/backend/php/components/shell.php';
staff_page_open([
    'module' => 'traces',
    'theme_styles' => 'main-menu trace',
    'title_key' => 'trace.traslados_de_ambulancias',
    'title' => 'Traslados de Ambulancias',
    'stylesheets' => ['traces/trace.css'],
    'font_stylesheet' => 'interface',
    'body_class' => '',
    'background' => true,
    'described_nav' => true,
    'nav_section' => 'transfers',
    'extra_scripts' => ['../../js/traces/trace-api.js', '../../js/traces/trace.js'],
]);
?>



        <section class="content-card">

            <?php staff_header('transfers'); ?>

            <section class="trace-panel">
                <div class="trace-content">
                    <div class="trace-heading">
                        <div>
                            <h1 data-i18n="trace.traslados_de_ambulancias">Traslados de Ambulancias</h1>
                            <p data-i18n="trace.gestiona_y_realiza_seguimiento_de_todos_los_traslados">Gestiona y realiza seguimiento de todos los traslados</p>
                        </div>

                        <div class="heading-actions">
                            <button type="button" class="action-button primary-action" data-required-role="dispatcher"<?= $dispatchHidden ?>>
                                <img src="../../../assets/Icons/plus-icon.svg" alt="" aria-hidden="true"
                                    class="button-icon">
                                <i18n-text data-i18n="trace_new.nuevo_traslado">Nuevo Traslado</i18n-text>
                            </button>

                            <a href="trace-map.php" class="action-button secondary-action">
                                <img src="../../../assets/Icons/map-icon.svg" alt="" aria-hidden="true"
                                    class="button-icon">
                                <i18n-text data-i18n="trace.ver_en_mapa">Ver en Mapa</i18n-text>
                            </a>
                        </div>
                    </div>


                    <div class="status-tabs" role="tablist" aria-label="Filtrar traslados" data-i18n-aria-label="trace_map.filtrar_traslados">
                        <button type="button" class="filter-button active" data-filter="active" role="tab"
                            aria-selected="true">
                            <i18n-text data-i18n="trace.activos">Activos</i18n-text> <span id="activeCount">0</span>
                        </button>
                        <button type="button" class="filter-button" data-filter="completed" role="tab"
                            aria-selected="false">
                            <i18n-text data-i18n="trace.completados">Completados</i18n-text> <span id="completedCount">0</span>
                        </button>
                        <button type="button" class="filter-button" data-filter="all" role="tab" aria-selected="false">
                            <i18n-text data-i18n="trace.todos">Todos</i18n-text> <span id="allCount">0</span>
                        </button>
                    </div>


                    <div class="transfer-views">
                    <section class="transfer-grid" id="transferGrid" aria-live="polite"></section>
                    <template id="transferCardTemplate"><article class="transfer-card blue-card" data-status="active">
                            <div class="card-heading">
                                <div class="ambulance-icon">
                                    <img src="../../../assets/Icons/ambulance-icon.png" alt="" aria-hidden="true">
                                </div>
                                <div class="card-title">
                                    <h2></h2>
                                    <p></p>
                                </div>
                                <span class="status-label progress-label"></span>
                            </div>

                            <div class="card-divider"></div>

                            <div class="transfer-information">
                                <div class="info-group">
                                    <p class="information-label" data-i18n="trace_view.conductor">Conductor</p>
                                    <div class="info-value">

                                        <img src="../../../assets/Icons/steering-wheel.svg" alt="" aria-hidden="true"
                                            class="info-icon">
                                        <p></p>
                                    </div>
                                </div>

                                <div class="info-group">
                                    <p class="information-label" data-i18n="trace_view.acompanante">Acompañante</p>
                                    <div class="info-value">

                                        <img src="../../../assets/Icons/person-icon.svg" alt="" aria-hidden="true"
                                            class="info-icon">
                                        <p></p>
                                    </div>
                                </div>

                                <div class="info-group">
                                    <p class="information-label" data-i18n="trace.elemento">Elemento</p>
                                    <div class="info-value">

                                        <img src="../../../assets/Icons/human-icon.svg" alt="" aria-hidden="true"
                                            class="info-icon">
                                        <p></p>
                                    </div>
                                </div>
                            </div>

                            <div class="route-box">
                                <div class="route-line"><span></span><span></span></div>
                                <div class="route-text">
                                    <div><small data-i18n="trace_view.origen">Origen</small><strong></strong>
                                        <p></p>
                                    </div>
                                    <div><small data-i18n="trace_view.destino">Destino</small><strong></strong>
                                        <p></p>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="details-button" data-i18n="trace.ver_detalles">Ver detalles</button>
                        </article></template>

                    <p class="empty-message" id="emptyMessage" hidden data-i18n="trace.no_se_encontraron_traslados">No se encontraron traslados.</p>

                    <section class="all-transfers" id="allTransfers" aria-label="Todos los traslados" data-i18n-aria-label="trace.todos_los_traslados" hidden>
                        <div class="all-transfers-heading">
                            <div>
                                <span class="list-eyebrow" data-i18n="trace.registro_completo">REGISTRO COMPLETO</span>
                                <h2 data-i18n="trace.todos_los_traslados">Todos los traslados</h2>
                                <p data-i18n="trace.consulta_cada_recorrido_y_encuentra_un_traslado_especifico">Consulta cada recorrido y encuentra un traslado específico.</p>
                            </div>
                            <label class="transfer-list-search" for="listTransferSearch">
                                <img src="../../../assets/Icons/search-icon.svg" alt="" aria-hidden="true">
                                <input type="search" id="listTransferSearch" placeholder="Buscar traslado, lugar, conductor..." data-i18n-placeholder="trace.buscar_traslado_lugar_conductor"
                                    autocomplete="off" aria-label="Buscar traslados por palabras clave" data-i18n-aria-label="trace.buscar_traslados_por_palabras_clave">
                            </label>
                        </div>

                        <div class="all-transfers-controls">
                            <div class="list-control-group">
                                <span class="control-label" data-i18n="trace_view.tipo_de_traslado">Tipo de traslado</span>
                                <div class="segmented-controls" role="group" aria-label="Filtrar por tipo de traslado" data-i18n-aria-label="trace.filtrar_por_tipo_de_traslado">
                                    <button type="button" class="list-control active" data-type="all" aria-pressed="true" data-i18n="trace.todos">Todos</button>
                                    <button type="button" class="list-control" data-type="paciente" aria-pressed="false" data-i18n="trace_view.paciente">Paciente</button>
                                    <button type="button" class="list-control" data-type="equipo" aria-pressed="false" data-i18n="trace.equipo">Equipo</button>
                                    <button type="button" class="list-control" data-type="biologico" aria-pressed="false" data-i18n="trace_new.biologico">Biológico</button>
                                </div>
                            </div>
                            <div class="list-control-group date-control-group">
                                <span class="control-label" data-i18n="trace.fecha_de_salida">Fecha de salida</span>
                                <div class="segmented-controls" role="group" aria-label="Ordenar por fecha de salida" data-i18n-aria-label="trace.ordenar_por_fecha_de_salida">
                                    <button type="button" class="list-control active" data-sort="newest" aria-pressed="true" data-i18n="trace.mas_recientes">Más recientes</button>
                                    <button type="button" class="list-control" data-sort="oldest" aria-pressed="false" data-i18n="trace.mas_antiguos">Más antiguos</button>
                                </div>
                            </div>
                        </div>

                        <div class="transfer-list-head" aria-hidden="true">
                            <span data-i18n="trace_view.traslado">Traslado</span>
                            <span data-i18n="trace.recorrido">Recorrido</span>
                            <span data-i18n="trace.personal_asignado">Personal asignado</span>
                            <span data-i18n="trace.accion">Acción</span>
                        </div>
                        <div class="transfer-list" id="transferList" role="region" aria-label="Lista de traslados" data-i18n-aria-label="trace.lista_de_traslados"
                            aria-live="polite" tabindex="0"></div>
                        <p class="list-empty-message" id="listEmptyMessage" hidden data-i18n="trace.no_se_encontraron_traslados_con_estos_filtros">No se encontraron traslados con estos filtros.</p>
                    </section>

                    </div>

                    <footer class="list-footer">
                        <p id="resultsText"></p>

                        <div class="pagination" aria-label="Paginación" data-i18n-aria-label="trace.paginacion">
                            <button type="button" class="page-arrow" id="previousPage"
                                aria-label="Página anterior" data-i18n-aria-label="trace.pagina_anterior">◀</button>
                            <div class="page-numbers" id="pageNumbers">

                            </div>
                            <button type="button" class="page-arrow" id="nextPage"
                                aria-label="Página siguiente" data-i18n-aria-label="trace.pagina_siguiente">▶</button>
                        </div>
                    </footer>
                </div>
            </section>
        </section>
    </main>

</body>

</html>
