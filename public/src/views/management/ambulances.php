<?php
require_once dirname(__DIR__, 4) . '/backend/php/core/page.php';
require_staff_page('management/ambulances.php');
require_once dirname(__DIR__, 4) . '/backend/php/components/shell.php';
staff_page_open([
    'module' => 'ambulances',
    'title_key' => 'ambulances.ambulancias_hospital_de_clinicas',
    'title' => 'Ambulancias | Hospital de Clínicas',
    'stylesheet' => 'management/ambulances.css?v=20261004-form-design',
    'background' => true,
    'described_nav' => true,
    'font_stylesheet' => 'interface',
    'script' => 'management/ambulances.js?v=20261020-pilot',
]);
?>
<?php staff_header('quick'); ?>

    <section class="hero-panel ambulances-hero" aria-label="Gestión de ambulancias" data-i18n-aria-label="ambulances.gestion_de_ambulancias">
      <div class="summary-row">
        <a class="summary-back" href="management.php" aria-label="Volver a Gestión" data-i18n-aria-label="routes.volver_a_gestion"><img src="../../../assets/Icons/arrow-left.svg" alt="" aria-hidden="true"></a>
        <section class="metrics" aria-label="Resumen de ambulancias" data-i18n-aria-label="ambulances.resumen_de_ambulancias">
          <?php metric_card('metric-total', 'ambulance-icon.png', 'ambulances.total_de_ambulancias', 'Total de ambulancias', 'ambulances.vehiculos_registrados', 'Vehículos registrados.'); ?>
          <?php metric_card('metric-operational', 'operative-icon.svg', 'ambulances.operativas', 'Operativas', 'ambulances.en_condiciones_de_servicio', 'En condiciones de servicio.'); ?>
          <?php metric_card('metric-maintenance', 'maintenance-icon.svg', 'ambulances.mantenimiento', 'Mantenimiento', 'ambulances.en_revision_o_reparacion', 'En revisión o reparación.'); ?>
          <?php metric_card('metric-unavailable', 'alert-icon.svg', 'ambulances.fuera_de_servicio', 'Fuera de servicio', 'ambulances.no_disponibles_para_uso', 'No disponibles para uso.'); ?>
        </section>
      </div>
      <div class="ambulance-workspace" id="ambulance-workspace">
        <section class="directory-panel" id="directory-panel" aria-labelledby="directory-title">
          <div class="panel-heading">
            <div><h1 id="directory-title" data-i18n="ambulances.listado_de_ambulancias">Listado de ambulancias</h1><p data-i18n="ambulances.busca_y_selecciona_una_ambulancia_para_ver_su_informacion_detallada">Busca y selecciona una ambulancia para ver su información detallada.</p></div>
            <button type="button" class="new-ambulance-button" id="new-ambulance"><span class="plus-circle"><img src="../../../assets/Icons/plus-icon.svg" alt="" aria-hidden="true"></span><i18n-text data-i18n="ambulances.nueva_ambulancia" style="display:contents">Nueva ambulancia</i18n-text></button>
          </div>
          <div class="directory-controls">
            <label class="ambulance-search"><img src="../../../assets/Icons/search-icon.svg" alt="" aria-hidden="true"><input id="ambulance-search" type="search" autocomplete="off" placeholder="Buscar por matrícula o modelo..." data-i18n-placeholder="ambulances.buscar_por_matricula_o_modelo" aria-label="Buscar por matrícula o modelo" data-i18n-aria-label="ambulances.buscar_por_matricula_o_modelo_2"></label>
            <div class="status-filters" role="group" aria-label="Filtrar ambulancias por estado" data-i18n-aria-label="ambulances.filtrar_ambulancias_por_estado">
              <button type="button" class="filter-button is-active" data-filter="ALL" aria-pressed="true" data-i18n="routes.todas">Todas</button>
              <button type="button" class="filter-button" data-filter="OPERATIVA" aria-pressed="false" data-i18n="ambulances.operativas">Operativas</button>
              <button type="button" class="filter-button" data-filter="MANTENIMIENTO" aria-pressed="false" data-i18n="ambulances.mantenimiento">Mantenimiento</button>
              <button type="button" class="filter-button" data-filter="FUERA_SERVICIO" aria-pressed="false" data-i18n="ambulances.fuera_de_servicio">Fuera de servicio</button>
            </div>
          </div>
          <div class="ambulance-list" id="ambulance-list" aria-live="polite"></div>
          <div class="empty-state" id="ambulance-empty" hidden><p id="empty-description" data-i18n="ambulances.no_se_encontraron_ambulancias_con_estos_criterios">No se encontraron ambulancias con estos criterios.</p><button type="button" class="cancel-button" id="clear-filters" data-i18n="routes.limpiar_filtros">Limpiar filtros</button></div>
          <footer class="directory-footer"><span id="results-summary" role="status"></span><nav class="pagination" id="ambulance-pagination" aria-label="Páginas de ambulancias" data-i18n-aria-label="ambulances.paginas_de_ambulancias"></nav></footer>
        </section>
        <aside class="detail-panel" id="detail-panel" aria-labelledby="detail-title" aria-hidden="true" inert>
          <div class="detail-heading"><div><h2 id="detail-title" data-i18n="ambulances.detalle_de_la_ambulancia">Detalle de la ambulancia</h2><p data-i18n="ambulances.informacion_del_vehiculo_seleccionado">Información del vehículo seleccionado.</p></div><button type="button" id="close-detail" class="icon-button" aria-label="Cerrar detalle" data-i18n-aria-label="ambulances.cerrar_detalle"><img src="../../../assets/Icons/cross-icon.svg" alt="" aria-hidden="true"></button></div>
          <div id="detail-content"></div>
          <div class="detail-actions"><button type="button" class="cancel-button" id="delete-from-detail"><img src="../../../assets/Icons/trash-icon.svg" alt="" aria-hidden="true"><i18n-text data-i18n="routes.eliminar" style="display:contents">Eliminar</i18n-text></button><button type="button" class="save-button" id="edit-from-detail"><img src="../../../assets/Icons/pencil-icon.svg" alt="" aria-hidden="true"><i18n-text data-i18n="ambulances.editar_ambulancia" style="display:contents">Editar ambulancia</i18n-text></button></div>
        </aside>
      </div>
    </section>
  </main>
  <dialog class="ambulance-dialog" id="ambulance-dialog" aria-labelledby="ambulance-form-title">
    <form id="ambulance-form" novalidate>
      <div class="dialog-heading"><div><h2 id="ambulance-form-title" data-i18n="ambulances.nueva_ambulancia">Nueva ambulancia</h2><p id="form-subtitle" data-i18n="ambulances.completa_los_datos_del_vehiculo">Completa los datos del vehículo.</p></div><button type="button" class="icon-button" id="close-ambulance-dialog" aria-label="Cerrar formulario" data-i18n-aria-label="routes.cerrar_formulario"><img src="../../../assets/Icons/cross-icon.svg" alt="" aria-hidden="true"></button></div>
      <div class="dialog-fields">
        <label for="ambulance-plate"><i18n-text data-i18n="ambulances.matricula" style="display:contents">Matrícula</i18n-text> <span class="field-control"><img src="../../../assets/Icons/id-badge.svg" alt="" aria-hidden="true"><input id="ambulance-plate" name="matricula" type="text" maxlength="20" pattern="[A-Za-z0-9]+([ \-][A-Za-z0-9]+)*" placeholder="Ej. SAB 001" data-i18n-placeholder="ambulances.ej_sab_001" autocomplete="off" required></span></label>
        <label for="ambulance-model"><i18n-text data-i18n="ambulances.modelo" style="display:contents">Modelo</i18n-text> <span class="field-control"><img src="../../../assets/Icons/ambulance-icon.png" alt="" aria-hidden="true"><input id="ambulance-model" name="modelo" type="text" maxlength="100" placeholder="Ej. Mercedes-Benz Sprinter" data-i18n-placeholder="ambulances.ej_mercedes_benz_sprinter" required></span></label>
        <label for="ambulance-capacity"><i18n-text data-i18n="ambulances.capacidad_plazas" style="display:contents">Capacidad (plazas)</i18n-text> <span class="field-control"><img src="../../../assets/Icons/seats-icon.svg" alt="" aria-hidden="true"><input id="ambulance-capacity" name="capacidad" type="number" min="1" max="99" step="1" placeholder="Ej. 4" data-i18n-placeholder="ambulances.ej_4" required></span></label>
        <label for="ambulance-status"><i18n-text data-i18n="ambulances.estado_operativo" style="display:contents">Estado operativo</i18n-text> <span class="field-control"><img src="../../../assets/Icons/operative-icon.svg" alt="" aria-hidden="true"><select id="ambulance-status" name="estado_operativo" required><option value="OPERATIVA" data-i18n="ambulances.operativa">Operativa</option><option value="MANTENIMIENTO" data-i18n="ambulances.mantenimiento">Mantenimiento</option><option value="FUERA_SERVICIO" data-i18n="ambulances.fuera_de_servicio">Fuera de servicio</option></select></span></label>
      </div>
      <p class="form-error" id="form-error" role="alert" hidden></p>
      <div class="dialog-actions"><button type="button" class="cancel-button" id="cancel-ambulance" data-i18n="settings.cancelar">Cancelar</button><button type="submit" class="save-button"><img src="../../../assets/Icons/save-floppy.svg" alt="" aria-hidden="true"><span id="save-label" data-i18n="ambulances.guardar_ambulancia">Guardar ambulancia</span></button></div>
    </form>
  </dialog>
  <dialog class="ambulance-dialog delete-dialog" id="delete-dialog" aria-labelledby="delete-title" aria-describedby="delete-description">
    <div class="delete-dialog-content">
      <div class="dialog-heading"><div><h2 id="delete-title" data-i18n="ambulances.eliminar_esta_ambulancia">¿Eliminar esta ambulancia?</h2><p id="delete-description"></p></div><button type="button" class="icon-button" data-close-delete aria-label="Cerrar confirmación" data-i18n-aria-label="ambulances.cerrar_confirmacion"><img src="../../../assets/Icons/cross-icon.svg" alt="" aria-hidden="true"></button></div>
      <div class="dialog-actions"><button type="button" class="cancel-button" data-close-delete autofocus data-i18n="settings.cancelar">Cancelar</button><button type="button" class="delete-button" id="confirm-delete" data-i18n="ambulances.eliminar_ambulancia">Eliminar ambulancia</button></div>
    </div>
  </dialog>
  <div class="ambulance-toast" id="ambulance-toast" role="status" aria-live="polite" hidden></div>
  <noscript><p class="empty-state" data-i18n="ambulances.activa_javascript_para_utilizar_la_gestion_de_ambulancias">Activa JavaScript para utilizar la gestión de ambulancias.</p></noscript>
</body>
</html>
