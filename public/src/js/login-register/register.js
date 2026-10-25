/* Sends staff account applications and previews password length and visibility.
 * API: auth.php?action=register; authentication state comes from HospitalAuth.
 * DOM: registerForm, contraseña, eyeIcon, strengthBar, strengthText, registerMessage.
 */
document.addEventListener('DOMContentLoaded', async () => {
  'use strict';
  await window.HospitalI18n.ready;
  await window.HospitalAuth.ready;

  const form = document.getElementById('registerForm');
  const password = document.getElementById('contraseña');
  const eye = document.getElementById('eyeIcon');
  const bar = document.getElementById('strengthBar');
  const strength = document.getElementById('strengthText');
  const message = document.getElementById('registerMessage');
  document.getElementById('togglePasswordBtn')?.addEventListener('click', () => {
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
  password?.addEventListener('input', () => {
    const value = password.value;
    let score;
    if (value.length >= 20) {
      score = 3;
    } else if (value.length >= 16) {
      score = 2;
    } else if (value.length >= 12) {
      score = 1;
    } else {
      score = 0;
    }
    const levels = [
      [window.HospitalI18n.t("registration.muy_corta"), '#ff4d4d'],
      [window.HospitalI18n.t("registration.aceptable"), '#ffd633'],
      [window.HospitalI18n.t("registration.buena"), '#9acd32'],
      [window.HospitalI18n.t("registration.fuerte"), '#2ecc71']
    ];
    if (value) {
      bar.style.width = `${(score + 1) * 25}%`;
    } else {
      bar.style.width = '0%';
    }
    bar.style.backgroundColor = `var(--password-strength-${score}, ${levels[score][1]})`;
    window.HospitalI18n.bindText(strength, () => {
      if (value) {
        return window.HospitalI18n.t("registration.longitud_value_minimo_12_caracteres", {
          value0: levels[score][0]
        });
      }
      return '';
    });
  });
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
      window.HospitalI18n.t("registration.registrando_solicitud")
    );
    try {
      const data = await window.HospitalApi.post(
        'auth.php?action=register',
        Object.fromEntries(new FormData(form))
      );
      message.style.color = 'var(--password-strength-3, #2ecc71)';
      window.HospitalI18n.bindText(message, () => data.message);
      form.reset();
      bar.style.width = '0%';
      window.HospitalI18n.bindText(strength, () => '');
    } catch (error) {
      window.HospitalApi.report(error, 'register');
      window.HospitalI18n.bindText(message, () => '');
    } finally {
      button.disabled = false;
    }
  });
});
