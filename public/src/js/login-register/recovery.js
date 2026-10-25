/* Requests recovery email or replaces a password using the URL fragment token.
 * API: auth.php?action=reset-request and auth.php?action=reset-password.
 * DOM: recovery-request-form, recovery-password-form, recovery-status and password inputs.
 */
document.addEventListener('DOMContentLoaded', async () => {
  'use strict';
  await window.HospitalI18n.ready;
  const api = window.HospitalApi;
  const i18n = window.HospitalI18n;
  const requestForm = document.getElementById('recovery-request-form');
  const passwordForm = document.getElementById('recovery-password-form');
  const status = document.getElementById('recovery-status');
  let token;
  function showMode() {
    const fragment = new URLSearchParams(location.hash.slice(1));
    const resetting = fragment.has('token');
    token = fragment.get('token');
    requestForm.hidden = resetting;
    passwordForm.hidden = !resetting;
    status.hidden = true;
    if (resetting && !/^[a-f0-9]{64}$/.test(token || '')) {
      api.report(new api.ApiError(i18n.t('recovery.invalid_token'), 422, 'RESET_TOKEN_INVALID'), 'recovery');
    }
  }
  window.addEventListener('hashchange', showMode);
  showMode();

  requestForm.addEventListener('submit', async event => {
    event.preventDefault();
    const button = requestForm.querySelector('[type="submit"]');
    if (button.disabled) {
      return;
    }
    status.hidden = true;
    if (!requestForm.checkValidity()) {
      return;
    }
    button.disabled = true;
    try {
      await api.post('auth.php?action=reset-request', { email: requestForm.elements.email.value });
      i18n.bindText(status, () => i18n.t('recovery.requested'));
      status.hidden = false;
    } catch (error) {
      api.report(error, 'recovery');
    } finally {
      button.disabled = false;
    }
  });

  passwordForm.addEventListener('submit', async event => {
    event.preventDefault();
    const button = passwordForm.querySelector('[type="submit"]');
    if (button.disabled || !passwordForm.checkValidity()) {
      return;
    }
    const password = document.getElementById('recovery-password').value;
    if (
      Array.from(password).length < 12 ||
      new TextEncoder().encode(password).length > 72 ||
      password.includes('\0')
    ) {
      api.report(new api.ApiError(i18n.t('recovery.password_help'), 422, 'VALIDATION_FAILED'), 'recovery');
      return;
    }
    if (password !== document.getElementById('recovery-confirmation').value) {
      api.report(
        new api.ApiError(i18n.t('recovery.password_mismatch'), 422, 'VALIDATION_FAILED'),
        'recovery'
      );
      return;
    }
    button.disabled = true;
    try {
      await api.post('auth.php?action=reset-password', { token, new_password: password });
      token = null;
      passwordForm.reset();
      const url = new URL(location.href);
      url.hash = '';
      history.replaceState(history.state, '', url);
      api.clearToken();
      let loginUrl = api.page('index.php');
      if (i18n.locale === 'en') {
        loginUrl += '?lang=en';
      }
      location.replace(loginUrl);
    } catch (error) {
      api.report(error, 'recovery');
      button.disabled = false;
    }
  });
});
