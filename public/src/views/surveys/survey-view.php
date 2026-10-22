<?php
declare(strict_types=1);
require dirname(__DIR__, 4) . '/backend/php/core/page.php';
require_staff_page('surveys/survey-view.php');
require dirname(__DIR__, 4) . '/backend/php/components/shell.php';
staff_page_open([
    'module' => 'surveys',
    'theme_styles' => 'main-menu survey survey-view survey-integration',
    'title_key' => 'survey_view.detalle_de_encuesta_hospital_de_clinicas',
    'title' => 'Detalle de encuesta | Hospital de Clínicas',
    'stylesheets' => ['surveys/survey.css', 'surveys/survey-view.css'],
    'font_stylesheet' => 'inclusive-regular',
    'body_class' => 'surveys-page survey-view-page',
    'background' => false,
    'described_nav' => false,
    'nav_section' => 'surveys',
    'extra_scripts' => ['../../../assets/vendor/qrcode/qrcode.js', '../../js/surveys/survey-api.js', '../../js/surveys/survey-view.js'],
]);
?>


    <div class="surveys-layout">
      <?php staff_header('plain'); ?>

      <section class="survey-insights" aria-labelledby="survey-view-title">
        <div class="insight-heading">
          <div class="insight-identity"><a class="icon-button insight-back" href="survey.php" aria-label="Volver a encuestas" data-i18n-aria-label="survey_view.volver_a_encuestas" title="Volver a encuestas" data-i18n-title="survey_view.volver_a_encuestas"><img src="../../../assets/Icons/arrow-left.svg" alt=""></a><div class="insight-title-copy"><h1 id="survey-view-title" data-i18n="survey_view.detalle_de_encuesta">Detalle de encuesta</h1><div class="insight-subline"><span id="view-category" data-i18n="survey_view.contenido_y_respuestas">Contenido y respuestas</span><span id="view-status" class="survey-status" hidden></span></div></div></div>
          <div class="insight-actions"><button type="button" class="survey-button secondary" id="survey-transition" hidden data-i18n="survey_view.cerrar_encuesta">Cerrar encuesta</button><button type="button" class="survey-button secondary" id="view-form" disabled><img src="../../../assets/Icons/eye.svg" alt=""><i18n-text data-i18n="survey_view.ver_formulario">Ver formulario</i18n-text></button><a class="survey-button primary" id="detail-edit-survey" aria-disabled="true" tabindex="-1"><img src="../../../assets/Icons/pencil-icon.svg" alt=""><i18n-text data-i18n="survey_view.editar_encuesta">Editar encuesta</i18n-text></a></div>
        </div>

        <div class="survey-stats insight-stats" aria-label="Resumen de esta encuesta" data-i18n-aria-label="survey_view.resumen_de_esta_encuesta">
          <article class="stat-card"><span class="survey-icon cyan"><img src="../../../assets/Icons/survey-answers.svg" alt=""></span><div><span class="stat-label" data-i18n="survey_view.respuestas_recibidas">Respuestas recibidas</span><strong id="view-response-count">—</strong><small id="view-response-caption" data-i18n="survey_view.de_esta_encuesta">De esta encuesta</small></div></article>
          <article class="stat-card"><span class="survey-icon violet"><img src="../../../assets/Icons/survey-all.svg" alt=""></span><div><span class="stat-label" data-i18n="survey_view.preguntas">Preguntas</span><strong id="view-question-count">—</strong><small id="view-required-count" data-i18n="survey_view.contenido_del_formulario">Contenido del formulario</small></div></article>
          <article class="stat-card"><span class="survey-icon amber"><img src="../../../assets/Icons/survey-rate.svg" alt=""></span><div><span class="stat-label" data-i18n="survey_view.valoracion_media">Valoración media</span><strong id="view-rating">—</strong><small id="view-rating-caption" data-i18n="survey_view.sin_valoraciones_disponibles">Sin valoraciones disponibles</small></div></article>
          <article class="stat-card"><span class="survey-icon green"><img src="../../../assets/Icons/survey-finished.svg" alt=""></span><div><span class="stat-label" data-i18n="survey_view.cupo_utilizado">Cupo utilizado</span><strong id="view-capacity">—</strong><small id="view-capacity-caption" data-i18n="survey_view.respuestas_cupo_maximo">Respuestas / cupo máximo</small></div></article>
        </div>

        <div class="insight-tabs" role="tablist" aria-label="Detalle de la encuesta" data-i18n-aria-label="survey_view.detalle_de_la_encuesta">
          <button type="button" role="tab" id="tab-summary" data-insight-tab="summary" aria-controls="summary-panel" aria-selected="true"><img src="../../../assets/Icons/analytics-icon.svg" alt=""><i18n-text data-i18n="survey_view.resumen">Resumen</i18n-text></button>
          <button type="button" role="tab" id="tab-questions" data-insight-tab="questions" aria-controls="question-results-panel" aria-selected="false" tabindex="-1"><img src="../../../assets/Icons/list-icon.svg" alt=""><i18n-text data-i18n="survey_view.por_pregunta">Por pregunta</i18n-text></button>
          <button type="button" role="tab" id="tab-form" data-insight-tab="form" aria-controls="survey-form-panel" aria-selected="false" tabindex="-1"><img src="../../../assets/Icons/survey-icon.svg" alt=""><i18n-text data-i18n="survey_view.formulario">Formulario</i18n-text></button>
          <span class="insight-reference" id="survey-reference"></span>
        </div>

        <div class="insight-body">
          <section class="insight-summary" id="summary-panel" role="tabpanel" aria-labelledby="tab-summary">
            <div class="summary-main">
              <section class="insight-panel activity-panel"><div class="insight-panel-heading"><h2 data-i18n="survey_view.participacion">Participación</h2><span id="activity-caption" data-i18n="survey_view.respuestas_recibidas">Respuestas recibidas</span></div><div class="activity-content" id="survey-activity"></div></section>
              <section class="insight-panel question-overview"><div class="insight-panel-heading"><h2><i18n-text data-i18n="survey_view.resultados_por_pregunta">Resultados por pregunta</i18n-text> <span id="overview-question-count" class="quiet-count"></span></h2><button type="button" class="text-button" data-go-tab="questions"><i18n-text data-i18n="survey_view.ver_todas">Ver todas</i18n-text> <span aria-hidden="true">↗</span></button></div><div class="overview-column-head" aria-hidden="true"><span data-i18n="survey_view.pregunta">Pregunta</span><span data-i18n="survey_view.respuestas">Respuestas</span><span data-i18n="survey_view.promedio">Promedio</span></div><div id="question-overview-list" class="question-overview-list"></div><footer class="insight-panel-footer"><span id="overview-range"></span><nav class="survey-pagination" id="overview-pagination" aria-label="Páginas del resumen de preguntas" data-i18n-aria-label="survey_view.paginas_del_resumen_de_preguntas"></nav></footer></section>
            </div>
            <aside class="insight-panel survey-information"><div class="insight-panel-heading"><h2 data-i18n="survey_view.informacion_de_la_encuesta">Información de la encuesta</h2><button type="button" class="icon-button" id="more-survey-info" aria-label="Ver toda la información" data-i18n-aria-label="survey_view.ver_toda_la_informacion" disabled><img src="../../../assets/Icons/information-icon.svg" alt=""></button></div><div class="survey-information-body" id="survey-information"></div><section class="survey-access"><h3 data-i18n="survey_view.acceso_para_participantes">Acceso para participantes</h3><p id="access-caption" data-i18n="survey_view.enlace_y_codigo_de_acceso_de_la_encuesta">Enlace y código de acceso de la encuesta</p><div><button type="button" class="survey-button secondary" data-share-survey disabled><img src="../../../assets/Icons/share-icon.svg" alt=""><i18n-text data-i18n="survey_view.copiar_enlace">Copiar enlace</i18n-text></button><button type="button" class="survey-button secondary" data-share-survey disabled><img src="../../../assets/Icons/qr-icon.svg" alt=""><i18n-text data-i18n="survey_view.codigo_qr">Código QR</i18n-text></button></div></section></aside>
          </section>

          <div class="question-workspace" id="question-workspace" hidden>
            <aside class="insight-panel question-navigation"><div class="insight-panel-heading"><h2 id="question-nav-title" data-i18n="survey_view.preguntas">Preguntas</h2><span class="quiet-count" id="question-nav-count"></span></div><label class="survey-search question-search"><img src="../../../assets/Icons/search-icon.svg" alt=""><input type="search" id="view-question-search" placeholder="Buscar pregunta..." data-i18n-placeholder="survey_view.buscar_pregunta" aria-label="Buscar pregunta" data-i18n-aria-label="survey_view.buscar_pregunta_2" autocomplete="off"></label><div id="question-navigation-list" class="question-navigation-list"></div><footer class="insight-panel-footer"><span id="question-nav-range"></span><nav class="survey-pagination" id="question-nav-pagination" aria-label="Páginas del índice de preguntas" data-i18n-aria-label="survey_view.paginas_del_indice_de_preguntas"></nav></footer></aside>
            <div class="selected-question-workspace">
              <div class="mobile-question-picker"><label for="mobile-question" data-i18n="survey_view.seleccionar_pregunta">Seleccionar pregunta</label><div class="insight-select"><select id="mobile-question"></select></div></div>
              <section class="insight-panel question-results-panel" id="question-results-panel" role="tabpanel" aria-labelledby="tab-questions" hidden>
                <div class="selected-question-heading" id="selected-question-heading"></div><div class="question-analysis-scroll"><div class="question-result-metrics" id="question-result-metrics"></div><div id="question-results-content" class="question-results-content"></div></div><footer class="selected-question-footer"><button type="button" class="survey-button secondary" data-previous-question><img src="../../../assets/Icons/arrow-left.svg" alt=""><i18n-text data-i18n="survey_view.anterior">Anterior</i18n-text></button><span class="selected-question-position" role="status"></span><button type="button" class="survey-button secondary" data-next-question><i18n-text data-i18n="survey_view.siguiente">Siguiente</i18n-text> <span class="next-question-arrow" aria-hidden="true"></span></button></footer>
              </section>
              <section class="insight-panel survey-form-panel" id="survey-form-panel" role="tabpanel" aria-labelledby="tab-form" hidden>
                <div class="public-form-heading"><span class="form-eyebrow" data-i18n="survey_view.formulario_para_participantes">FORMULARIO PARA PARTICIPANTES</span><h2 id="public-form-title" data-i18n="survey_view.contenido_del_formulario">Contenido del formulario</h2><p id="public-form-introduction"></p></div><div class="public-form-content" id="public-form-content"></div><footer class="selected-question-footer"><button type="button" class="survey-button secondary" data-previous-question><img src="../../../assets/Icons/arrow-left.svg" alt=""><i18n-text data-i18n="survey_view.anterior">Anterior</i18n-text></button><span class="selected-question-position" role="status"></span><button type="button" class="survey-button secondary" data-next-question><i18n-text data-i18n="survey_view.siguiente">Siguiente</i18n-text> <span class="next-question-arrow" aria-hidden="true"></span></button></footer>
              </section>
            </div>
          </div>
        </div>
      </section>
    </div>
  </main>

  <dialog class="survey-dialog survey-info-dialog" id="survey-info-dialog" aria-labelledby="full-info-title"><div class="dialog-heading"><div><p class="eyebrow" data-i18n="survey_view.informacion_interna">INFORMACIÓN INTERNA</p><h2 id="full-info-title" data-i18n="survey_view.informacion_de_la_encuesta">Información de la encuesta</h2></div><button type="button" class="icon-button" data-close-info aria-label="Cerrar información" data-i18n-aria-label="survey_view.cerrar_informacion"><img src="../../../assets/Icons/cross-icon.svg" alt=""></button></div><div id="full-survey-info" class="full-survey-info"></div><div class="dialog-footer"><button type="button" class="survey-button secondary" data-close-info data-i18n="settings.cerrar">Cerrar</button></div></dialog>
</body>
</html>
