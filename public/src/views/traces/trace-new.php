<?php
declare(strict_types=1);
require dirname(__DIR__, 4) . '/backend/php/core/page.php';
require_staff_page('traces/trace-new.php');
require dirname(__DIR__, 4) . '/backend/php/components/shell.php';
staff_page_open([
    'module' => 'traces',
    'theme_styles' => 'main-menu documents trace-new',
    'title_key' => 'trace_new.nuevo_traslado',
    'title' => 'Nuevo Traslado',
    'stylesheets' => ['documents/documents.css?v=20261012-documents', 'traces/trace-new.css?v=20261020-dropdowns'],
    'font_stylesheet' => 'forms',
    'body_class' => '',
    'background' => true,
    'described_nav' => true,
    'nav_section' => 'transfer-form',
    'extra_scripts' => ['../../js/traces/trace-api.js?v=stage5', '../../js/traces/trace.js?v=stage5'],
]);
?>



        <section class="content-card">
            <?php staff_header('transfer-form'); ?>

            <section class="new-transfer-panel">
                <div class="new-transfer-content">
                    <a href="trace.php" class="back-link">
                        <span class="new-transfer-icon back-icon" aria-hidden="true"></span>
                        <i18n-text data-i18n="trace_new.volver_a_traslados" style="display:contents">Volver a traslados</i18n-text>
                    </a>

                    <div class="new-transfer-heading">
                        <div class="heading-icon">
                            <span class="new-transfer-icon ambulance-icon" aria-hidden="true"></span>
                        </div>
                        <div>
                            <h1 data-i18n="trace_new.nuevo_traslado">Nuevo Traslado</h1>
                            <p data-i18n="trace_new.completa_la_informacion_para_registrar_un_nuevo_traslado_de_ambulancia">Completa la información para registrar un nuevo traslado de ambulancia</p>
                        </div>
                    </div>

                    <form id="newTransferForm" novalidate>
                        <div class="form-layout">
                            <div class="form-column">
                                <section class="form-section general-section">
                                    <h2>
                                        <span class="new-transfer-icon information-icon" aria-hidden="true"></span>
                                        <i18n-text data-i18n="settings.informacion_general" style="display:contents">Información General</i18n-text>
                                    </h2>

                                    <div class="field full-field">
                                        <label for="ambulance" data-i18n="trace_view.ambulancia">Ambulancia</label>
                                        <select id="ambulance" name="ambulance" required>
                                            <option value="" data-i18n="trace_new.buscar_ambulancia">Buscar ambulancia...</option>
                                        </select>
                                    </div>

                                    <div class="field full-field">
                                        <label for="driver" data-i18n="trace_view.conductor">Conductor</label>
                                        <div class="input-wrapper">
                                            <input type="text" id="driver" name="driver" list="crew-options"
                                                placeholder="Buscar conductor..." data-i18n-placeholder="trace_new.buscar_conductor" autocomplete="off" required>
                                            <span class="new-transfer-icon search-icon field-icon" aria-hidden="true"></span>
                                        </div>
                                    </div>

                                    <div class="field full-field">
                                        <label for="companion"><i18n-text data-i18n="trace_view.acompanante" style="display:contents">Acompañante</i18n-text> <span data-i18n="trace_new.opcional">(Opcional)</span></label>
                                        <div class="input-wrapper">
                                            <input type="text" id="companion" name="companion" list="crew-options"
                                                placeholder="Buscar acompañante..." data-i18n-placeholder="trace_new.buscar_acompanante" autocomplete="off">
                                            <span class="new-transfer-icon search-icon field-icon" aria-hidden="true"></span>
                                        </div>
                                    </div>

                                    <div class="field full-field">
                                        <label for="startDate" data-i18n="trace_new.fecha_y_hora_de_inicio">Fecha y Hora de Inicio</label>
                                        <div class="date-time-fields">
                                            <input type="date" id="startDate" name="startDate" aria-label="Fecha de inicio" data-i18n-aria-label="trace_new.fecha_de_inicio"
                                                required>
                                            <input type="time" id="startTime" name="startTime" aria-label="Hora de inicio" data-i18n-aria-label="trace_new.hora_de_inicio"
                                                required>
                                        </div>
                                    </div>

                                    <div class="field full-field">
                                        <label for="reason" data-i18n="trace_new.motivo_del_traslado">Motivo del Traslado</label>
                                        <select id="reason" name="reason" required>
                                            <option value="" data-i18n="trace_new.seleccionar_motivo">Seleccionar motivo...</option>
                                            <option value="consulta" data-i18n="trace_new.consulta_medica">Consulta médica</option>
                                            <option value="estudio" data-i18n="trace_new.estudio_o_tratamiento">Estudio o tratamiento</option>
                                            <option value="alta" data-i18n="trace_new.alta_medica">Alta médica</option>
                                            <option value="emergencia" data-i18n="trace_new.emergencia">Emergencia</option>
                                            <option value="insumos" data-i18n="trace_new.traslado_de_insumos">Traslado de insumos</option>
                                        </select>
                                    </div>
                                </section>

                                <section class="form-section patient-section">
                                    <h2>
                                        <span class="new-transfer-icon patient-icon" aria-hidden="true"></span>
                                        <i18n-text data-i18n="trace_view.paciente" style="display:contents">Paciente</i18n-text>
                                    </h2>

                                    <div class="patient-fields">
                                        <div class="field">
                                            <label for="patientName" data-i18n="trace_new.nombre_del_paciente">Nombre del Paciente</label>
                                            <input type="text" id="patientName" name="patientName" list="patient-options" placeholder="Nombre..." data-i18n-placeholder="trace_new.nombre"
                                                autocomplete="off">
                                        </div>

                                        <div class="field">
                                            <label for="patientCedula" data-i18n="trace_new.cedula_del_paciente">Cédula del Paciente</label>
                                            <div class="input-wrapper">
                                                <input type="text" id="patientCedula" name="patientCedula" readonly inputmode="numeric"
                                                    placeholder="Ej.: 1.234.567-8" data-i18n-placeholder="trace_new.ej_1_234_567_8" autocomplete="off">
                                                <span class="new-transfer-icon id-icon field-icon" aria-hidden="true"></span>
                                            </div>
                                        </div>

                                        <div class="field">
                                            <label for="patientAge" data-i18n="trace_view.edad">Edad</label>
                                            <input type="number" id="patientAge" name="patientAge" readonly min="0" max="120"
                                                placeholder="Ej: 45" data-i18n-placeholder="trace_new.ej_45">
                                        </div>

                                        <div class="field">
                                            <label for="patientSex" data-i18n="trace_view.sexo">Sexo</label>
                                            <select id="patientSex" name="patientSex" disabled>
                                                <option value="" data-i18n="trace_new.seleccionar">Seleccionar...</option>
                                                <option value="femenino" data-i18n="trace_new.femenino">Femenino</option>
                                                <option value="masculino" data-i18n="trace_new.masculino">Masculino</option>
                                                <option value="otro" data-i18n="trace_new.otro">Otro</option>
                                                <option value="no-indica" data-i18n="trace_new.prefiere_no_indicar">Prefiere no indicar</option>
                                            </select>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <div class="form-column">
                                <section class="form-section route-section">
                                    <h2>
                                        <span class="new-transfer-icon location-icon" aria-hidden="true"></span>
                                        <i18n-text data-i18n="trace_new.origen_y_destino" style="display:contents">Origen y Destino</i18n-text>
                                    </h2>

                                    <div class="field full-field">
                                        <label for="routeTemplate" data-i18n="trace_view.ruta_predefinida_opcional">Ruta predefinida (opcional)</label>
                                        <div class="input-wrapper select-wrapper">
                                            <select id="routeTemplate" name="routeTemplate"><option value="" data-i18n="trace_view.escribir_recorrido_manualmente">Escribir recorrido manualmente</option></select>
                                        </div>
                                        <input type="hidden" id="routeVersion" name="routeVersion" value="">
                                        <small id="route-itinerary" hidden></small>
                                    </div>

                                    <div class="field full-field">
                                        <label for="origin" data-i18n="trace_view.origen">Origen</label>
                                        <div class="input-wrapper">
                                            <input type="text" id="origin" name="origin" list="facility-options"
                                                placeholder="Elegir centro o escribir dirección..." data-i18n-placeholder="trace_new.elegir_centro_o_escribir_direccion" autocomplete="off" required>
                                            <span class="new-transfer-icon search-icon field-icon" aria-hidden="true"></span>
                                        </div>
                                    </div>

                                    <div class="field full-field">
                                        <label for="destination" data-i18n="trace_view.destino">Destino</label>
                                        <div class="input-wrapper">
                                            <input type="text" id="destination" name="destination" list="facility-options"
                                                placeholder="Elegir centro o escribir destino..." data-i18n-placeholder="trace_new.elegir_centro_o_escribir_destino" autocomplete="off" required>
                                            <span class="new-transfer-icon search-icon field-icon" aria-hidden="true"></span>
                                        </div>
                                    </div>

                                    <div class="two-fields">
                                        <div class="field">
                                            <label for="transferType" data-i18n="trace_new.tipo_de_traslado">Tipo de Traslado</label>
                                            <select id="transferType" name="transferType" required>
                                                <option value="" data-i18n="trace_new.seleccionar_tipo">Seleccionar tipo...</option>
                                                <option value="patient" data-i18n="trace_view.paciente">Paciente</option>
                                                <option value="biological" data-i18n="trace_new.biologico">Biológico</option>
                                                <option value="equipment" data-i18n="trace_new.elemento_o_equipo">Elemento o equipo</option>
                                            </select>
                                        </div>

                                        <div class="field priority-field">
                                            <label for="priority" data-i18n="trace_view.prioridad">Prioridad</label>
                                            <select id="priority" name="priority" required>
                                                <option value="" data-i18n="trace_new.seleccionar">Seleccionar...</option>
                                                <option value="low" data-i18n="trace_view.baja">Baja</option>
                                                <option value="normal" data-i18n="trace_view.normal">Normal</option>
                                                <option value="high" data-i18n="trace_view.alta">Alta</option>
                                                <option value="critical" data-i18n="trace_view.critica">Crítica</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="field full-field">
                                        <label for="notes"><i18n-text data-i18n="trace_new.notas_adicionales" style="display:contents">Notas Adicionales</i18n-text> <span data-i18n="trace_new.opcional">(Opcional)</span></label>
                                        <textarea id="notes" name="notes" rows="4" maxlength="1000"
                                            placeholder="Información relevante..." data-i18n-placeholder="trace_new.informacion_relevante"></textarea>
                                    </div>
                                </section>

                                <section class="form-section options-section">
                                    <h2>
                                        <span class="new-transfer-icon settings-icon" aria-hidden="true"></span>
                                        <i18n-text data-i18n="trace_new.opciones_adicionales" style="display:contents">Opciones Adicionales</i18n-text>
                                    </h2>

                                    <div class="option-grid">
                                        <label class="check-option">
                                            <input type="checkbox" name="specialEquipment">
                                            <span data-i18n="trace_new.requiere_equipamiento_especial">Requiere equipamiento especial</span>
                                        </label>

                                        <label class="check-option">
                                            <input type="checkbox" name="withFamily">
                                            <span data-i18n="trace_new.acompanado_por_familiar">Acompañado por familiar</span>
                                        </label>

                                        <label class="check-option">
                                            <input type="checkbox" name="interdepartmental">
                                            <span data-i18n="trace_new.traslado_interdepartamental">Traslado interdepartamental</span>
                                        </label>

                                        <label class="check-option critical-option">
                                            <input type="checkbox" name="criticalPatient">
                                            <span data-i18n="trace_new.paciente_en_estado_critico">Paciente en estado crítico</span>
                                        </label>
                                    </div>
                                </section>

                                <p class="form-message" id="formMessage" aria-live="polite"></p>

                                <div class="form-actions">
                                    <button type="button" class="cancel-button" id="cancelTransfer" data-i18n="settings.cancelar">Cancelar</button>
                                    <button type="button" class="draft-button" id="saveDraft" data-i18n="trace_new.guardar_borrador">Guardar Borrador</button>
                                    <button type="submit" class="create-button" data-i18n="trace_new.crear_traslado">Crear Traslado</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <datalist id="crew-options"></datalist><datalist id="facility-options"></datalist><datalist id="patient-options"></datalist>
                </div>
            </section>
        </section>
    </main>
</body>

</html>
