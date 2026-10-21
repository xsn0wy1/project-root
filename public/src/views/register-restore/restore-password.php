<?php
require_once dirname(__DIR__, 4) . '/backend/php/components/auth.php';
auth_page_open('registration.recuperar_contrasena', 'Recuperar contraseña', '../../', '../../../assets/', true);
?>
    <div class="tarjeta">
        <h1 data-i18n="login.recuperar_contrasena">Recuperar Contraseña</h1>
        <p id="recovery-status" class="texto" role="status" hidden></p>
        <form id="recovery-request-form" novalidate>
            <div class="campo">
                <span aria-hidden="true">✉️</span>
                <input id="recovery-email" name="email" type="email" autocomplete="email" required maxlength="254" placeholder="Email" aria-label="Email" data-i18n-placeholder="settings.email" data-i18n-aria-label="settings.email">
            </div>
            <button type="submit" data-i18n="registration.enviar">
                Enviar
            </button>
        </form>
        <form id="recovery-password-form" hidden novalidate>
            <p class="texto" data-i18n="recovery.password_help">Usá al menos 12 caracteres y un máximo de 72 bytes.</p>
            <div class="campo">
                <span aria-hidden="true">🔒</span>
                <input id="recovery-password" name="new_password" type="password" autocomplete="new-password" required placeholder="Nueva contraseña" aria-label="Nueva contraseña" data-i18n-placeholder="recovery.new_password" data-i18n-aria-label="recovery.new_password">
            </div>
            <div class="campo">
                <span aria-hidden="true">🔒</span>
                <input id="recovery-confirmation" type="password" autocomplete="new-password" required placeholder="Confirmar contraseña" aria-label="Confirmar contraseña" data-i18n-placeholder="recovery.confirm_password" data-i18n-aria-label="recovery.confirm_password">
            </div>
            <button type="submit" data-i18n="recovery.save_password">Guardar contraseña</button>
            <a href="restore-password.php" class="volver" data-i18n="recovery.request_another">Solicitar otro enlace</a>
        </form>
        <a href="../../../index.php" class="volver" data-i18n="registration.volver_a_inicio_de_sesion">
            Volver a Inicio de Sesión
        </a>
    </div>
</body>

</html>
