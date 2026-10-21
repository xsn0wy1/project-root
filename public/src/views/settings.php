<?php
require_once dirname(__DIR__, 3) . '/backend/php/core/page.php';
require_staff_page('settings.php');
require_once dirname(__DIR__, 3) . '/backend/php/components/shell.php';
staff_page_open([
    'module' => 'settings',
    'title_key' => 'main_menu.configuracion',
    'title' => 'Configuración',
    'theme_styles' => 'main-menu documents settings',
    'stylesheets' => ['documents/documents.css?v=20261012-documents', 'settings.css?v=ui-repairs'],
    'src_prefix' => '../',
    'assets_prefix' => '../../assets/',
    'body_class' => '',
    'background' => true,
    'described_nav' => true,
    'nav_section' => 'settings',
    'fonts' => 'https://fonts.googleapis.com/css2?family=Inclusive+Sans:ital,wght@0,300..700;1,300..700&family=Krub:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;1,200;1,300;1,400;1,500;1,600;1,700&display=swap',
    'search' => false,
    'script' => 'settings.js?v=ui-repairs',
]);
?>

        <section class="content-card">
            <section class="bg-panel">
                <header class="settings-header">
                    <div class="settings-heading">
                        <h1 data-i18n="main_menu.configuracion">Configuración</h1>

                        <div class="settings-tabs" role="tablist" aria-label="Secciones de configuración" data-i18n-aria-label="settings.secciones_de_configuracion">
                            <button type="button" class="settings-tab active" id="general-tab" role="tab"
                                aria-selected="true" aria-controls="general-panel" data-tab="general-panel" data-i18n="settings.informacion_general">
                                Información General
                            </button>
                            <button type="button" class="settings-tab" id="privacy-tab" role="tab" aria-selected="false"
                                aria-controls="privacy-panel" data-tab="privacy-panel" data-i18n="settings.privacidad">
                                Privacidad
                            </button>
                        </div>
                    </div>

                    <div class="profile-wrapper settings-profile-wrapper">
                    <button type="button" class="user-control" aria-label="Abrir opciones de usuario" data-i18n-aria-label="settings.abrir_opciones_de_usuario">
                        <span class="user-avatar" aria-hidden="true">
                            <img src="../../assets/Icons/person-icon.svg" alt="Usuario" data-i18n-alt="login.usuario">
                        </span>
                        <span class="user-name" data-i18n="login.usuario">Usuario</span>
                        <span class="user-arrows" aria-hidden="true">
                            <span>▲</span>
                            <span>▼</span>
                        </span>
                    </button>
                    <div class="profile-menu" id="settings-profile-menu">
                        <button type="button" class="profile-menu-item" data-security-notifications><img src="../../assets/Icons/guard-icon.svg" alt=""><span data-i18n="settings.notificaciones_de_seguridad_2">Notificaciones de seguridad</span></button>
                        <div class="profile-menu-divider"></div>
                        <button type="button" class="profile-menu-item" data-logout><img src="../../assets/Icons/logout-icon.svg" alt=""><span data-i18n="main_menu.cerrar_sesion">Cerrar sesión</span></button>
                    </div>
                    </div>
                </header>

                <div class="settings-panels">
                    <section class="settings-panel general-panel" id="general-panel" role="tabpanel"
                        aria-labelledby="general-tab">

                        <div class="general-details">
                            <div class="settings-field">
                                <label for="settings-name" data-i18n="settings.nombre">Nombre</label>
                                <input id="settings-name" type="text" maxlength="150" autocomplete="name">
                            </div>

                            <div class="settings-field">
                                <label for="settings-email" data-i18n="settings.email">Email</label>
                                <input id="settings-email" type="email" maxlength="254" autocomplete="email" required>
                            </div>

                            <div class="settings-field">
                                <label id="settings-center-field-label" for="settings-center-button" data-i18n="settings.centro">Centro</label>
                                <div class="document-filter settings-center-dropdown">
                                    <select id="settings-center" hidden><option value=""></option></select>
                                    <button type="button" id="settings-center-button" class="filter-trigger" aria-expanded="false" aria-controls="settings-center-menu" aria-labelledby="settings-center-field-label settings-center-label" disabled>
                                        <span id="settings-center-label" data-i18n="settings.selecciona_un_centro">Seleccioná un centro</span>
                                        <span class="filter-chevron" aria-hidden="true"></span>
                                    </button>
                                    <div id="settings-center-menu" class="filter-menu" role="group" aria-labelledby="settings-center-field-label" hidden></div>
                                </div>
                            </div>

                            <div class="specialties-group">
                                <p data-i18n="settings.especializaciones">Especializaciones</p>
                                <div class="specialty-list">
                                    <button type="button" class="specialty-add"
                                        aria-label="Agregar especialización" data-i18n-aria-label="settings.agregar_especializacion">+</button>
                                </div>
                            </div>
                        </div>

                        <div class="general-divider" aria-hidden="true"></div>

                        <div class="profile-settings">
                            <p class="section-label" data-i18n="settings.foto_de_perfil">Foto de Perfil</p>

                            <button type="button" class="upload-box" aria-label="Subir foto de perfil" data-i18n-aria-label="settings.subir_foto_de_perfil">
                                <span class="upload-icon" aria-hidden="true">
                                    <img src="../../assets/Icons/camera-icon.svg" alt="Subir Imagen" data-i18n-alt="settings.subir_imagen">
                                </span>
                                <span data-i18n="settings.subir_imagen_2">SUBIR IMAGEN</span>
                            </button>
                            <input id="settings-photo" type="file" accept="image/png,image/jpeg,image/webp" hidden>
                            <button type="button" class="specialty-chip" data-photo-delete hidden data-i18n="settings.quitar_foto">Quitar foto</button>

                            <div class="visibility-setting">
                                <p data-i18n="settings.visibilidad_de_foto">Visibilidad de Foto</p>
                                <div class="toggle-line">
                                    <button type="button" class="toggle is-on" role="switch" aria-checked="true"
                                        aria-label="Visibilidad de la foto para funcionarios" data-i18n-aria-label="settings.visibilidad_de_la_foto_para_funcionarios" data-preference="photo_visible_to_staff">
                                        <span class="toggle-knob"></span>
                                    </button>
                                    <span data-i18n="settings.visible_para_otros_funcionarios">Visible para otros funcionarios</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="settings-panel privacy-panel" id="privacy-panel" role="tabpanel"
                        aria-labelledby="privacy-tab" hidden>

                        <div class="privacy-options">
                            <div class="privacy-setting">
                                <p data-i18n="settings.analisis_de_rendimiento">Análisis de Rendimiento</p>
                                <button type="button" class="toggle is-on" role="switch" aria-checked="true"
                                    aria-label="Análisis de rendimiento" data-i18n-aria-label="settings.analisis_de_rendimiento_2" data-preference="optional_diagnostics" title="Métricas locales opcionales del tiempo de carga. No se envían a terceros." data-i18n-title="settings.metricas_locales_opcionales_del_tiempo_de_carga_no_se_envian_a_terceros">
                                    <span class="toggle-knob"></span>
                                </button>
                            </div>

                            <div class="privacy-setting">
                                <p data-i18n="settings.notificaciones_de_seguridad">Notificaciones de Seguridad</p>
                                <button type="button" class="toggle" role="switch" aria-checked="false"
                                    aria-label="Notificaciones de seguridad" data-i18n-aria-label="settings.notificaciones_de_seguridad_2" data-preference="security_notifications">
                                    <span class="toggle-knob"></span>
                                </button>
                            </div>

                            <div class="privacy-setting">
                                <p data-i18n="settings.cerrar_sesion_automaticamente">Cerrar Sesión Automáticamente</p>
                                <button type="button" class="toggle" role="switch" aria-checked="false"
                                    aria-label="Cerrar sesión automáticamente" data-i18n-aria-label="settings.cerrar_sesion_automaticamente_2" data-preference="automatic_logout" title="Activado: 5 minutos sin actividad. Desactivado: límite obligatorio del servidor (30 minutos por defecto)." data-i18n-title="settings.activado_5_minutos_sin_actividad_desactivado_limite_obligatorio_del_servidor_30_minutos_po">
                                    <span class="toggle-knob"></span>
                                </button>
                            </div>

                            <div class="select-setting">
                                <label for="settings-language" data-i18n="settings.idioma">Idioma</label>
                                <select id="settings-language">
                                    <option value="es-UY" selected data-i18n="settings.espanol">Español</option>
                                    <option value="en" data-i18n="settings.english">English</option>
                                </select>
                            </div>

                            <div class="theme-setting">
                                <p data-i18n="settings.estilo_de_interfaz">Estilo de Interfaz</p>
                                <div class="theme-switch" data-theme="dark">
                                    <span class="theme-highlight" aria-hidden="true"></span>
                                    <button type="button" class="theme-option active" data-theme-option="dark"
                                        aria-label="Tema oscuro" data-i18n-aria-label="settings.tema_oscuro">
                                        <img src="../../assets/Icons/moon-icon.svg" alt="Tema oscuro" data-i18n-alt="settings.tema_oscuro">
                                    </button>
                                    <button type="button" class="theme-option" data-theme-option="light"
                                        aria-label="Tema claro" data-i18n-aria-label="settings.tema_claro">
                                        <img src="../../assets/Icons/sun-icon.svg" alt="Tema claro" data-i18n-alt="settings.tema_claro">
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="password-setting">
                            <p data-i18n="settings.olvidaste_tu_contrasena">¿Olvidaste tu Contraseña?</p>
                            <button type="button" class="reset-password" data-i18n="settings.cambiar_contrasena">Cambiar contraseña</button>
                        </div>
                    </section>
                </div>
            </section>
        </section>
    </main>
    <dialog id="specialty-dialog" class="settings-dialog" aria-labelledby="specialty-title">
        <form id="specialty-form" novalidate>
            <div class="dialog-heading"><h2 id="specialty-title" data-i18n="settings.agregar_especializacion">Agregar especialización</h2><button type="button" class="icon-button" data-close-dialog aria-label="Cerrar" data-i18n-aria-label="settings.cerrar"><img src="../../assets/Icons/cross-icon.svg" alt=""></button></div>
            <div class="settings-dialog-body"><label for="specialty-name" data-i18n="settings.especializacion">Especialización</label>
            <input id="specialty-name" name="specialty" maxlength="80" list="specialty-options" required autocomplete="off">
            <datalist id="specialty-options"></datalist>
            </div>
            <div class="settings-dialog-actions"><button type="button" class="cancel-button" data-close-dialog data-i18n="settings.cancelar">Cancelar</button><button type="submit" class="save-button" data-i18n="settings.agregar">Agregar</button></div>
        </form>
    </dialog>
    <dialog id="specialty-remove-dialog" class="settings-dialog" aria-labelledby="specialty-remove-title" aria-describedby="specialty-remove-description">
        <form id="specialty-remove-form" novalidate>
            <div class="dialog-heading"><h2 id="specialty-remove-title" data-i18n="settings.quitar_especializacion">Quitar especialización</h2><button type="button" class="icon-button" data-close-dialog aria-label="Cerrar" data-i18n-aria-label="settings.cerrar"><img src="../../assets/Icons/cross-icon.svg" alt=""></button></div>
            <div class="settings-dialog-body"><p id="specialty-remove-description"></p></div>
            <div class="settings-dialog-actions"><button type="button" class="cancel-button" data-close-dialog autofocus data-i18n="settings.cancelar">Cancelar</button><button type="submit" class="delete-button" data-i18n="settings.quitar_especializacion">Quitar especialización</button></div>
        </form>
    </dialog>
    <dialog id="account-dialog" class="settings-dialog" aria-labelledby="account-dialog-title">
        <div class="dialog-heading"><h2 id="account-dialog-title" data-i18n="settings.notificaciones_de_seguridad_2">Notificaciones de seguridad</h2><button type="button" class="icon-button" data-close-dialog aria-label="Cerrar" data-i18n-aria-label="settings.cerrar"><img src="../../assets/Icons/cross-icon.svg" alt=""></button></div>
        <ul id="security-notifications" class="settings-dialog-body"></ul>
        <div class="settings-dialog-actions"><button type="button" class="cancel-button" data-close-dialog data-i18n="settings.cerrar">Cerrar</button></div>
    </dialog>
</body>

</html>
