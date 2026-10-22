<?php
declare(strict_types=1);
require dirname(__DIR__, 4) . '/backend/php/core/page.php';
require_staff_page('traces/trace-view.php');
require dirname(__DIR__, 4) . '/backend/php/components/shell.php';
staff_page_open([
    'module' => 'traces',
    'main_class' => 'app-shell trace-view-shell',
    'theme_styles' => 'main-menu trace-view',
    'title_key' => 'trace_view.traslado_hospital_de_clinicas',
    'title' => 'Traslado · Hospital de Clínicas',
    'stylesheets' => ['traces/trace-view.css?v=20261008-route-preview'],
    'vendor_styles' => [['href' => '../../../assets/vendor/leaflet/leaflet.css', 'integrity' => 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=']],
    'font_stylesheet' => 'interface',
    'body_class' => '',
    'meta' => ['carto-basemap-key' => ''],
    'background' => true,
    'described_nav' => true,
    'nav_section' => 'transfer-view',
    'extra_scripts' => ['../../js/traces/trace-api.js?v=stage5', ['src' => '../../../assets/vendor/leaflet/leaflet.js', 'integrity' => 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo='], '../../js/traces/trace-map-shared.js?v=ui-repairs', '../../js/traces/trace-view.js?v=stage5'],
]);
?>


    <?php staff_header('quick'); ?>

    <section class="hero-panel trace-view-hero" aria-labelledby="transfer-title">
      <article class="transfer-detail" id="transfer-detail" data-transfer-id="" data-status="scheduled">
        <header class="transfer-header">
          <a class="back-link" href="trace.php"><span class="detail-icon color-icon back-icon" aria-hidden="true"></span><span data-i18n="trace_view.volver_a_traslados">Volver a Traslados</span></a>
          <div class="transfer-heading">
            <div class="transfer-identity">
              <div class="vehicle-icon"><img src="../../../assets/Icons/ambulance-icon.png" alt=""></div>
              <div class="transfer-heading-copy">
                <div class="transfer-title-row"><h1 id="transfer-title" data-i18n="trace_view.cargando_traslado">Cargando traslado...</h1><span class="status-badge" data-status-label></span></div>
                <p><i18n-text data-i18n="trace_view.ambulancia">Ambulancia</i18n-text> <span data-field="ambulance"></span><span class="identity-divider" aria-hidden="true">·</span><span data-field="plate"></span></p>
                <p class="created-at"></p>
              </div>
            </div>
            <div class="header-actions">
              <button class="detail-button" id="edit-transfer" type="button" disabled><img class="detail-icon" src="../../../assets/Icons/edit-icon.svg" alt=""><span data-i18n="trace_view.editar">Editar</span></button>
              <button class="detail-button danger-button" id="cancel-transfer" type="button" disabled><span class="detail-icon color-icon trash-icon" aria-hidden="true"></span><span data-i18n="trace_view.cancelar_traslado">Cancelar traslado</span></button>
            </div>
          </div>
        </header>

        <dl class="transfer-summary">
          <div class="summary-item"><img class="detail-icon" src="../../../assets/Icons/time-icon.svg" alt=""><div><dt data-i18n="trace_view.estado">Estado</dt><dd class="status-value" data-status-label></dd></div></div>
          <div class="summary-item"><img class="detail-icon" src="../../../assets/Icons/calendar-icon.svg" alt=""><div><dt data-i18n="trace_view.fecha_y_hora_de_inicio">Fecha y hora de inicio</dt><dd><time id="transfer-start-time"></time></dd></div></div>
          <div class="summary-item summary-route"><img class="detail-icon" src="../../../assets/Icons/location-icon.svg" alt=""><div><dt data-i18n="trace_view.origen_destino">Origen → Destino</dt><dd><span data-field="origin"></span><span class="route-arrow" aria-hidden="true">→</span><span data-field="destination"></span></dd></div></div>
          <div class="summary-item"><img class="detail-icon" src="../../../assets/Icons/steering-wheel.svg" alt=""><div><dt data-i18n="trace_view.conductor">Conductor</dt><dd data-field="driver"></dd></div></div>
        </dl>

        <div class="detail-tabs" role="tablist" aria-label="Información del traslado" data-i18n-aria-label="trace_view.informacion_del_traslado">
          <button type="button" role="tab" id="tab-patient" aria-controls="patient-panel" aria-selected="true" data-panel="patient-panel" data-i18n="trace_view.paciente">Paciente</button>
          <button type="button" role="tab" id="tab-transfer" aria-controls="transfer-panel" aria-selected="false" tabindex="-1" data-panel="transfer-panel" data-i18n="trace_view.traslado">Traslado</button>
          <button type="button" role="tab" id="tab-route" aria-controls="route-panel" aria-selected="false" tabindex="-1" data-panel="route-panel" data-i18n="trace_view.ruta">Ruta</button>
          <button type="button" role="tab" id="tab-timeline" aria-controls="timeline-panel" aria-selected="false" tabindex="-1" data-panel="timeline-panel" data-i18n="trace_view.actividad">Actividad</button>
          <button type="button" role="tab" id="tab-personnel" aria-controls="personnel-panel" aria-selected="false" tabindex="-1" data-panel="personnel-panel" data-i18n="trace_view.personal">Personal</button>
        </div>

        <div class="detail-grid">
          <section class="detail-panel patient-panel is-selected" id="patient-panel" aria-labelledby="patient-heading">
            <h2 id="patient-heading"><img class="detail-icon" src="../../../assets/Icons/human-icon.svg" alt=""><i18n-text data-i18n="trace_view.informacion_del_paciente">Información del paciente</i18n-text></h2>
            <dl class="patient-information">
              <div class="wide-field"><dt data-i18n="settings.nombre">Nombre</dt><dd data-field="patient"></dd></div>
              <div><dt data-i18n="trace_view.edad">Edad</dt><dd><span data-field="age"></span> <i18n-text data-i18n="trace_view.anos">años</i18n-text></dd></div>
              <div><dt data-i18n="trace_view.sexo">Sexo</dt><dd data-field="sex"></dd></div>
              <div class="wide-field"><dt data-i18n="trace_view.documento_historia_clinica">Documento / Historia clínica</dt><dd data-field="document"></dd></div>
              <div class="wide-field"><dt data-i18n="trace_view.acompanante">Acompañante</dt><dd data-field="companion"></dd></div>
              <div class="wide-field"><dt data-i18n="trace_view.notas">Notas</dt><dd data-field="patientNotes"></dd></div>
            </dl>
          </section>

          <section class="detail-panel transfer-panel" id="transfer-panel" aria-labelledby="details-heading">
            <h2 id="details-heading"><img class="detail-icon" src="../../../assets/Icons/list-icon.svg" alt=""><i18n-text data-i18n="trace_view.detalles_del_traslado">Detalles del traslado</i18n-text></h2>
            <dl class="transfer-information">
              <div><dt data-i18n="trace_view.motivo_del_traslado">Motivo del traslado</dt><dd data-field="reason"></dd></div>
              <div><dt data-i18n="trace_view.tipo_de_traslado">Tipo de traslado</dt><dd data-field="cargo"></dd></div>
              <div><dt data-i18n="trace_view.prioridad">Prioridad</dt><dd class="priority-value"><span class="priority-dot" aria-hidden="true"></span><span data-field="priority"></span></dd></div>
              <div><dt data-i18n="trace_view.acompanado_por_medico">Acompañado por médico</dt><dd data-field="withDoctor"></dd></div>
              <div><dt data-i18n="trace_view.equipamiento_especial">Equipamiento especial</dt><dd data-field="equipment"></dd></div>
              <div class="additional-notes"><dt data-i18n="trace_view.notas_adicionales">Notas adicionales</dt><dd data-field="notes"></dd></div>
            </dl>
          </section>

          <section class="detail-panel route-panel" id="route-panel" aria-labelledby="route-heading">
            <h2 id="route-heading"><img class="detail-icon" src="../../../assets/Icons/map-icon.svg" alt=""><i18n-text data-i18n="trace_view.ruta_del_traslado">Ruta del traslado</i18n-text></h2>
            <div class="map-space">
              <div class="route-map-canvas" id="ambulance-map" aria-label="Vista previa de la ruta del traslado" data-i18n-aria-label="trace_view.vista_previa_de_la_ruta_del_traslado"></div>
              <span class="map-preview-status" id="map-preview-status" role="status" data-i18n="trace_view.cargando_ruta">Cargando ruta…</span>
              <a class="map-preview-link" id="route-map-link" href="trace-map.php"
                aria-label="Abrir el traslado en el mapa" data-i18n-aria-label="trace_view.abrir_el_traslado_en_el_mapa"></a>
            </div>
            <dl class="route-metrics">
              <div><img class="detail-icon" src="../../../assets/Icons/location-icon.svg" alt=""><div><dt data-i18n="trace_view.distancia_total">Distancia total</dt><dd data-field="distance"></dd></div></div>
              <div><img class="detail-icon" src="../../../assets/Icons/time-icon.svg" alt=""><div><dt data-i18n="trace_view.tiempo_estimado">Tiempo estimado</dt><dd data-field="duration"></dd></div></div>
              <div><img class="detail-icon" src="../../../assets/Icons/time-icon.svg" alt=""><div><dt data-i18n="trace_view.tiempo_transcurrido">Tiempo transcurrido</dt><dd id="elapsed-time"></dd></div></div>
            </dl>
          </section>

          <section class="detail-panel timeline-panel" id="timeline-panel" aria-labelledby="timeline-heading">
            <h2 id="timeline-heading"><img class="detail-icon" src="../../../assets/Icons/time-icon.svg" alt=""><i18n-text data-i18n="trace_view.linea_de_tiempo">Línea de tiempo</i18n-text></h2>
            <ol class="transfer-timeline"></ol>
          </section>

          <section class="detail-panel personnel-panel" id="personnel-panel" aria-labelledby="personnel-heading">
            <h2 id="personnel-heading"><img class="detail-icon" src="../../../assets/Icons/people-svgrepo-com.svg" alt=""><i18n-text data-i18n="trace_view.acompanantes_y_personal">Acompañantes y personal</i18n-text></h2>
            <dl class="personnel-information">
              <div><dt><img class="detail-icon" src="../../../assets/Icons/steering-wheel.svg" alt=""><i18n-text data-i18n="trace_view.conductor">Conductor</i18n-text></dt><dd data-field="driver"></dd></div>
              <div><dt><img class="detail-icon" src="../../../assets/Icons/person-icon.svg" alt=""><i18n-text data-i18n="trace_view.acompanante_1">Acompañante 1</i18n-text></dt><dd data-field="companion"></dd></div>
              <div><dt><img class="detail-icon" src="../../../assets/Icons/person-icon.svg" alt=""><i18n-text data-i18n="trace_view.acompanante_2">Acompañante 2</i18n-text></dt><dd>—</dd></div>
              <div><dt><img class="detail-icon" src="../../../assets/Icons/doctor-icon.svg" alt=""><i18n-text data-i18n="trace_view.medico_a_cargo">Médico a cargo</i18n-text></dt><dd data-field="doctor"></dd></div>
            </dl>
          </section>
          <footer class="transfer-footer">
            <p class="view-message" id="view-message" role="status" aria-live="polite"></p>
            <button class="detail-button primary-button" id="update-status" type="button" disabled><img class="detail-icon" src="../../../assets/Icons/reload-icon.svg" alt=""><span data-i18n="trace_view.actualizar_estado">Actualizar estado</span></button>
          </footer>
        </div>
      </article>
    </section>
  </main>

  <dialog class="transfer-dialog" id="edit-dialog" aria-labelledby="edit-dialog-title">
    <form id="edit-form" novalidate>
      <header class="dialog-heading"><h2 id="edit-dialog-title" data-i18n="trace_view.editar_traslado">Editar traslado</h2><button class="icon-button" type="button" data-close-dialog aria-label="Cerrar" data-i18n-aria-label="settings.cerrar"><span class="detail-icon color-icon close-icon" aria-hidden="true"></span></button></header>
      <div class="edit-fields">
        <label><i18n-text data-i18n="trace_view.ambulancia">Ambulancia</i18n-text><input name="ambulance" list="edit-ambulances" required maxlength="255"></label>
        <label><i18n-text data-i18n="trace_view.conductor">Conductor</i18n-text><input name="driver" list="edit-crew" required maxlength="255"></label>
        <label><i18n-text data-i18n="trace_view.ruta_predefinida_opcional">Ruta predefinida (opcional)</i18n-text><select name="routeTemplate"><option value="" data-i18n="trace_view.escribir_recorrido_manualmente">Escribir recorrido manualmente</option></select></label>
        <label><i18n-text data-i18n="trace_view.origen">Origen</i18n-text><input name="origin" list="edit-facilities" required maxlength="255"></label>
        <label><i18n-text data-i18n="trace_view.destino">Destino</i18n-text><input name="destination" list="edit-facilities" required maxlength="255"></label>
        <label><i18n-text data-i18n="trace_view.paciente">Paciente</i18n-text><input name="patient" list="edit-patients" required maxlength="255"></label>
        <label><i18n-text data-i18n="trace_view.acompanante">Acompañante</i18n-text><input name="companion" list="edit-crew" maxlength="255"></label>
        <label><i18n-text data-i18n="trace_view.motivo">Motivo</i18n-text><span class="dialog-select"><select name="reason" required></select></span></label>
        <label><i18n-text data-i18n="trace_view.prioridad">Prioridad</i18n-text><span class="dialog-select"><select name="priority"><option value="Normal" data-i18n="trace_view.normal">Normal</option><option value="Baja" data-i18n="trace_view.baja">Baja</option><option value="Alta" data-i18n="trace_view.alta">Alta</option><option value="Crítica" data-i18n="trace_view.critica">Crítica</option></select></span></label>
        <label class="wide-field"><span><input type="checkbox" name="refreshRoute"> <i18n-text data-i18n="trace_view.actualizar_mapa_con_el_recorrido_seleccionado_al_guardar">Actualizar mapa con el recorrido seleccionado al guardar</i18n-text></span></label>
        <label class="wide-field"><i18n-text data-i18n="trace_view.notas_adicionales">Notas adicionales</i18n-text><textarea name="notes" rows="2" maxlength="1000"></textarea></label>
      </div>
      <p class="dialog-message" id="edit-message" role="status"></p>
      <div class="dialog-actions"><button class="detail-button" type="button" data-close-dialog data-i18n="trace_view.volver">Volver</button><button class="detail-button primary-button" type="submit"><img class="detail-icon" src="../../../assets/Icons/save-floppy.svg" alt=""><i18n-text data-i18n="trace_view.guardar_cambios">Guardar cambios</i18n-text></button></div>
    </form>
  </dialog>

  <dialog class="transfer-dialog compact-dialog" id="status-dialog" aria-labelledby="status-dialog-title">
    <form id="status-form">
      <header class="dialog-heading"><h2 id="status-dialog-title" data-i18n="trace_view.actualizar_estado">Actualizar estado</h2><button class="icon-button" type="button" data-close-dialog aria-label="Cerrar" data-i18n-aria-label="settings.cerrar"><span class="detail-icon color-icon close-icon" aria-hidden="true"></span></button></header>
      <label><i18n-text data-i18n="trace_view.estado_del_traslado">Estado del traslado</i18n-text><span class="dialog-select"><select id="transfer-status" name="status"><option value="in-progress"></option><option value="arrived" data-i18n="trace_view.en_destino">En destino</option><option value="completed" data-i18n="trace_view.finalizado">Finalizado</option></select></span></label>
      <div class="dialog-actions"><button class="detail-button" type="button" data-close-dialog data-i18n="trace_view.volver">Volver</button><button class="detail-button primary-button" type="submit" data-i18n="trace_view.guardar_estado">Guardar estado</button></div>
    </form>
  </dialog>

  <dialog class="transfer-dialog compact-dialog" id="cancel-dialog" aria-labelledby="cancel-dialog-title">
    <form id="cancel-form">
      <header class="dialog-heading"><h2 id="cancel-dialog-title" data-i18n="trace_view.cancelar_traslado">Cancelar traslado</h2><button class="icon-button" type="button" data-close-dialog aria-label="Cerrar" data-i18n-aria-label="settings.cerrar"><span class="detail-icon color-icon close-icon" aria-hidden="true"></span></button></header>
      <p data-i18n="trace_view.deseas_cancelar_este_traslado_esta_accion_marcara_el_traslado_como_cancelado">¿Deseas cancelar este traslado? Esta acción marcará el traslado como cancelado.</p>
      <div class="dialog-actions"><button class="detail-button" type="button" data-close-dialog data-i18n="trace_view.volver">Volver</button><button class="detail-button danger-button" type="submit" data-i18n="trace_view.cancelar_traslado">Cancelar traslado</button></div>
    </form>
  </dialog>
<datalist id="edit-ambulances"></datalist><datalist id="edit-crew"></datalist><datalist id="edit-facilities"></datalist><datalist id="edit-patients"></datalist>
</body>
</html>
