<?php
require_once dirname(__DIR__, 4) . '/backend/php/core/page.php';
require_staff_page('management/patients.php');
require_once dirname(__DIR__, 4) . '/backend/php/components/shell.php';
staff_page_open([
    'module' => 'patients',
    'title_key' => 'patients.pacientes_hospital_de_clinicas',
    'title' => 'Pacientes | Hospital de Clínicas',
    'stylesheet' => 'management/patients.css?v=review-cleanup1',
    'background' => true,
    'described_nav' => true,
    'font_stylesheet' => 'interface',
    'script' => 'management/patients.js?v=20261020-pilot',
]);
?>
<?php staff_header('quick'); ?>

    <section class="hero-panel patients-hero" aria-label="Gestión de pacientes" data-i18n-aria-label="patients.gestion_de_pacientes">
      <div class="summary-row">
        <a class="summary-back" href="management.php" aria-label="Volver a Gestión" data-i18n-aria-label="routes.volver_a_gestion">
          <img src="../../../assets/Icons/arrow-left.svg" alt="" aria-hidden="true">
        </a>
        <section class="metrics" aria-label="Resumen de pacientes" data-i18n-aria-label="patients.resumen_de_pacientes">
          <article class="metric-card"><span class="metric-icon"><img src="../../../assets/Icons/people-svgrepo-com.svg" alt="" aria-hidden="true"></span><div><strong data-patient-stat="active">—</strong><span data-i18n="patients.pacientes_activos">Pacientes activos</span><small data-i18n="patients.actualmente_en_seguimiento">Actualmente en seguimiento.</small></div></article>
          <article class="metric-card"><span class="metric-icon"><img src="../../../assets/Icons/bed-icon.svg" alt="" aria-hidden="true"></span><div><strong data-patient-stat="inpatient">—</strong><span data-i18n="patients.internados">Internados</span><small data-i18n="patients.pacientes_hospitalizados">Pacientes hospitalizados.</small></div></article>
          <article class="metric-card"><span class="metric-icon"><img src="../../../assets/Icons/leave-icon.svg" alt="" aria-hidden="true"></span><div><strong data-patient-stat="discharged_today">—</strong><span data-i18n="patients.altas_hoy">Altas hoy</span><small data-i18n="patients.pacientes_dados_de_alta">Pacientes dados de alta.</small></div></article>
        </section>
      </div>

    <div class="patient-workspace" id="patient-workspace">
      <section class="directory-panel" aria-labelledby="directory-title">
        <div class="panel-heading">
          <div><h1 id="directory-title" data-i18n="patients.listado_de_pacientes">Listado de pacientes</h1><p data-i18n="patients.busca_y_selecciona_un_paciente_para_ver_su_informacion_detallada">Busca y selecciona un paciente para ver su información detallada.</p></div>
          <button type="button" class="new-patient-button" id="new-patient-button"><span class="plus-circle"><img src="../../../assets/Icons/plus-icon.svg" alt="" aria-hidden="true"></span><i18n-text data-i18n="patients.nuevo_paciente" style="display:contents">Nuevo paciente</i18n-text></button>
        </div>
        <div class="directory-controls">
          <label class="patient-search"><img src="../../../assets/Icons/search-icon.svg" alt="" aria-hidden="true"><input id="patient-search" type="search" autocomplete="off" placeholder="Buscar por nombre, documento o teléfono..." data-i18n-placeholder="patients.buscar_por_nombre_documento_o_telefono" aria-label="Buscar por nombre, documento o teléfono" data-i18n-aria-label="patients.buscar_por_nombre_documento_o_telefono_2"></label>
          <div class="status-filters" role="group" aria-label="Filtrar pacientes por estado" data-i18n-aria-label="patients.filtrar_pacientes_por_estado">
            <button type="button" class="filter-button is-active" data-filter="all" aria-pressed="true" data-i18n="trace.todos">Todos</button>
            <button type="button" class="filter-button" data-filter="inpatient" aria-pressed="false" data-i18n="patients.internados">Internados</button>
            <button type="button" class="filter-button" data-filter="outpatient" aria-pressed="false" data-i18n="patients.ambulatorios">Ambulatorios</button>
            <button type="button" class="filter-button" data-filter="emergency" aria-pressed="false" data-i18n="patients.urgencias">Urgencias</button>
            <button type="button" class="filter-button" id="patient-archive-toggle" aria-pressed="false" data-i18n="personnel.ver_archivados">Ver archivados</button>
          </div>
        </div>
        <div class="patient-list" id="patient-list" aria-live="polite"></div>
        <p class="empty-state" id="patient-empty" hidden data-i18n="patients.no_se_encontraron_pacientes_con_estos_criterios">No se encontraron pacientes con estos criterios.</p>
        <footer class="directory-footer"><span id="results-summary"></span><nav class="pagination" id="patient-pagination" aria-label="Páginas de pacientes" data-i18n-aria-label="patients.paginas_de_pacientes"></nav></footer>
      </section>

      <aside class="detail-panel" id="detail-panel" aria-labelledby="detail-title" aria-hidden="true" inert>
        <div class="detail-heading"><div><h2 id="detail-title" data-i18n="patients.detalle_del_paciente">Detalle del paciente</h2><p data-i18n="patients.informacion_completa_del_paciente_seleccionado">Información completa del paciente seleccionado.</p></div><button type="button" id="close-detail" class="icon-button" aria-label="Cerrar detalle" data-i18n-aria-label="ambulances.cerrar_detalle"><img src="../../../assets/Icons/cross-icon.svg" alt="" aria-hidden="true"></button></div>
        <div id="detail-content"></div>
        <div class="detail-actions"><button type="button" class="cancel-button" id="delete-from-detail"><img src="../../../assets/Icons/trash-icon.svg" alt="" aria-hidden="true"><i18n-text data-i18n="patients.archivar" style="display:contents">Archivar</i18n-text></button><button type="button" class="save-button" id="edit-from-detail"><img src="../../../assets/Icons/pencil-icon.svg" alt="" aria-hidden="true"><i18n-text data-i18n="patients.editar_paciente" style="display:contents">Editar paciente</i18n-text></button></div>
      </aside>
    </div>
    </section>
  </main>

  <dialog class="patient-dialog" id="patient-dialog" aria-labelledby="new-patient-title">
    <form id="patient-form" method="dialog">
      <div class="dialog-heading"><div><h2 id="new-patient-title" data-i18n="patients.nuevo_paciente">Nuevo paciente</h2><p id="form-subtitle" data-i18n="patients.completa_los_datos_para_registrar_un_paciente_del_hospital">Completa los datos para registrar un paciente del hospital.</p></div><button type="button" class="icon-button" id="close-patient-dialog" aria-label="Cerrar formulario" data-i18n-aria-label="routes.cerrar_formulario"><img src="../../../assets/Icons/cross-icon.svg" alt="" aria-hidden="true"></button></div>
      <div class="dialog-fields">
        <fieldset class="patient-form-section">
          <legend><img src="../../../assets/Icons/person-icon.svg" alt="" aria-hidden="true"><i18n-text data-i18n="trace_view.informacion_del_paciente" style="display:contents">Información del paciente</i18n-text></legend>
          <div class="form-section-fields">
            <label><i18n-text data-i18n="personnel.nombre_completo" style="display:contents">Nombre completo</i18n-text> <span class="field-control"><img src="../../../assets/Icons/person-icon.svg" alt="" aria-hidden="true"><input name="name" type="text" maxlength="150" autocomplete="name" placeholder="Ej. María González López" data-i18n-placeholder="patients.ej_maria_gonzalez_lopez" required></span></label>
            <label><i18n-text data-i18n="patients.documento_ci" style="display:contents">Documento (CI)</i18n-text> <span class="field-control"><img src="../../../assets/Icons/id-badge.svg" alt="" aria-hidden="true"><input name="dni" type="text" maxlength="20" placeholder="Ej. 12345678" data-i18n-placeholder="patients.ej_12345678" required></span></label>
            <label><i18n-text data-i18n="patients.edad_registrada" style="display:contents">Edad registrada</i18n-text> <span class="field-control"><img src="../../../assets/Icons/calendar-icon.svg" alt="" aria-hidden="true"><input name="age" type="number" min="0" max="120" placeholder="Ej. 34" data-i18n-placeholder="patients.ej_34"></span></label>
            <label><i18n-text data-i18n="patients.fecha_de_nacimiento_opcional" style="display:contents">Fecha de nacimiento (opcional)</i18n-text> <span class="field-control"><input name="dateOfBirth" type="date"></span></label>
            <label><i18n-text data-i18n="patients.sexo_opcional" style="display:contents">Sexo (opcional)</i18n-text> <span class="field-control"><select name="sex"><option value="" data-i18n="patients.sin_indicar">Sin indicar</option><option value="female" data-i18n="trace_new.femenino">Femenino</option><option value="male" data-i18n="trace_new.masculino">Masculino</option><option value="other" data-i18n="trace_new.otro">Otro</option><option value="unspecified" data-i18n="patients.desconocido">Desconocido</option></select></span></label>
            <label><i18n-text data-i18n="destinations.telefono" style="display:contents">Teléfono</i18n-text> <span class="field-control"><img src="../../../assets/Icons/phone-icon.svg" alt="" aria-hidden="true"><input name="phone" type="tel" maxlength="25" autocomplete="tel" placeholder="Ej. 099 123 456" data-i18n-placeholder="personnel.ej_099_123_456" required></span></label>
            <label><i18n-text data-i18n="patients.grupo_sanguineo" style="display:contents">Grupo sanguíneo</i18n-text> <span class="field-control"><img src="../../../assets/Icons/bloodtype-icon.svg" alt="" aria-hidden="true"><input name="bloodType" type="text" maxlength="5" placeholder="Ej. O+" data-i18n-placeholder="patients.ej_o"></span></label>
            <label><i18n-text data-i18n="patients.alergias" style="display:contents">Alergias</i18n-text> <span class="field-control"><img src="../../../assets/Icons/alert-icon.svg" alt="" aria-hidden="true"><input name="allergies" type="text" maxlength="100" placeholder="Ninguna conocida" data-i18n-placeholder="patients.ninguna_conocida"></span></label>
          </div>
        </fieldset>
        <fieldset class="patient-form-section">
          <legend><img src="../../../assets/Icons/phone-icon.svg" alt="" aria-hidden="true"><i18n-text data-i18n="patients.contacto_de_emergencia" style="display:contents">Contacto de emergencia</i18n-text></legend>
          <div class="form-section-fields">
            <label><i18n-text data-i18n="personnel.nombre_completo" style="display:contents">Nombre completo</i18n-text> <span class="field-control"><img src="../../../assets/Icons/person-icon.svg" alt="" aria-hidden="true"><input name="emergencyName" type="text" maxlength="100" placeholder="Ej. José González" data-i18n-placeholder="patients.ej_jose_gonzalez"></span></label>
            <label><i18n-text data-i18n="destinations.telefono" style="display:contents">Teléfono</i18n-text> <span class="field-control"><img src="../../../assets/Icons/phone-icon.svg" alt="" aria-hidden="true"><input name="emergencyPhone" type="tel" maxlength="25" placeholder="Ej. 098 765 432" data-i18n-placeholder="patients.ej_098_765_432"></span></label>
            <label><i18n-text data-i18n="patients.parentesco" style="display:contents">Parentesco</i18n-text> <span class="field-control"><img src="../../../assets/Icons/people-svgrepo-com.svg" alt="" aria-hidden="true"><input name="emergencyRelationship" type="text" maxlength="50" placeholder="Ej. Padre, hermana o cónyuge" data-i18n-placeholder="patients.ej_padre_hermana_o_conyuge"></span></label>
          </div>
        </fieldset>
        <fieldset class="patient-form-section">
          <legend><img src="../../../assets/Icons/doctor-icon.svg" alt="" aria-hidden="true"><i18n-text data-i18n="patients.estado_clinico" style="display:contents">Estado clínico</i18n-text></legend>
          <div class="form-section-fields">
            <label><i18n-text data-i18n="trace_view.estado" style="display:contents">Estado</i18n-text> <span class="field-control"><img src="../../../assets/Icons/hrate-icon.svg" alt="" aria-hidden="true"><select name="status"><option value="" data-i18n="patients.sin_registro_clinico">Sin registro clínico</option><option value="inpatient" data-i18n="patients.internado">Internado</option><option value="outpatient" data-i18n="patients.ambulatorio">Ambulatorio</option><option value="emergency" data-i18n="patients.urgencias">Urgencias</option><option value="discharged" data-i18n="patients.status_discharged">Alta</option></select></span></label>
            <label><i18n-text data-i18n="patients.habitacion_o_area" style="display:contents">Habitación o área</i18n-text> <span class="field-control"><img src="../../../assets/Icons/location-icon.svg" alt="" aria-hidden="true"><input name="room" type="text" maxlength="80" placeholder="Ej. Habitación 204" data-i18n-placeholder="patients.ej_habitacion_204" required></span></label>
            <label><i18n-text data-i18n="patients.servicio" style="display:contents">Servicio</i18n-text> <span class="field-control"><img src="../../../assets/Icons/ambulatory-icon.svg" alt="" aria-hidden="true"><input name="department" type="text" maxlength="80" list="patient-departments" placeholder="Ej. Medicina Interna" data-i18n-placeholder="patients.ej_medicina_interna" required></span></label>
            <label><i18n-text data-i18n="patients.medico_asignado" style="display:contents">Médico asignado</i18n-text> <span class="field-control"><img src="../../../assets/Icons/doctor-icon.svg" alt="" aria-hidden="true"><select name="doctor"><option value="" data-i18n="patients.sin_medico_asignado">Sin médico asignado</option></select></span></label>
            <label><i18n-text data-i18n="patients.especialidad" style="display:contents">Especialidad</i18n-text> <span class="field-control"><img src="../../../assets/Icons/specialty-icon.svg" alt="" aria-hidden="true"><input name="doctorSpecialty" type="text" maxlength="80" placeholder="Ej. Clínica Médica" data-i18n-placeholder="patients.ej_clinica_medica"></span></label>
            <label><i18n-text data-i18n="patients.fecha_de_ingreso" style="display:contents">Fecha de ingreso</i18n-text> <span class="field-control"><input name="admissionDate" type="date"></span></label>
            <label><i18n-text data-i18n="patients.estado_clinico_actual" style="display:contents">Estado clínico actual</i18n-text> <span class="field-control"><input name="clinicalState" type="text" maxlength="100" placeholder="Sin indicar" data-i18n-placeholder="patients.sin_indicar"></span></label>
            <label><i18n-text data-i18n="patients.diagnostico" style="display:contents">Diagnóstico</i18n-text> <span class="field-control"><textarea name="diagnosis" maxlength="1000" placeholder="Sin diagnóstico registrado" data-i18n-placeholder="patients.sin_diagnostico_registrado"></textarea></span></label>
          </div>
        </fieldset>
      </div>
      <datalist id="patient-departments"></datalist>
      <div class="dialog-actions"><button type="button" class="cancel-button" id="cancel-patient" data-i18n="settings.cancelar">Cancelar</button><button type="submit" class="save-button"><img src="../../../assets/Icons/save-floppy.svg" alt="" aria-hidden="true"><span id="save-label" data-i18n="patients.guardar_paciente">Guardar paciente</span></button></div>
    </form>
  </dialog>
  <dialog class="patient-dialog delete-dialog" id="delete-dialog" aria-labelledby="delete-title" aria-describedby="delete-description">
    <div class="delete-dialog-content">
      <div class="dialog-heading"><div><h2 id="delete-title" data-i18n="patients.archivar_este_paciente">¿Archivar este paciente?</h2><p id="delete-description"></p></div><button type="button" class="icon-button" data-close-delete aria-label="Cerrar confirmación" data-i18n-aria-label="ambulances.cerrar_confirmacion"><img src="../../../assets/Icons/cross-icon.svg" alt="" aria-hidden="true"></button></div>

      <div class="dialog-actions"><button type="button" class="cancel-button" data-close-delete autofocus data-i18n="settings.cancelar">Cancelar</button><button type="button" class="delete-button" id="confirm-delete" data-i18n="patients.archivar_paciente">Archivar paciente</button></div>
    </div>
  </dialog>
  <div class="patient-toast" id="patient-toast" role="status" aria-live="polite" hidden></div>
</body>
</html>
