<?php
require_once dirname(__DIR__, 3) . '/backend/php/core/page.php';
require_staff_page('main-menu.php');
require_once dirname(__DIR__, 3) . '/backend/php/components/shell.php';
staff_page_open([
    'module' => 'home',
    'title_key' => 'main_menu.hospital_de_clinicas',
    'title' => 'Hospital de Clínicas',
    'theme_styles' => 'main-menu',
    'stylesheets' => [],
    'src_prefix' => '../',
    'assets_prefix' => '../../assets/',
    'body_class' => '',
    'background' => true,
    'described_nav' => true,
    'nav_section' => 'home',
    'fonts' => 'https://fonts.googleapis.com/css2?family=Inclusive+Sans:ital,wght@0,300..700;1,300..700&family=Krub:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;1,200;1,300;1,400;1,500;1,600;1,700&display=swap',
]);
?>

    <?php staff_header('quick', '../../assets/'); ?>

    <section class="hero-panel">
      <div class="hero-copy">
        <h1 data-current-user-welcome data-i18n="main_menu.bienvenido_usuario">¡Bienvenido Usuario!</h1>
        <p data-i18n="main_menu.gestiona_documentos_y_traslados_de_manera_rapida_segura_y_eficiente">Gestiona documentos y traslados de manera rapida, segura y eficiente.</p>
      </div>

      <div class="cards-grid">
        <article class="feature-card">
          <div class="feature-icon">
            <img src="../../assets/Icons/folder-icon.svg" alt="Icono de carpeta" data-i18n-alt="main_menu.icono_de_carpeta">
          </div>

          <div>
            <h2 data-i18n="main_menu.carga_y_gestion_de_documentos">Carga y Gestión de Documentos</h2>
            <p data-i18n="main_menu.carga_organiza_y_gestiona_toda_la_documentacion_del_hospital_de_forma_segura_y_centralizad">Carga, organiza y gestiona toda la documentacion del hospital de forma segura y centralizada</p>
          </div>

          <a href="documents/documents.php" class="card-button" aria-label="Ir a carga y gestión de documentos" data-i18n-aria-label="main_menu.ir_a_carga_y_gestion_de_documentos"></a>
        </article>

        <article class="feature-card">
          <div class="feature-icon">
            <img src="../../assets/Icons/location-icon.svg" alt="Icono de ubicación" data-i18n-alt="main_menu.icono_de_ubicacion">
          </div>

          <div>
            <h2 data-i18n="main_menu.trazabilidad_de_ambulancias">Trazabilidad de Ambulancias</h2>
            <p data-i18n="main_menu.seguimiento_en_tiempo_real_de_ambulancias_y_traslados_para_una_mejor_coordinacion_y_atenci">Seguimiento en tiempo real de ambulancias y traslados para una mejor coordinación y atención</p>
          </div>

          <a href="traces/trace.php" class="card-button" aria-label="Ir a trazabilidad de ambulancias" data-i18n-aria-label="main_menu.ir_a_trazabilidad_de_ambulancias"></a>
        </article>
      </div>
    </section>
  </main>

  <div class="profile-wrapper">
    <div class="profile-menu">
      <button type="button" onclick="window.location.href='settings.php';" class="profile-menu-item" aria-label="Configuración" data-i18n-aria-label="main_menu.configuracion">
        <img src="../../assets/Icons/settings-icon.svg" alt="Configuración" data-i18n-alt="main_menu.configuracion">
      </button>
      <div class="profile-menu-divider"></div>
      <button type="button" data-logout class="profile-menu-item" aria-label="Cerrar sesión" data-i18n-aria-label="main_menu.cerrar_sesion">
        <img src="../../assets/Icons/logout-icon.svg" alt="Cerrar sesión" data-i18n-alt="main_menu.cerrar_sesion">
      </button>
    </div>

    <button type="button" class="profile-button" aria-label="Perfil de usuario" data-i18n-aria-label="main_menu.perfil_de_usuario"></button>
  </div>
</body>

</html>
