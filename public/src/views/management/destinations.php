<?php
require_once dirname(__DIR__, 4) . '/backend/php/core/page.php';
require_staff_page('management/destinations.php');
require_once dirname(__DIR__, 4) . '/backend/php/components/shell.php';
staff_page_open([
    'module' => 'destinations',
    'title_key' => 'destinations.destinos_hospital_de_clinicas',
    'title' => 'Destinos | Hospital de Clínicas',
    'stylesheet' => 'management/catalogs.css?v=catalog-details',
    'background' => false,
    'described_nav' => false,
    'script' => 'management/destinations.js?v=catalog-details',
]);
?>
<div class="destinations-layout">
      <?php staff_header('plain'); ?>

      <section class="destinations-content" aria-label="Gestión de destinos" data-i18n-aria-label="destinations.gestion_de_destinos">
        <div class="destinations-toolbar">
          <div class="toolbar-left">
            <a class="destinations-back" href="management.php" aria-label="Volver a Gestión" data-i18n-aria-label="routes.volver_a_gestion" title="Volver a Gestión" data-i18n-title="routes.volver_a_gestion">
              <img src="../../../assets/Icons/arrow-left.svg" alt="">
            </a>
            <div class="status-filters" role="group" aria-label="Filtrar destinos por estado" data-i18n-aria-label="destinations.filtrar_destinos_por_estado">
              <button type="button" class="filter-button is-active" data-filter="active" aria-pressed="true" aria-controls="destinations-list">
                <span class="status-dot" aria-hidden="true"></span><span data-i18n="trace.activos">Activos</span>
              </button>
              <button type="button" class="filter-button" data-filter="inactive" aria-pressed="false" aria-controls="destinations-list">
                <span class="status-dot is-inactive" aria-hidden="true"></span><span data-i18n="destinations.inactivos">Inactivos</span>
              </button>
            </div>
          </div>
          <div class="toolbar-right">
            <label class="list-search">
              <img src="../../../assets/Icons/search-icon.svg" alt="">
              <input id="destinations-search" type="search" autocomplete="off" placeholder="Buscar por nombre, dirección o localidad..." data-i18n-placeholder="destinations.buscar_por_nombre_direccion_o_localidad" aria-label="Buscar por nombre, dirección o localidad" data-i18n-aria-label="destinations.buscar_por_nombre_direccion_o_localidad_2">
            </label>
          </div>
        </div>

        <div class="destinations-grid">
          <section class="destinations-card list-card" id="destinations-list" aria-labelledby="list-title">
            <div class="card-heading">
              <h1 id="list-title" data-i18n="destinations.destinos_activos">Destinos activos</h1>
              <p id="list-count" aria-live="polite"></p>
            </div>
            <div class="table-container">
              <table class="destinations-table">
                <thead>
                  <tr><th scope="col" data-i18n="trace_view.destino">Destino</th><th scope="col" data-i18n="destinations.direccion">Dirección</th><th scope="col" data-i18n="destinations.localidad">Localidad</th><th scope="col" data-i18n="trace_view.estado">Estado</th><th scope="col" data-i18n="destinations.acciones">Acciones</th></tr>
                </thead>
                <tbody id="destinations-rows"></tbody>
              </table>
            </div>
            <div class="empty-state" id="empty-state" hidden>
              <span class="empty-icon"><img src="../../../assets/Icons/location-icon.svg" alt=""></span>
              <h2 id="empty-title" data-i18n="destinations.no_se_encontraron_destinos">No se encontraron destinos</h2>
              <p id="empty-description" data-i18n="destinations.prueba_con_otro_nombre_direccion_o_localidad">Prueba con otro nombre, dirección o localidad.</p>
              <button type="button" id="empty-action" class="cancel-button" data-i18n="destinations.limpiar_busqueda">Limpiar búsqueda</button>
            </div>
            <div class="table-footer">
              <span id="results-summary" role="status" aria-live="polite"></span>
              <nav class="pagination" aria-label="Páginas del listado" data-i18n-aria-label="destinations.paginas_del_listado">
                <button type="button" id="page-prev" aria-label="Página anterior" data-i18n-aria-label="trace.pagina_anterior" disabled><span aria-hidden="true">‹</span></button>
                <span class="page-number" id="page-number" aria-current="page">1</span>
                <button type="button" id="page-next" aria-label="Página siguiente" data-i18n-aria-label="trace.pagina_siguiente" disabled><span aria-hidden="true">›</span></button>
              </nav>
            </div>
          </section>

          <section class="destinations-card form-card" aria-labelledby="form-title">
            <div class="card-heading">
              <h2 id="form-title" data-i18n="destinations.agregar_destino">Agregar destino</h2>
              <p id="form-subtitle" data-i18n="destinations.completa_la_informacion_del_destino">Completa la información del destino.</p>
            </div>
            <form id="destinations-form" novalidate>
              <div class="form-field">
                <label for="destination-name" data-i18n="destinations.nombre_del_destino">Nombre del destino</label>
                <div class="field-control">
                  <img src="../../../assets/Icons/location-icon.svg" alt="">
                  <input id="destination-name" name="name" type="text" placeholder="Ej. Hospital de Clínicas" data-i18n-placeholder="destinations.ej_hospital_de_clinicas" maxlength="100" required>
                </div>
              </div>
              <div class="form-pair">
                <div class="form-field">
                  <label for="destination-address" data-i18n="destinations.direccion">Dirección</label>
                  <div class="field-control">
                    <img src="../../../assets/Icons/map-icon.svg" alt="">
                    <input id="destination-address" name="address" type="text" placeholder="Ej. Av. Italia 2870" data-i18n-placeholder="destinations.ej_av_italia_2870" autocomplete="street-address" maxlength="180" required>
                  </div>
                </div>
                <div class="form-field">
                  <label for="destination-locality" data-i18n="destinations.localidad">Localidad</label>
                  <div class="field-control">
                    <img src="../../../assets/Icons/location-icon.svg" alt="">
                    <input id="destination-locality" name="locality" type="text" placeholder="Ej. Montevideo" data-i18n-placeholder="destinations.ej_montevideo" autocomplete="address-level2" maxlength="80" required>
                  </div>
                </div>
              </div>
              <div class="form-pair">
                <div class="form-field">
                  <label for="destination-type" data-i18n="destinations.tipo_de_destino">Tipo de destino</label>
                  <div class="field-control select-control">
                    <img src="../../../assets/Icons/medkit-icon.png" alt="">
                    <select id="destination-type" name="type">
                      <option value="hospital" data-i18n="destinations.hospital">Hospital</option>
                      <option value="sanatorium" data-i18n="destinations.sanatorio">Sanatorio</option>
                      <option value="health_center" data-i18n="destinations.centro_de_salud">Centro de salud</option>
                      <option value="other" data-i18n="trace_new.otro">Otro</option>
                    </select>
                  </div>
                </div>
                <div class="form-field">
                  <label for="destination-phone"><i18n-text data-i18n="destinations.telefono">Teléfono</i18n-text> <span data-i18n="destinations.opcional">(opcional)</span></label>
                  <div class="field-control">
                    <img src="../../../assets/Icons/phone-icon.svg" alt="">
                    <input id="destination-phone" name="phone" type="tel" placeholder="Ej. 2487 1515" data-i18n-placeholder="destinations.ej_2487_1515" autocomplete="tel" maxlength="25">
                  </div>
                </div>
              </div>
              <div class="form-pair">
                <div class="form-field">
                  <label for="destination-status" data-i18n="trace_view.estado">Estado</label>
                  <div class="field-control select-control status-control">
                    <span class="status-dot" id="form-status-dot" aria-hidden="true"></span>
                    <select id="destination-status" name="status">
                      <option value="active" data-i18n="destinations.activo">Activo</option>
                      <option value="inactive" data-i18n="destinations.inactivo">Inactivo</option>
                    </select>
                  </div>
                </div>
                <div class="form-field">
                  <label for="destination-notes"><i18n-text data-i18n="destinations.referencia">Referencia</i18n-text> <span data-i18n="destinations.opcional">(opcional)</span></label>
                  <div class="field-control textarea-control">
                    <textarea id="destination-notes" name="notes" rows="1" maxlength="400" placeholder="Ej. Entrada lateral" data-i18n-placeholder="destinations.ej_entrada_lateral"></textarea>
                  </div>
                </div>
              </div>
              <div class="form-actions">
                <button type="button" id="form-cancel" class="cancel-button" data-i18n="settings.cancelar">Cancelar</button>
                <button type="submit" class="save-button"><img src="../../../assets/Icons/save-floppy.svg" alt=""><span id="save-label" data-i18n="accounts.guardar">Guardar</span></button>
              </div>
            </form>
            <p class="form-feedback" id="form-feedback" role="status" aria-live="polite"></p>
          </section>
        </div>
      </section>
    </div>
  </main>

  <dialog class="destination-dialog catalog-detail-dialog" id="destination-dialog" aria-labelledby="detail-title" aria-describedby="destination-detail-description">
    <header class="dialog-heading">
      <div><h2 id="detail-title" data-i18n="destinations.detalle_del_destino">Detalle del destino</h2><p id="destination-detail-description" data-i18n="destinations.ubicacion_contacto_y_acceso">Ubicación, contacto e indicaciones de acceso.</p></div>
      <button type="button" id="detail-close" class="dialog-close catalog-detail-close" aria-label="Cerrar detalle" data-i18n-aria-label="ambulances.cerrar_detalle"><img src="../../../assets/Icons/cross-icon.svg" alt=""></button>
    </header>
    <div class="catalog-detail-body">
      <section class="catalog-detail-summary" aria-labelledby="destination-detail-name">
        <span class="catalog-detail-icon"><img src="../../../assets/Icons/location-icon.svg" alt=""></span>
        <div class="catalog-detail-identity"><h3 id="destination-detail-name"></h3><p id="destination-detail-type"></p></div>
        <span class="catalog-detail-status" id="destination-detail-status"></span>
      </section>
      <section class="catalog-detail-card" aria-labelledby="destination-detail-section">
        <h3 id="destination-detail-section" data-i18n="routes.informacion_general">Información general</h3>
        <dl class="catalog-detail-fields" id="dialog-details"></dl>
      </section>
    </div>
    <form method="dialog" class="dialog-actions">
      <button type="submit" class="cancel-button" data-i18n="settings.cerrar">Cerrar</button>
      <button type="button" id="detail-edit" class="save-button"><img src="../../../assets/Icons/pencil-icon.svg" alt=""><span data-i18n="destinations.editar_destino">Editar destino</span></button>
    </form>
  </dialog>

  <dialog class="destination-dialog delete-dialog" id="delete-dialog" aria-labelledby="delete-title" aria-describedby="delete-description">
    <div class="dialog-heading">
      <h2 id="delete-title" data-i18n="destinations.eliminar_destino">Eliminar destino</h2>
      <button type="button" id="delete-close" class="dialog-close" aria-label="Cerrar confirmación" data-i18n-aria-label="ambulances.cerrar_confirmacion" data-i18n="documents_upload.times">×</button>
    </div>
    <p id="delete-description"></p>
    <div class="dialog-actions">
      <button type="button" id="delete-cancel" class="cancel-button" autofocus data-i18n="settings.cancelar">Cancelar</button>
      <button type="button" id="delete-confirm" class="delete-button"><img src="../../../assets/Icons/trash-icon.svg" alt=""><span data-i18n="destinations.eliminar_destino">Eliminar destino</span></button>
    </div>
  </dialog>
</body>

</html>
