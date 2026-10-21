<?php
require_once dirname(__DIR__, 4) . '/backend/php/core/page.php';
require_staff_page('management/accounts.php');
require_once dirname(__DIR__, 4) . '/backend/php/components/shell.php';
staff_page_open([
    'module' => 'accounts',
    'title_key' => 'accounts.cuentas_hospital_de_clinicas',
    'title' => 'Cuentas | Hospital de Clínicas',
    'theme_styles' => 'main-menu routes accounts documents',
    'stylesheets' => ['management/routes.css?v=20261004-controls-design', 'documents/documents.css?v=20261012-documents', 'management/accounts.css?v=20261020-dropdown'],
    'body_class' => 'routes-page accounts-page',
    'described_nav' => false,
    'script' => 'management/accounts.js?v=20261020-dropdown',
]);
?>

    <div class="routes-layout">
      <?php staff_header('plain'); ?>

      <section class="routes-content" aria-label="Gestión de cuentas" data-i18n-aria-label="accounts.gestion_de_cuentas">
        <div class="routes-heading"><h1 data-i18n="accounts.cuentas_del_personal">Cuentas del personal</h1><a href="management.php" class="routes-back" aria-label="Volver a Gestión" data-i18n-aria-label="routes.volver_a_gestion"><img src="../../../assets/Icons/arrow-left.svg" alt=""></a></div>
        <div class="routes-workspace">
          <section class="route-directory" aria-label="Listado de cuentas" data-i18n-aria-label="accounts.listado_de_cuentas">
            <div class="accounts-toolbar">
              <label class="route-search"><img src="../../../assets/Icons/search-icon.svg" alt=""><input type="search" id="account-search" aria-label="Buscar cuenta" data-i18n-aria-label="accounts.buscar_cuenta" placeholder="Buscar usuario o email..." data-i18n-placeholder="accounts.buscar_usuario_o_email"></label>
              <div class="catalog-filters">
                <div class="document-filter account-status-filter">
                  <button type="button" class="filter-trigger" id="account-state-button" aria-expanded="false" aria-controls="account-state-menu" aria-label="Estado de cuenta" data-i18n-aria-label="accounts.estado_de_cuenta">
                    <span id="account-state-label" data-i18n="routes.todas">Todas</span><span class="filter-chevron" aria-hidden="true"></span>
                  </button>
                  <div class="filter-menu" id="account-state-menu" role="group" aria-label="Estado de cuenta" data-i18n-aria-label="accounts.estado_de_cuenta" hidden>
                    <button type="button" data-account-state="" aria-pressed="true" data-i18n="routes.todas">Todas</button>
                    <button type="button" data-account-state="pending" aria-pressed="false" data-i18n="accounts.pendientes">Pendientes</button>
                    <button type="button" data-account-state="active" aria-pressed="false" data-i18n="routes.activas">Activas</button>
                    <button type="button" data-account-state="disabled" aria-pressed="false" data-i18n="accounts.desactivadas">Desactivadas</button>
                  </div>
                </div>
              </div>
            </div>
            <div class="route-list detail-scroll" id="account-list"></div>
            <div class="accounts-placeholder" id="account-empty" hidden>
              <span class="account-empty-icon" aria-hidden="true"><img src="../../../assets/Icons/person-icon.svg" alt=""></span>
              <h2 id="account-empty-title" data-i18n="accounts.no_accounts">Aún no hay usuarios</h2>
              <p id="account-empty-description" data-i18n="accounts.no_accounts_help">Las solicitudes de registro del personal aparecerán aquí.</p>
            </div>
            <p id="account-count" role="status"></p>
          </section>
          <aside class="route-detail" aria-label="Detalle de cuenta" data-i18n-aria-label="accounts.detalle_de_cuenta">
            <div class="detail-scroll is-empty" id="account-detail"><div class="accounts-placeholder"><span class="account-empty-icon" aria-hidden="true"><img src="../../../assets/Icons/person-icon.svg" alt=""></span><h2 data-i18n="accounts.select_user_to_begin">Selecciona un usuario para comenzar</h2><p data-i18n="accounts.selection_help">Aquí verás su información y podrás administrar su acceso y sus permisos.</p></div></div>
          </aside>
        </div>
      </section>
    </div>
  </main>
  <dialog class="route-dialog" id="account-dialog" aria-labelledby="account-title">
    <form id="account-form" novalidate>
      <div class="dialog-heading"><h2 id="account-title" data-i18n="accounts.administrar_cuenta">Administrar cuenta</h2></div>
      <div class="form-scroll"><p id="account-name"></p><fieldset id="account-roles" class="form-section account-roles"><legend data-i18n="accounts.permisos_de_la_cuenta">Permisos de la cuenta</legend>
        <label><input type="checkbox" name="role" value="staff"> <i18n-text data-i18n="trace_view.personal" style="display:contents">Personal</i18n-text></label>
        <label><input type="checkbox" name="role" value="records_manager"> <i18n-text data-i18n="accounts.registros_clinicos" style="display:contents">Registros clínicos</i18n-text></label>
        <label><input type="checkbox" name="role" value="dispatcher"> <i18n-text data-i18n="accounts.coordinacion_de_traslados" style="display:contents">Coordinación de traslados</i18n-text></label>
        <label><input type="checkbox" name="role" value="admin"> <i18n-text data-i18n="accounts.administracion_de_cuentas" style="display:contents">Administración de cuentas</i18n-text></label>
      </fieldset><label id="password-label" class="form-section" hidden><i18n-text data-i18n="settings.nueva_contrasena" style="display:contents">Nueva contraseña</i18n-text><input type="password" id="account-password" autocomplete="new-password" minlength="12" maxlength="72"></label></div>
      <div class="dialog-actions"><button class="secondary-button" type="button" id="account-cancel" data-i18n="settings.cancelar">Cancelar</button><button class="primary-button" type="submit" id="account-save" data-i18n="accounts.guardar">Guardar</button></div>
    </form>
  </dialog>
  <p class="route-toast" id="account-status" role="status" hidden></p>
</body>
</html>
