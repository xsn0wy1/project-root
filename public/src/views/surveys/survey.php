<?php
declare(strict_types=1);
require dirname(__DIR__, 4) . '/backend/php/core/page.php';
require_staff_page('surveys/survey.php');
require dirname(__DIR__, 4) . '/backend/php/components/shell.php';
staff_page_open([
    'module' => 'surveys',
    'theme_styles' => 'main-menu survey survey-integration documents',
    'title_key' => 'survey.encuestas_hospital_de_clinicas',
    'title' => 'Encuestas | Hospital de Clínicas',
    'stylesheets' => ['documents/documents.css?v=20261012-documents', 'surveys/survey.css?v=stage6'],
    'font_stylesheet' => 'inclusive-regular',
    'body_class' => 'surveys-page',
    'background' => false,
    'described_nav' => false,
    'nav_section' => 'surveys',
    'extra_scripts' => ['../../../assets/vendor/qrcode/qrcode.js', '../../js/surveys/survey-api.js?v=stage5', '../../js/surveys/survey-templates.js?v=stage5', '../../js/surveys/survey.js?v=ui-repairs'],
]);
?>


    <div class="surveys-layout">
      <?php staff_header('plain'); ?>

      <section class="surveys-content" aria-labelledby="surveys-title">
        <div class="surveys-heading">
          <h1 id="surveys-title" data-i18n="main_menu.encuestas">Encuestas</h1>
          <div class="heading-actions"><button type="button" class="survey-button secondary" data-open-templates><img src="../../../assets/Icons/template-icon.svg" alt=""><i18n-text data-i18n="survey.plantillas" style="display:contents">Plantillas</i18n-text></button><a class="survey-button primary new-survey-link" href="survey-new.php"><img src="../../../assets/Icons/plus-icon.svg" alt=""><i18n-text data-i18n="survey.nueva_encuesta" style="display:contents">Nueva encuesta</i18n-text></a></div>
        </div>

        <div class="survey-stats" aria-label="Resumen de encuestas" data-i18n-aria-label="survey.resumen_de_encuestas">
          <article class="stat-card"><span class="survey-icon violet"><img src="../../../assets/Icons/survey-all.svg" alt=""></span><div><span class="stat-label" data-i18n="survey.encuestas_totales">Encuestas totales</span><strong id="stat-total">0</strong><small id="stat-drafts" data-i18n="survey.0_borradores">0 borradores</small></div></article>
          <article class="stat-card"><span class="survey-icon green"><img src="../../../assets/Icons/survey-finished.svg" alt=""></span><div><span class="stat-label" data-i18n="routes.activas">Activas</span><strong id="stat-active">0</strong><small data-i18n="survey.abiertas_a_respuestas">Abiertas a respuestas</small></div></article>
          <article class="stat-card"><span class="survey-icon cyan"><img src="../../../assets/Icons/survey-answers.svg" alt=""></span><div><span class="stat-label" data-i18n="survey_view.respuestas_recibidas">Respuestas recibidas</span><strong id="stat-responses">0</strong><small data-i18n="survey.entre_todas_las_encuestas">Entre todas las encuestas</small></div></article>
          <article class="stat-card"><span class="survey-icon amber"><img src="../../../assets/Icons/survey-rate.svg" alt=""></span><div><span class="stat-label" data-i18n="survey_view.valoracion_media">Valoración media</span><strong id="stat-satisfaction">—</strong><small data-i18n="survey.sin_valoraciones_todavia">Sin valoraciones todavía</small></div></article>
        </div>


        <div class="survey-toolbar" role="search" aria-label="Buscar y filtrar encuestas" data-i18n-aria-label="survey.buscar_y_filtrar_encuestas">
          <label class="survey-search"><img src="../../../assets/Icons/search-icon.svg" alt=""><input id="survey-search" type="search" placeholder="Buscar encuesta..." data-i18n-placeholder="survey.buscar_encuesta" autocomplete="off" aria-label="Buscar encuesta" data-i18n-aria-label="survey.buscar_encuesta_2"></label>
          <div class="document-filter survey-select">
            <span class="visually-hidden" id="survey-status-caption" data-i18n="trace_view.estado">Estado</span>
            <select id="survey-status" hidden aria-hidden="true" tabindex="-1"><option value="all" data-i18n="survey.todos_los_estados">Todos los estados</option><option value="published" data-i18n="routes.activas">Activas</option><option value="draft" data-i18n="survey.borradores">Borradores</option><option value="closed" data-i18n="survey.finalizadas">Finalizadas</option><option value="archived" data-i18n="survey.archivadas">Archivadas</option></select>
            <button type="button" class="filter-trigger" id="survey-status-button" aria-expanded="false" aria-controls="survey-status-menu" aria-labelledby="survey-status-caption survey-status-label"><span id="survey-status-label" data-i18n="survey.todos_los_estados">Todos los estados</span><span class="filter-chevron" aria-hidden="true"></span></button>
            <div class="filter-menu" id="survey-status-menu" role="group" aria-labelledby="survey-status-caption" hidden></div>
          </div>
          <div class="document-filter survey-select">
            <span class="visually-hidden" id="survey-category-caption" data-i18n="survey.categoria">Categoría</span>
            <select id="survey-category" hidden aria-hidden="true" tabindex="-1"><option value="all" data-i18n="survey.todas_las_categorias">Todas las categorías</option><option value="patient_care" data-i18n="survey.atencion_al_paciente">Atención al paciente</option><option value="transfers" data-i18n="survey.traslados">Traslados</option><option value="emergency" data-i18n="survey.emergencias">Emergencias</option><option value="staff" data-i18n="trace_view.personal">Personal</option><option value="other" data-i18n="survey.otras">Otras</option></select>
            <button type="button" class="filter-trigger" id="survey-category-button" aria-expanded="false" aria-controls="survey-category-menu" aria-labelledby="survey-category-caption survey-category-label"><span id="survey-category-label" data-i18n="survey.todas_las_categorias">Todas las categorías</span><span class="filter-chevron" aria-hidden="true"></span></button>
            <div class="filter-menu" id="survey-category-menu" role="group" aria-labelledby="survey-category-caption" hidden></div>
          </div>
          <div class="document-filter survey-select sort-select">
            <span class="visually-hidden" id="survey-sort-caption" data-i18n="survey.ordenar_encuestas">Ordenar encuestas</span>
            <select id="survey-sort" hidden aria-hidden="true" tabindex="-1"><option value="recent" data-i18n="trace.mas_recientes">Más recientes</option><option value="oldest" data-i18n="survey.mas_antiguas">Más antiguas</option><option value="name" data-i18n="survey.nombre_a_z">Nombre A–Z</option><option value="responses" data-i18n="survey.mas_respuestas">Más respuestas</option></select>
            <button type="button" class="filter-trigger" id="survey-sort-button" aria-expanded="false" aria-controls="survey-sort-menu" aria-labelledby="survey-sort-caption survey-sort-label"><span id="survey-sort-label" data-i18n="trace.mas_recientes">Más recientes</span><span class="filter-chevron" aria-hidden="true"></span></button>
            <div class="filter-menu" id="survey-sort-menu" role="group" aria-labelledby="survey-sort-caption" hidden></div>
          </div>
          <div class="view-switch" role="group" aria-label="Vista de encuestas" data-i18n-aria-label="survey.vista_de_encuestas"><button type="button" class="is-active" data-view="list" aria-label="Vista de lista" data-i18n-aria-label="survey.vista_de_lista" aria-pressed="true"><img src="../../../assets/Icons/list-icon.svg" alt=""></button><button type="button" data-view="grid" aria-label="Vista de tarjetas" data-i18n-aria-label="survey.vista_de_tarjetas" aria-pressed="false"><img src="../../../assets/Icons/grid-icon.svg" alt=""></button></div>
        </div>

        <div class="surveys-workspace">
          <section class="survey-directory" aria-labelledby="directory-title">
            <div class="directory-heading"><h2 id="directory-title"><i18n-text data-i18n="survey.todas_las_encuestas" style="display:contents">Todas las encuestas</i18n-text> <span id="survey-count">0</span></h2><span class="directory-caption" data-i18n="survey.tu_espacio_para_escuchar">Tu espacio para escuchar</span></div>
            <div class="survey-list-head" id="survey-list-head" aria-hidden="true"><span data-i18n="survey.encuesta">Encuesta</span><span data-i18n="trace_view.estado">Estado</span><span data-i18n="survey_view.respuestas">Respuestas</span><span data-i18n="survey.valoracion">Valoración</span><span data-i18n="survey.ultima_actividad">Última actividad</span><span></span></div>
            <div id="survey-list" class="survey-list" aria-label="Encuestas" data-i18n-aria-label="main_menu.encuestas"></div>
            <div class="survey-empty" id="survey-empty">
              <div class="empty-art" aria-hidden="true"><div class="paper-back"></div><div class="paper-front"><img src="../../../assets/Icons/survey-icon.svg" alt=""><i></i><i></i><i></i></div><span>+</span></div>
              <span class="eyebrow" data-i18n="survey.el_primer_paso_es_escuchar">EL PRIMER PASO ES ESCUCHAR</span><h3 id="empty-title" data-i18n="survey.crea_tu_primera_encuesta">Creá tu primera encuesta</h3><p id="empty-description" data-i18n="survey.conoce_como_viven_la_atencion_tus_pacientes_y_como_se_siente_tu_equipo_empeza_desde_cero_o">Conocé cómo viven la atención tus pacientes y cómo se siente tu equipo. Empezá desde cero o elegí una plantilla.</p>
              <div class="empty-actions" id="empty-actions"><a class="survey-button primary new-survey-link" href="survey-new.php"><img src="../../../assets/Icons/plus-icon.svg" alt=""><i18n-text data-i18n="survey.crear_encuesta" style="display:contents">Crear encuesta</i18n-text></a><button type="button" class="text-button" data-open-templates><i18n-text data-i18n="survey.explorar_plantillas" style="display:contents">Explorar plantillas</i18n-text> <span aria-hidden="true">↗</span></button></div>
              <button type="button" class="survey-button secondary" id="clear-filters" hidden data-i18n="routes.limpiar_filtros">Limpiar filtros</button>
            </div>
            <footer class="survey-footer"><span id="results-summary" role="status" aria-live="polite" data-i18n="survey.0_encuestas">0 encuestas</span><nav id="survey-pagination" class="survey-pagination" aria-label="Páginas de encuestas" data-i18n-aria-label="survey.paginas_de_encuestas"></nav></footer>
          </section>

          <aside class="survey-detail" aria-labelledby="detail-title">
            <div class="detail-heading"><h2 id="detail-title" data-i18n="survey.detalles_de_la_encuesta">Detalles de la encuesta</h2><img src="../../../assets/Icons/information-icon.svg" alt=""></div>
            <div id="survey-detail-content" class="detail-content"></div>
            <div class="detail-actions" id="detail-actions" hidden><button type="button" class="survey-button danger" id="delete-survey"><img src="../../../assets/Icons/trash-icon.svg" alt=""><span class="visually-hidden" data-i18n="survey.eliminar_encuesta">Eliminar encuesta</span></button><a class="survey-button secondary" id="edit-survey" href="survey-new.php"><img src="../../../assets/Icons/pencil-icon.svg" alt=""><i18n-text data-i18n="survey_view.editar_encuesta" style="display:contents">Editar encuesta</i18n-text></a></div>
          </aside>
        </div>
      </section>
    </div>
  </main>

  <dialog class="survey-dialog templates-dialog" id="templates-dialog" aria-labelledby="templates-title">
    <div class="dialog-heading"><div><p class="eyebrow" data-i18n="survey.un_punto_de_partida">UN PUNTO DE PARTIDA</p><h2 id="templates-title" data-i18n="survey.preguntas_que_ayudan_a_mejorar">Preguntas que ayudan a mejorar</h2><p data-i18n="survey.elegi_una_plantilla_y_adaptala_a_lo_que_necesitas_conocer">Elegí una plantilla y adaptala a lo que necesitás conocer.</p></div><button type="button" class="icon-button" data-close-dialog aria-label="Cerrar plantillas" data-i18n-aria-label="survey.cerrar_plantillas"><img src="../../../assets/Icons/cross-icon.svg" alt=""></button></div>
    <div class="template-grid" id="template-grid"></div>
    <div class="dialog-footer"><span data-i18n="survey.las_plantillas_no_incluyen_respuestas">Las plantillas no incluyen respuestas.</span><a class="survey-button secondary" href="survey-new.php" data-i18n="survey.empezar_desde_cero">Empezar desde cero</a></div>
  </dialog>

  <dialog class="survey-dialog delete-dialog" id="delete-dialog" aria-labelledby="delete-title" aria-describedby="delete-description">
    <div class="delete-content"><span class="survey-icon rose"><img src="../../../assets/Icons/trash-icon.svg" alt=""></span><h2 id="delete-title" data-i18n="survey.archivar_esta_encuesta">¿Archivar esta encuesta?</h2><p id="delete-description"></p><p data-i18n="survey.se_conservaran_las_respuestas_podes_restaurarla_desde_el_filtro_archivadas">Se conservarán las respuestas. Podés restaurarla desde el filtro Archivadas.</p><div class="delete-actions"><button type="button" class="survey-button secondary" data-close-dialog autofocus data-i18n="settings.cancelar">Cancelar</button><button type="button" class="survey-button danger" id="confirm-delete" data-i18n="patients.archivar">Archivar</button></div></div>
  </dialog>

  <dialog class="survey-dialog form-reader-dialog" id="form-reader-dialog" aria-labelledby="reader-title">
    <div class="dialog-heading"><div><p class="eyebrow" data-i18n="survey.formulario_de_la_encuesta">FORMULARIO DE LA ENCUESTA</p><h2 id="reader-title" data-i18n="survey.encuesta">Encuesta</h2><p id="reader-description"></p></div><button type="button" class="icon-button" data-close-dialog aria-label="Cerrar formulario" data-i18n-aria-label="routes.cerrar_formulario"><img src="../../../assets/Icons/cross-icon.svg" alt=""></button></div>
    <div class="reader-questions" id="reader-questions"></div><div class="dialog-footer"><button type="button" class="survey-button secondary" data-close-dialog data-i18n="routes.cerrar_formulario">Cerrar formulario</button></div>
  </dialog>
</body>
</html>
