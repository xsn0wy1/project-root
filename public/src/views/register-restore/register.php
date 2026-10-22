<?php
require_once dirname(__DIR__, 4) . '/backend/php/components/auth.php';
auth_page_open('registration.registro', 'Registro', '../../', '../../../assets/', false);
?>
    <div class="tarjeta">
        <h1 data-i18n="registration.registro">Registro</h1>
       <form
    id="registerForm"
    method="POST"
    action="../../../api/v1/auth.php?action=register">

    <input
        type="text"
        name="username"
        autocomplete="username"
        maxlength="50"
        placeholder="Usuario" data-i18n-placeholder="login.usuario"
        required>

    <div class="password-wrapper">
        <input
            type="password"
            id="contraseña"
            name="password"
            autocomplete="new-password"
            placeholder="Contraseña" data-i18n-placeholder="login.contrasena"
            minlength="12"
            maxlength="72"
            required>

        <button
            type="button"
            id="togglePasswordBtn"
            class="btn-toggle-eye">

            <img
                src="../../../assets/Icons/eye.svg"
                id="eyeIcon"
                alt="Mostrar contraseña" data-i18n-alt="registration.mostrar_contrasena">
        </button>
    </div>

    <div class="strength-meter">
        <div id="strengthBar" class="strength-bar"></div>
    </div>

    <span
        id="strengthText"
        class="strength-text">
    </span>

    <input
        type="email"
        name="email"
        placeholder="Email" data-i18n-placeholder="settings.email"
        required>

    <input
        type="text"
        name="national_id"
        maxlength="20"
        placeholder="CI" data-i18n-placeholder="registration.ci"
        required>

    <button type="submit" data-i18n="registration.registrarse">
        Registrarse
    </button>

    <p id="registerMessage" role="status" aria-live="polite"></p>

</form>
        <p>
            <i18n-text data-i18n="registration.ya_tienes_cuenta">¿Ya tienes cuenta?</i18n-text>
            <a href="../../../index.php" data-i18n="registration.iniciar_sesion">Iniciar Sesion</a>
        </p>
    </div>
    
  <script src="../../js/login-register/register.js?v=20261019-light"></script>
</body>

</html>
