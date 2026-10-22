<?php
declare(strict_types=1);
require __DIR__ . '/shell.php';
$titleKey = $editing ? 'documents_edit.editar_documento' : 'documents_upload.cargar_documento';
$title = $editing ? 'Editar Documento' : 'Cargar Documento';
staff_page_open([
    'module' => 'documents',
    'body_class' => '',
    'background' => true,
    'described_nav' => true,
    'title_key' => $titleKey,
    'title' => $title,
    'theme_styles' => 'main-menu documents documents-upload',
    'stylesheets' => ['documents/documents.css', 'documents/documents-upload.css'],
    'font_stylesheet' => 'forms',
    'nav_section' => 'document-form',
    'main_class' => 'app-shell document-workspace',
    'extra_scripts' => ['../../js/documents/document-api.js', '../../js/documents/document-form.js'],
]);
?>
    <section class="content-card document-content-card">
      <?php staff_header('document-form'); ?>

      <section class="document-form-panel" aria-labelledby="page-title">
        <div class="document-form-content">
          <a href="documents.php" class="back-link">
            <span aria-hidden="true">←</span>
            <i18n-text data-i18n="documents_upload.volver_a_documentos">Volver a documentos</i18n-text>
          </a>

          <div class="document-form-heading">
            <div class="heading-icon">
              <img src="../../../assets/Icons/<?= $editing ? 'folder-icon.svg' : 'cloud-upload.svg' ?>" alt="" aria-hidden="true">
            </div>
            <div>
              <h1 id="page-title" data-i18n="<?= $titleKey ?>"><?= $title ?></h1>
              <p data-i18n="<?= $editing ? 'documents_edit.actualiza_los_datos_o_reemplaza_el_archivo_adjunto' : 'documents_upload.registra_los_datos_y_adjunta_el_archivo_correspondiente' ?>"><?= $editing ? 'Actualizá los datos o reemplazá el archivo adjunto.' : 'Registrá los datos y adjuntá el archivo correspondiente.' ?></p>
            </div>
          </div>

          <form
            id="documentForm"
            class="document-form"
            data-mode="<?= $editing ? 'update' : 'create' ?>"
            method="post"
            action="../../../../api/v1/documents.php?action=<?= $editing ? 'update' : 'create' ?>"
            enctype="multipart/form-data">

            <div class="form-layout">
              <section class="form-section details-section" aria-labelledby="document-data-title">
                <h2 id="document-data-title">
                  <span class="section-marker" aria-hidden="true"></span>
                  <i18n-text data-i18n="documents_upload.datos_del_documento">Datos del documento</i18n-text>
                </h2>

                <div class="document-field">
                  <label for="nombre" data-i18n="documents_upload.nombre_del_documento">Nombre del documento</label>
                  <input
                    type="text"
                    id="nombre"
                    name="nombre"
                    maxlength="150"
                    <?= $editing ? 'value=""' : 'placeholder="Ej. Informe médico" data-i18n-placeholder="documents_upload.ej_informe_medico"' ?>
                    autocomplete="off"
                    required>
                </div>

                <div class="document-field">
                  <label for="paciente" data-i18n="documents_upload.paciente_del_hospital">Paciente del hospital</label>
                  <input
                    type="text"
                    id="paciente"
                    name="paciente"<?= $editing ? ' value=""' : '' ?>
                    list="patient-options"
                    maxlength="180"
                    placeholder="Buscá por nombre o cédula" data-i18n-placeholder="documents_upload.busca_por_nombre_o_cedula"
                    autocomplete="off"
                    required>
                </div>

                <datalist id="patient-options"></datalist>

                <div class="field-row">
                  <div class="document-field">
                    <label for="fecha" data-i18n="documents_upload.fecha_del_documento">Fecha del documento</label>
                    <input type="date" id="fecha" name="fecha" required>
                  </div>

                  <div class="document-field">
                    <label id="category-field-label" for="category-button" data-i18n="survey.categoria">Categoría</label>
                    <div class="document-filter document-category">
                      <select id="categoria" name="categoria" required hidden>
                        <option value="" data-i18n="documents_edit.selecciona_una_categoria">Seleccioná una categoría</option>
                        <option value="informe" data-i18n="catalog.document_categories.medical_report">Informe Médico</option>
                        <option value="estudio" data-i18n="catalog.document_categories.laboratory_study">Estudio de Laboratorio</option>
                        <option value="administrativo" data-i18n="catalog.document_categories.administrative">Documento Administrativo</option>
                      </select>
                      <button type="button" id="category-button" class="filter-trigger" aria-expanded="false" aria-controls="category-menu" aria-labelledby="category-field-label category-label" disabled>
                        <span id="category-label" data-i18n="documents_edit.selecciona_una_categoria">Seleccioná una categoría</span>
                        <span class="filter-chevron" aria-hidden="true"></span>
                      </button>
                      <div id="category-menu" class="filter-menu" role="group" aria-labelledby="category-field-label" hidden></div>
                    </div>
                  </div>
                </div>

                <div class="document-field description-field">
                  <label for="descripcion" data-i18n="documents_upload.descripcion">Descripción</label>
                  <textarea
                    id="descripcion"
                    name="descripcion"
                    maxlength="1000"
                    placeholder="Agregá una descripción del documento..." data-i18n-placeholder="documents_upload.agrega_una_descripcion_del_documento"></textarea>
                </div>
              </section>

              <section class="form-section file-section" aria-labelledby="file-data-title">
                <h2 id="file-data-title">
                  <span class="section-marker" aria-hidden="true"></span>
                  <i18n-text data-i18n="documents_upload.archivo_adjunto">Archivo adjunto</i18n-text>
                </h2>

