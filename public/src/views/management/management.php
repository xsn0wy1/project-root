<?php
require_once dirname(__DIR__, 4) . '/backend/php/core/page.php';
require_staff_page('management/management.php');
require_once dirname(__DIR__, 4) . '/backend/php/components/shell.php';
staff_page_open([
    'module' => 'management',
    'title_key' => 'trace_map.gestion',
    'title' => 'Gestión',
    'stylesheet' => 'management/management.css',
    'background' => true,
    'described_nav' => true,
    'font_stylesheet' => 'interface',
]);
?>
<?php staff_header('modules'); ?>

    <section class="management-panel" aria-labelledby="management-title">
      <div class="management-heading">
        <div>
          <p class="management-eyebrow" data-i18n="management.administracion">Administración</p>
          <h1 id="management-title" data-i18n="management.gestion_de_datos">Gestión de datos</h1>
        </div>
      </div>

      <div class="management-grid">
        <article class="management-card management-card--patients" data-required-role="records_manager" hidden>
          <span class="management-card__icon" aria-hidden="true">
            <img src="../../../assets/Icons/person-icon.svg" alt="">
          </span>
          <span class="management-card__copy">
            <strong data-i18n="management.pacientes">Pacientes</strong>
            <span data-i18n="management.gestiona_la_informacion_de_los_pacientes_del_hospital">Gestiona la información de los pacientes del hospital.</span>
          </span>
          <a href="patients.php" class="management-card__arrow" aria-label="Abrir gestión de pacientes" data-i18n-aria-label="management.abrir_gestion_de_pacientes">
            <img src="../../../assets/Icons/arrowhead-icon.svg" alt="" aria-hidden="true">
          </a>
        </article>

        <article class="management-card management-card--ambulances" data-required-role="dispatcher" hidden>
          <span class="management-card__icon" aria-hidden="true">
            <img src="../../../assets/Icons/ambulance-icon.png" alt="">
          </span>
          <span class="management-card__copy">
            <strong data-i18n="main_menu.ambulancias">Ambulancias</strong>
            <span data-i18n="management.registra_y_administra_las_ambulancias_del_sistema">Registra y administra las ambulancias del sistema.</span>
          </span>
          <a href="ambulances.php" class="management-card__arrow" aria-label="Abrir gestión de ambulancias" data-i18n-aria-label="management.abrir_gestion_de_ambulancias">
            <img src="../../../assets/Icons/arrowhead-icon.svg" alt="" aria-hidden="true">
          </a>
        </article>

        <article class="management-card management-card--doctors" data-required-role="records_manager" hidden>
          <span class="management-card__icon" aria-hidden="true">
            <img src="../../../assets/Icons/medkit-icon.png" alt="">
          </span>
          <span class="management-card__copy">
            <strong data-i18n="management.medicos">Médicos</strong>
            <span data-i18n="management.gestiona_el_personal_medico_y_sus_especialidades">Gestiona el personal médico y sus especialidades.</span>
          </span>
          <a href="doctors.php" class="management-card__arrow" aria-label="Abrir gestión de médicos" data-i18n-aria-label="management.abrir_gestion_de_medicos">
            <img src="../../../assets/Icons/arrowhead-icon.svg" alt="" aria-hidden="true">
          </a>
        </article>

        <article class="management-card management-card--staff" data-required-role="dispatcher" hidden>
          <span class="management-card__icon" aria-hidden="true">
            <img src="../../../assets/Icons/steering-wheel.svg" alt="">
          </span>
          <span class="management-card__copy">
            <strong data-i18n="trace_view.personal">Personal</strong>
            <span data-i18n="management.administra_el_personal_del_hospital_choferes_administrativos_etc">Administra el personal del hospital (choferes, administrativos, etc.).</span>
          </span>
          <a href="personnel.php" class="management-card__arrow" aria-label="Abrir gestión de personal" data-i18n-aria-label="management.abrir_gestion_de_personal">
            <img src="../../../assets/Icons/arrowhead-icon.svg" alt="" aria-hidden="true">
          </a>
        </article>

        <article class="management-card management-card--destinations" data-required-role="dispatcher" hidden>
          <span class="management-card__icon" aria-hidden="true">
            <img src="../../../assets/Icons/location-icon.svg" alt="">
          </span>
          <span class="management-card__copy">
            <strong data-i18n="management.destinos">Destinos</strong>
            <span data-i18n="management.gestiona_los_destinos_utilizados_en_los_traslados">Gestiona los destinos utilizados en los traslados.</span>
          </span>
          <a href="destinations.php" class="management-card__arrow" aria-label="Abrir gestión de destinos" data-i18n-aria-label="management.abrir_gestion_de_destinos">
            <img src="../../../assets/Icons/arrowhead-icon.svg" alt="" aria-hidden="true">
          </a>
        </article>

        <article class="management-card management-card--routes" data-required-role="dispatcher" hidden>
          <span class="management-card__icon" aria-hidden="true">
            <img src="../../../assets/Icons/map-icon.svg" alt="">
          </span>
          <span class="management-card__copy">
            <strong data-i18n="management.rutas">Rutas</strong>
            <span data-i18n="management.administra_las_rutas_disponibles_y_sus_caracteristicas">Administra las rutas disponibles y sus características.</span>
          </span>
          <a href="routes.php" class="management-card__arrow" aria-label="Abrir gestión de rutas" data-i18n-aria-label="management.abrir_gestion_de_rutas">
            <img src="../../../assets/Icons/arrowhead-icon.svg" alt="" aria-hidden="true">
          </a>
        </article>
      </div>
    </section>
  </main>
</body>

</html>
