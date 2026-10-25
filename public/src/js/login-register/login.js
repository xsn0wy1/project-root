/* Handles staff sign-in, password visibility and the optional login theme choice.
 * API: auth.php?action=login and settings.php?action=preferences-update.
 * DOM: loginForm, loginMessage, loginContraseña, loginEyeIcon, toggleLoginPasswordBtn.
 */
document.addEventListener('DOMContentLoaded', async () => {
  'use strict';
  let loginTheme = null;
  document.querySelectorAll('[data-public-theme]').forEach(button => {
    button.addEventListener('click', () => {
      if (button.dataset.publicTheme !== window.HospitalTheme.current) {
        loginTheme = button.dataset.publicTheme;
      }
    });
  });
  await window.HospitalI18n.ready;
  await window.HospitalAuth.ready;

  const form = document.getElementById('loginForm');
  const message = document.getElementById('loginMessage');
  const password = document.getElementById('loginContraseña');
  const eye = document.getElementById('loginEyeIcon');
  document.getElementById('toggleLoginPasswordBtn')?.addEventListener('click', () => {
    if (password.type === 'password') {
      password.type = 'text';
    } else {
      password.type = 'password';
    }
    let eyeIcon = 'eye-closed.svg';
    if (password.type === 'password') {
      eyeIcon = 'eye.svg';
    }
    eye.src = window.HospitalApi.page('assets/Icons/' + eyeIcon);
  });
  if (new URLSearchParams(location.search).get('reason') === 'session_expired') {
    console.info('[Hospital auth]', window.HospitalI18n.t('auth.session_ended'));
  }
  form?.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.checkValidity()) {
      return;
    }
    const button = form.querySelector('[type="submit"]');
    if (button.disabled) {
      return;
    }
    button.disabled = true;
    message.style.color = '';
    window.HospitalI18n.bindText(message, () =>
      window.HospitalI18n.t("registration.iniciando_sesion")
    );
    try {
      const session = await window.HospitalApi.post(
        'auth.php?action=login',
        Object.fromEntries(new FormData(form))
      );
      password.value = '';
      if (loginTheme) {
        try {
          await window.HospitalApi.post('settings.php?action=preferences-update', {
            version: session.settings.version,
            theme: loginTheme
          });
        } catch (error) {
          window.HospitalApi.report(error, 'login-theme');
        }
      }
      location.assign(window.HospitalApi.page('src/views/main-menu.php'));
    } catch (error) {
      window.HospitalApi.report(error, 'login');
      window.HospitalI18n.bindText(message, () => '');
      button.disabled = false;
    }
  });
});