<?php if ($editing): ?>
                <div class="current-file" id="currentFile">
                  <img src="../../../assets/Icons/folder-icon.svg" alt="" aria-hidden="true">
                  <div>
                    <span data-i18n="documents_edit.archivo_actual">Archivo actual</span>
                    <strong data-i18n="documents_edit.cargando_archivo">Cargando archivo...</strong>
                  </div>
                </div>
<?php endif; ?>
                <label class="upload-area<?= $editing ? ' compact-upload' : '' ?>" id="uploadArea" for="archivo">
                  <input
                    type="file"
                    id="archivo"
                    name="archivo"
                    accept=".pdf,.docx,.jpg,.jpeg,.png"
                    aria-describedby="fileHelp"<?= $editing ? '' : ' required' ?>>
                  <img src="../../../assets/Icons/cloud-upload.svg" alt="" aria-hidden="true">
                  <strong data-i18n="<?= $editing ? 'documents_edit.reemplazar_archivo' : 'documents_upload.selecciona_o_arrastra_un_archivo' ?>"><?= $editing ? 'Reemplazar archivo' : 'Seleccioná o arrastrá un archivo' ?></strong>
                  <span id="fileHelp" data-i18n="<?= $editing ? 'documents_edit.opcional_pdf_docx_jpg_o_png_maximo_10_mib' : 'documents_upload.pdf_docx_jpg_o_png_maximo_10_mib' ?>"><?= $editing ? 'Opcional · PDF, DOCX, JPG o PNG · máximo 10 MiB' : 'PDF, DOCX, JPG o PNG · máximo 10 MiB' ?></span>
                </label>

                <div class="file-summary" id="fileSummary" hidden>
                  <img src="../../../assets/Icons/folder-icon.svg" alt="" aria-hidden="true">
                  <div>
                    <strong id="selectedFileName"></strong>
                    <span id="selectedFileMeta"></span>
                  </div>
                  <button type="button" id="clearFile" aria-label="Quitar archivo seleccionado" data-i18n-aria-label="documents_upload.quitar_archivo_seleccionado" data-i18n="documents_upload.times">&times;</button>
                </div>

                <p class="form-message" id="formMessage" role="status" aria-live="polite"></p>

                <div class="form-actions">
                  <a href="documents.php" class="cancel-button" data-i18n="settings.cancelar">Cancelar</a>
                  <button type="submit" class="save-button" data-i18n="<?= $editing ? 'documents_edit.guardar_cambios' : 'documents_upload.subir_documento' ?>"><?= $editing ? 'Guardar Cambios' : 'Subir Documento' ?></button>
                </div>
              </section>
            </div>
          </form>
        </div>
      </section>
    </section>
  </main>
</body>

</html>
