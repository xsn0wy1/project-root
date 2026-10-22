<?php
require_once dirname(__DIR__, 4) . '/backend/php/core/page.php';
require_staff_page('management/doctors.php');
require_once dirname(__DIR__, 4) . '/backend/php/components/shell.php';
staff_page_open([
    'module' => 'doctors',
    'title_key' => 'doctors.medicos_hospital_de_clinicas',
    'title' => 'Médicos | Hospital de Clínicas',
    'stylesheet' => 'management/doctors.css?v=20261019-i18n',
    'background' => false,
    'described_nav' => false,
    'font_stylesheet' => 'inclusive-variable',
    'script' => 'management/doctors.js?v=20261020-pilot',
]);
?>
<?php staff_header('catalog'); ?>

    <section class="doctors-content" aria-label="Gestión de médicos" data-i18n-aria-label="doctors.gestion_de_medicos">
      <div class="doctor-summary-row">
        <a class="doctor-back" href="management.php" aria-label="Volver a Gestión" data-i18n-aria-label="routes.volver_a_gestion" title="Volver a Gestión" data-i18n-title="routes.volver_a_gestion"><img src="../../../assets/Icons/arrow-left.svg" alt=""></a>
        <section class="doctor-metrics" aria-label="Resumen de médicos" data-i18n-aria-label="doctors.resumen_de_medicos">
          <article class="doctor-metric"><span class="metric-icon"><img src="../../../assets/Icons/doctor-icon.svg" alt=""></span><div><strong id="metric-total">12</strong><span data-i18n="doctors.medicos_registrados">Médicos registrados</span><small data-i18n="doctors.directorio_del_equipo_medico">Directorio del equipo médico.</small></div></article>
          <article class="doctor-metric"><span class="metric-icon"><img src="../../../assets/Icons/available-icon.svg" alt=""></span><div><strong id="metric-available">4</strong><span data-i18n="doctors.disponibles">Disponibles</span><small data-i18n="doctors.disponibilidad_para_atencion">Disponibilidad para atención.</small></div></article>
          <article class="doctor-metric"><span class="metric-icon"><img src="../../../assets/Icons/guard-icon.svg" alt=""></span><div><strong id="metric-on-call">3</strong><span data-i18n="doctors.de_guardia">De guardia</span><small data-i18n="doctors.asignados_a_una_guardia">Asignados a una guardia.</small></div></article>
          <article class="doctor-metric"><span class="metric-icon"><img src="../../../assets/Icons/specialty-icon.svg" alt=""></span><div><strong id="metric-specialties">9</strong><span data-i18n="doctors.especialidades">Especialidades</span><small data-i18n="doctors.areas_de_atencion_medica">Áreas de atención médica.</small></div></article>
        </section>
      </div>

      <div class="doctor-workspace">
        <section class="doctor-directory" aria-labelledby="directory-title">
          <div class="directory-heading">
            <div><h1 id="directory-title" data-i18n="doctors.listado_de_medicos">Listado de médicos</h1><p data-i18n="doctors.consulta_el_equipo_medico_sus_especialidades_y_su_disponibilidad">Consulta el equipo médico, sus especialidades y su disponibilidad.</p></div>
            <button type="button" class="new-doctor-button" id="new-doctor-button"><span class="plus-circle"><img src="../../../assets/Icons/plus-icon.svg" alt=""></span><i18n-text data-i18n="doctors.nuevo_medico">Nuevo médico</i18n-text></button>
          </div>
          <div class="doctor-controls">
            <label class="doctor-search"><img src="../../../assets/Icons/search-icon.svg" alt=""><input id="doctor-search" type="search" autocomplete="off" placeholder="Buscar por nombre, registro o teléfono..." data-i18n-placeholder="doctors.buscar_por_nombre_registro_o_telefono" aria-label="Buscar por nombre, registro o teléfono" data-i18n-aria-label="doctors.buscar_por_nombre_registro_o_telefono_2"></label>
            <label class="specialty-filter"><img src="../../../assets/Icons/specialty-icon.svg" alt=""><select id="specialty-filter" aria-label="Filtrar por especialidad" data-i18n-aria-label="doctors.filtrar_por_especialidad"><option value="" data-i18n="doctors.todas_las_especialidades">Todas las especialidades</option></select></label>
            <div class="doctor-status-filters" role="group" aria-label="Filtrar médicos por disponibilidad" data-i18n-aria-label="doctors.filtrar_medicos_por_disponibilidad">
              <button type="button" class="filter-button is-active" data-status="all" aria-pressed="true" data-i18n="trace.todos">Todos</button>
              <button type="button" class="filter-button" data-status="available" aria-pressed="false" data-i18n="doctors.disponibles">Disponibles</button>
              <button type="button" class="filter-button" data-status="consulting" aria-pressed="false" data-i18n="doctors.en_consulta">En consulta</button>
              <button type="button" class="filter-button" data-status="on_call" aria-pressed="false" data-i18n="doctors.de_guardia">De guardia</button>
              <button type="button" class="filter-button" data-status="inactive" aria-pressed="false" data-i18n="destinations.inactivos">Inactivos</button>
            </div>
          </div>

          <div class="doctor-table-container">
            <table class="doctor-table" aria-label="Listado de médicos" data-i18n-aria-label="doctors.listado_de_medicos">
              <tbody id="doctor-rows"></tbody>
            </table>
            <div class="doctor-empty" id="doctor-empty" hidden>
              <span class="empty-icon"><img src="../../../assets/Icons/search-icon.svg" alt=""></span>
              <h2 data-i18n="doctors.no_se_encontraron_medicos">No se encontraron médicos</h2><p id="empty-description" data-i18n="doctors.prueba_con_otro_nombre_o_ajusta_los_filtros">Prueba con otro nombre o ajusta los filtros.</p>
              <button type="button" class="secondary-button" id="clear-filters" data-i18n="routes.limpiar_filtros">Limpiar filtros</button>
            </div>
          </div>
          <footer class="directory-footer">
            <span id="results-summary" role="status" aria-live="polite"></span>
            <nav class="doctor-pagination" id="doctor-pagination" aria-label="Páginas de médicos" data-i18n-aria-label="doctors.paginas_de_medicos"></nav>
          </footer>
        </section>
        <button class="detail-scrim" id="detail-scrim" type="button" aria-label="Cerrar detalle del médico" data-i18n-aria-label="doctors.cerrar_detalle_del_medico" tabindex="-1" hidden></button>
        <aside class="doctor-detail" id="doctor-detail" role="dialog" aria-modal="true" aria-labelledby="detail-title" aria-hidden="true" inert>
          <header class="detail-heading"><div><h2 id="detail-title" data-i18n="doctors.detalle_del_medico">Detalle del médico</h2><p data-i18n="doctors.perfil_profesional_y_datos_de_contacto">Perfil profesional y datos de contacto.</p></div><button type="button" class="icon-button" id="close-detail" aria-label="Cerrar detalle" data-i18n-aria-label="ambulances.cerrar_detalle"><img src="../../../assets/Icons/cross-icon.svg" alt=""></button></header>
          <div class="detail-content" id="detail-content"></div>
          <div class="detail-actions"><button type="button" class="danger-button" id="delete-from-detail"><img src="../../../assets/Icons/trash-icon.svg" alt=""><i18n-text data-i18n="routes.eliminar">Eliminar</i18n-text></button><button type="button" class="primary-button" id="edit-from-detail"><img src="../../../assets/Icons/pencil-icon.svg" alt=""><i18n-text data-i18n="doctors.editar_medico">Editar médico</i18n-text></button></div>
        </aside>
      </div>
    </section>
  </main>

  <dialog class="doctor-dialog" id="doctor-dialog" aria-labelledby="form-title">
    <form id="doctor-form" novalidate>
      <header class="dialog-heading"><div><h2 id="form-title" data-i18n="doctors.nuevo_medico">Nuevo médico</h2><p id="form-subtitle" data-i18n="doctors.completa_la_informacion_para_agregar_un_medico_al_directorio">Completa la información para agregar un médico al directorio.</p></div><button type="button" class="icon-button" data-close-form aria-label="Cerrar formulario" data-i18n-aria-label="routes.cerrar_formulario"><img src="../../../assets/Icons/cross-icon.svg" alt=""></button></header>
      <div class="doctor-form-fields">
        <fieldset><legend><img src="../../../assets/Icons/person-icon.svg" alt=""><i18n-text data-i18n="doctors.informacion_personal">Información personal</i18n-text></legend>
          <div class="form-grid personal-fields">
            <label><i18n-text data-i18n="doctors.professional_title">Tratamiento</i18n-text><span class="field-control"><select name="title" aria-label="Tratamiento" data-i18n-aria-label="doctors.professional_title"><option value="Dr." data-i18n="doctors.dr">Dr.</option><option value="Dra." data-i18n="doctors.dra">Dra.</option></select></span></label>
            <label class="name-field"><i18n-text data-i18n="doctors.nombre_completo">Nombre completo *</i18n-text><span class="field-control"><img src="../../../assets/Icons/person-icon.svg" alt=""><input name="name" type="text" placeholder="Ej. Carlos Méndez" data-i18n-placeholder="doctors.ej_carlos_mendez" autocomplete="name" maxlength="100" required></span></label>
            <label><i18n-text data-i18n="doctors.documento_c_i">Documento (C.I.) *</i18n-text><span class="field-control"><img src="../../../assets/Icons/id-badge.svg" alt=""><input name="document" type="text" placeholder="Ej. 4.567.890-1" data-i18n-placeholder="personnel.ej_4_567_890_1" maxlength="25" required></span></label>
            <label><i18n-text data-i18n="doctors.telefono">Teléfono *</i18n-text><span class="field-control"><img src="../../../assets/Icons/phone-icon.svg" alt=""><input name="phone" type="tel" placeholder="Ej. 099 123 456" data-i18n-placeholder="personnel.ej_099_123_456" autocomplete="tel" maxlength="25" required></span></label>
            <label><i18n-text data-i18n="doctors.correo_electronico">Correo electrónico</i18n-text><span class="field-control"><input name="email" type="email" placeholder="nombre@ejemplo.com" data-i18n-placeholder="doctors.nombre_ejemplo_com" autocomplete="email" maxlength="120"></span></label>
          </div>
        </fieldset>
        <fieldset><legend><img src="../../../assets/Icons/doctor-icon.svg" alt=""><i18n-text data-i18n="doctors.informacion_profesional">Información profesional</i18n-text></legend>
          <div class="form-grid">
            <label><i18n-text data-i18n="doctors.registro_msp">Registro MSP *</i18n-text><span class="field-control"><img src="../../../assets/Icons/driver-badge.svg" alt=""><input name="license" type="text" placeholder="Ej. MSP-10482" data-i18n-placeholder="doctors.ej_msp_10482" maxlength="30" required></span></label>
            <label><i18n-text data-i18n="doctors.especialidad">Especialidad *</i18n-text><span class="field-control"><img src="../../../assets/Icons/specialty-icon.svg" alt=""><input name="specialty" type="text" list="specialty-options" placeholder="Ej. Cardiología" data-i18n-placeholder="doctors.ej_cardiologia" maxlength="80" required></span></label>
            <label><i18n-text data-i18n="doctors.servicio">Servicio *</i18n-text><span class="field-control"><img src="../../../assets/Icons/ambulatory-icon.svg" alt=""><input name="service" type="text" list="service-options" placeholder="Ej. Consultorios Externos" data-i18n-placeholder="doctors.ej_consultorios_externos" maxlength="80" required></span></label>
            <label><i18n-text data-i18n="doctors.consultorio_area">Consultorio / Área</i18n-text><span class="field-control"><img src="../../../assets/Icons/location-icon.svg" alt=""><input name="office" type="text" placeholder="Ej. Consultorio 12" data-i18n-placeholder="doctors.ej_consultorio_12" maxlength="80"></span></label>
          </div>
        </fieldset>
        <fieldset><legend><img src="../../../assets/Icons/calendar-icon.svg" alt=""><i18n-text data-i18n="doctors.turno_y_disponibilidad">Turno y disponibilidad</i18n-text></legend>
          <div class="form-grid">
            <label><i18n-text data-i18n="doctors.disponibilidad">Disponibilidad</i18n-text><span class="field-control"><span class="status-dot" id="form-status-dot" aria-hidden="true"></span><select name="status"><option value="available" data-i18n="doctors.disponible">Disponible</option><option value="consulting" data-i18n="doctors.en_consulta">En consulta</option><option value="on_call" data-i18n="doctors.de_guardia">De guardia</option><option value="inactive" data-i18n="destinations.inactivo">Inactivo</option></select></span></label>
            <label><i18n-text data-i18n="doctors.turno">Turno</i18n-text><span class="field-control"><img src="../../../assets/Icons/time-icon.svg" alt=""><select name="shift"><option value="morning" data-i18n="doctors.manana">Mañana</option><option value="afternoon" data-i18n="doctors.tarde">Tarde</option><option value="night" data-i18n="doctors.noche">Noche</option><option value="rotating" data-i18n="doctors.rotativo">Rotativo</option></select></span></label>
            <label><i18n-text data-i18n="doctors.dias_de_atencion">Días de atención</i18n-text><span class="field-control"><select name="days"><option value="weekdays" data-i18n="doctors.lunes_a_viernes">Lunes a viernes</option><option value="mon_wed_fri" data-i18n="doctors.lunes_miercoles_y_viernes">Lunes, miércoles y viernes</option><option value="tue_thu" data-i18n="doctors.martes_y_jueves">Martes y jueves</option><option value="weekends" data-i18n="doctors.fines_de_semana">Fines de semana</option><option value="rotating" data-i18n="doctors.rotativo">Rotativo</option></select></span></label>
            <div class="time-fields"><label><i18n-text data-i18n="doctors.desde">Desde</i18n-text><span class="field-control"><input name="startTime" type="time" aria-label="Hora de inicio" data-i18n-aria-label="trace_new.hora_de_inicio"></span></label><label><i18n-text data-i18n="doctors.hasta">Hasta</i18n-text><span class="field-control"><input name="endTime" type="time" aria-label="Hora de fin" data-i18n-aria-label="doctors.hora_de_fin"></span></label></div>
            <label class="full-width"><i18n-text data-i18n="doctors.observaciones">Observaciones</i18n-text><textarea name="notes" rows="2" placeholder="Información adicional del médico..." data-i18n-placeholder="doctors.informacion_adicional_del_medico" maxlength="500"></textarea></label>
          </div>
        </fieldset>
      </div>
      <p class="form-error" id="form-error" role="alert" hidden></p>
      <footer class="dialog-actions"><button type="button" class="secondary-button" data-close-form data-i18n="settings.cancelar">Cancelar</button><button type="submit" class="primary-button"><img src="../../../assets/Icons/save-floppy.svg" alt=""><span id="save-label" data-i18n="doctors.guardar_medico">Guardar médico</span></button></footer>
    </form>
  </dialog>
  <datalist id="specialty-options"></datalist>
  <datalist id="service-options"></datalist>

  <dialog class="doctor-dialog delete-dialog" id="delete-dialog" aria-labelledby="delete-title" aria-describedby="delete-description">
    <div class="delete-content">
      <header class="dialog-heading"><div><span class="delete-symbol"><img src="../../../assets/Icons/trash-icon.svg" alt=""></span><h2 id="delete-title" data-i18n="doctors.eliminar_este_medico">¿Eliminar este médico?</h2></div><button type="button" class="icon-button" data-close-delete aria-label="Cerrar confirmación" data-i18n-aria-label="ambulances.cerrar_confirmacion"><img src="../../../assets/Icons/cross-icon.svg" alt=""></button></header>
      <p id="delete-description"></p><p class="delete-note" data-i18n="doctors.se_conservara_en_inactivos_y_en_el_historial_de_los_pacientes">Se conservará en Inactivos y en el historial de los pacientes.</p>
      <footer class="dialog-actions"><button type="button" class="secondary-button" data-close-delete autofocus data-i18n="settings.cancelar">Cancelar</button><button type="button" class="danger-button" id="confirm-delete" data-i18n="doctors.eliminar_medico">Eliminar médico</button></footer>
    </div>
  </dialog>
  <div class="doctor-toast" id="doctor-toast" role="status" aria-live="polite" hidden></div>
</body>
</html>
