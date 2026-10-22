<?php
require_once dirname(__DIR__, 1) . '/backend/php/components/auth.php';
auth_page_open('login.inicio_de_sesion', 'Inicio de Sesion', 'src/', 'assets/', false);
?>
  <div class="tarjeta">
    <h1 data-i18n="login.inicio_de_sesion">Inicio de Sesion</h1>

    <form id="loginForm" method="POST" action="api/v1/auth.php?action=login">
      <input type="text" name="username" autocomplete="username" maxlength="50" placeholder="Usuario" data-i18n-placeholder="login.usuario" required>

      <div class="password-wrapper">
        <input type="password" id="loginContraseña" name="password" autocomplete="current-password" placeholder="Contraseña" data-i18n-placeholder="login.contrasena" required>
        <button type="button" id="toggleLoginPasswordBtn" class="btn-toggle-eye">
          <img src="assets/Icons/eye.svg" id="loginEyeIcon" alt="Mostrar u ocultar contraseña" data-i18n-alt="login.mostrar_u_ocultar_contrasena">
        </button>
      </div>

      <p id="loginMessage" role="status" aria-live="polite"></p>

      <p>
        <i18n-text data-i18n="login.olvidaste_tu_contrasena">¿Olvidaste tu contraseña?</i18n-text>
        <a href="src/views/register-restore/restore-password.php" data-i18n="login.recuperar_contrasena">Recuperar Contraseña</a>
      </p>
      <button type="submit" data-i18n="login.inicio_sesion">Inicio sesion</button>
    </form>

    <p>
      <i18n-text data-i18n="login.no_tienes_cuenta">¿No tienes cuenta?</i18n-text>
      <a href="src/views/register-restore/register.php" data-i18n="login.registrate">Registrate</a>
    </p>
  </div>

  <script src="src/js/login-register/login.js?v=20261019-i18n"></script>
</body>

</html>
