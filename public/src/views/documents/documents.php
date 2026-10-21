<?php
declare(strict_types=1);
require dirname(__DIR__, 4) . '/backend/php/core/page.php';
require_staff_page('documents/documents.php');
require dirname(__DIR__, 4) . '/backend/php/components/shell.php';
staff_page_open([
    'module' => 'documents',
    'body_class' => '',
    'background' => true,
    'described_nav' => true,
    'title_key' => 'main_menu.documentos',
    'title' => 'Documentos',
    'theme_styles' => 'main-menu documents',
    'stylesheets' => ['documents/documents.css?v=20261012-documents'],
    'fonts' => 'https://fonts.googleapis.com/css2?family=Inclusive+Sans:ital,wght@0,300..700;1,300..700&family=Krub:ital,wght@0,200;0,300;0,400;0,500;1,400;1,500&display=swap',
    'nav_section' => 'documents',
    'main_class' => 'app-shell',
    'extra_scripts' => ['../../js/documents/document-api.js?v=stage4', '../../js/documents/documents.js?v=stage4'],
]);
?>
<?php staff_header('documents'); ?>
    <section class="hero-panel documents-panel">

      <div class="documents-layout">
        <aside class="document-surface staff-card">
          <img class="staff-avatar" src="../../../assets/Icons/pfp-default.png" alt="Funcionario 1" data-i18n-alt="documents.funcionario_1" data-current-user-avatar>
          <h2 data-current-user-name data-i18n="documents.funcionario1">FUNCIONARIO1</h2>
          <p data-current-user-national-id></p>
          <p data-current-user-role></p>

          <div class="staff-divider"></div>

          <a href="documents-upload.php" class="staff-action" style="text-decoration: none;">
            <img src="../../../assets/Icons/folders-icon.png" alt="">
            <span data-i18n="documents.cargar_nuevo_documento">CARGAR NUEVO DOCUMENTO</span>
          </a>

          <button type="button" class="staff-action" id="editDocumentButton" aria-pressed="false" aria-controls="documentsGrid documentSelectionBar">
            <img src="../../../assets/Icons/edit-icon.svg" alt="">
            <span data-i18n="documents.editar_documento">EDITAR DOCUMENTO</span>
          </button>
        </aside>

        <div class="documents-column">
          <section class="document-surface recent-documents" aria-labelledby="recent-title">
            <h2 id="recent-title" class="visually-hidden" data-i18n="main_menu.documentos">Documentos</h2>
            <div class="documents-heading">
              <div class="documents-heading-left">
                <div class="documents-view-switch" role="group" aria-label="Cambiar vista de documentos" data-i18n-aria-label="documents.cambiar_vista_de_documentos">
                  <button type="button" class="view-toggle active" id="gridView" aria-label="Vista de cuadrícula" data-i18n-aria-label="documents.vista_de_cuadricula"
                    aria-pressed="true" title="Vista de cuadrícula" data-i18n-title="documents.vista_de_cuadricula">
                    <img src="../../../assets/Icons/grid-icon.svg" alt="" aria-hidden="true">
                  </button>
                  <button type="button" class="view-toggle" id="listView" aria-label="Vista de lista" data-i18n-aria-label="survey.vista_de_lista"
                    aria-pressed="false" title="Vista de lista" data-i18n-title="survey.vista_de_lista">
                    <img src="../../../assets/Icons/list-icon.svg" alt="" aria-hidden="true">
                  </button>
                </div>

                <button type="button" class="filter-trigger" id="documentsArchiveToggle" aria-pressed="false" data-i18n="personnel.ver_archivados">Ver archivados</button>
                <div class="documents-list-filters" id="documentListFilters" hidden>
                  <div class="document-filter">
                    <button type="button" class="filter-trigger" id="documentTypeButton" aria-haspopup="true"
                      aria-expanded="false" aria-controls="documentTypeMenu">
                      <span id="documentTypeLabel" data-i18n="documents.tipo_todos">Tipo: Todos</span><span class="filter-chevron" aria-hidden="true"></span>
                    </button>
                    <div class="filter-menu" id="documentTypeMenu" aria-label="Filtrar por tipo" data-i18n-aria-label="documents.filtrar_por_tipo" hidden>
                      <button type="button" data-type="" aria-pressed="true" data-i18n="trace.todos">Todos</button>
                      <button type="button" data-type="pdf" aria-pressed="false" data-i18n="documents.pdf">PDF</button>
                      <button type="button" data-type="docx" aria-pressed="false" data-i18n="documents.docx">DOCX</button>
                      <button type="button" data-type="png" aria-pressed="false" data-i18n="documents.png">PNG</button>
                      <button type="button" data-type="jpg" aria-pressed="false" data-i18n="documents.jpg">JPG</button>
                    </div>
                  </div>
                  <div class="document-filter">
                    <button type="button" class="filter-trigger" id="documentDateButton" aria-haspopup="true"
                      aria-expanded="false" aria-controls="documentDateMenu">
                      <span id="documentDateLabel" data-i18n="documents.fecha">Fecha</span><span class="filter-chevron" aria-hidden="true"></span>
                    </button>
                    <div class="filter-menu" id="documentDateMenu" aria-label="Ordenar por fecha" data-i18n-aria-label="documents.ordenar_por_fecha" hidden>
                      <button type="button" data-date="" aria-pressed="true" data-i18n="documents.sin_ordenar">Sin ordenar</button>
                      <button type="button" data-date="recent" aria-pressed="false" data-i18n="trace.mas_recientes">Más recientes</button>
                      <button type="button" data-date="oldest" aria-pressed="false" data-i18n="trace.mas_antiguos">Más antiguos</button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="documents-list-search" id="documentListSearch" role="search" hidden>
                <label class="visually-hidden" for="documentSearchInput" data-i18n="documents.buscar_documentos_por_nombre_o_palabras_clave">Buscar documentos por nombre o palabras clave</label>
                <input type="search" id="documentSearchInput" placeholder="Nombre o palabras clave..." data-i18n-placeholder="documents.nombre_o_palabras_clave" autocomplete="off">
                <button type="button" id="documentSearchButton" aria-label="Buscar en documentos" data-i18n-aria-label="documents.buscar_en_documentos" title="Buscar en documentos" data-i18n-title="documents.buscar_en_documentos">
                  <img src="../../../assets/Icons/search-icon.svg" alt="" aria-hidden="true">
                </button>
              </div>

              <div class="documents-pagination">
                <button type="button" class="page-arrow" id="prevDocs" aria-label="Anterior" data-i18n-aria-label="survey_view.anterior" disabled>
                  <img src="../../../assets/Icons/arrowhead-icon.svg" alt="" aria-hidden="true">
                </button>
                <button type="button" class="page-arrow" id="nextDocs" aria-label="Siguiente" data-i18n-aria-label="survey_view.siguiente">
                  <img src="../../../assets/Icons/arrowhead-icon.svg" alt="" aria-hidden="true">
                </button>
              </div>
            </div>

            <p class="visually-hidden" id="documentResultsStatus" role="status" aria-live="polite"></p>
            <div class="documents-grid" id="documentsGrid" aria-busy="true"></div>
          </section>

          <section class="document-selection-bar" id="documentSelectionBar" aria-labelledby="documentSelectionTitle" hidden>
            <div class="document-selection-summary visually-hidden">
              <h3 id="documentSelectionTitle" data-i18n="documents.seleccionar_documentos">Seleccionar documentos</h3>
              <p id="documentSelectionStatus" role="status" aria-live="polite" aria-atomic="true"></p>
            </div>
            <div class="document-selection-actions" role="group" aria-label="Acciones de selección" data-i18n-aria-label="documents.acciones_de_seleccion">
              <button type="button" class="selection-action" id="selectAllDocuments" data-i18n="documents.seleccionar_todos">Seleccionar todos</button>
              <button type="button" class="selection-action selection-edit" id="editSelectedDocument" disabled>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 3 5 5-12 12-6 1 1-6L16 3ZM14 5l5 5"/></svg>
                <span data-i18n="trace_view.editar">Editar</span>
              </button>
              <button type="button" class="selection-action selection-delete" id="deleteSelectedDocuments" disabled>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/></svg>
                <span data-i18n="routes.eliminar">Eliminar</span>
              </button>
              <button type="button" class="selection-action selection-cancel" id="cancelDocumentSelection" data-i18n="settings.cancelar">Cancelar</button>
            </div>
          </section>
          <p class="document-action-status" id="documentActionStatus" role="status" aria-live="polite" aria-atomic="true" hidden></p>
        </div>
      </div>
    </section>
  </main>

</body>

</html>
