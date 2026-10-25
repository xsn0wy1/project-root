/* Manages staff accounts, approvals, permissions, sessions and password resets.
 * API: accounts directory/manage/reset-password.
 * DOM: account-list, account-detail, account-form and account-dialog.
 * $ finds an element by ID; HospitalI18n.t translates labels when rendered.
 */
document.addEventListener('DOMContentLoaded', async () => {
  'use strict';
  await window.HospitalI18n.ready;
  await window.HospitalAuth.ready;

  const api = window.HospitalApi;
  const $ = id => document.getElementById(id);
  const dialog = $('account-dialog');
  const form = $('account-form');
  const stateButton = $('account-state-button');
  const stateMenu = $('account-state-menu');
  const stateOptions = [...stateMenu.querySelectorAll('[data-account-state]')];
  const stateLabels = {
    '': 'routes.todas',
    pending: 'accounts.pendientes',
    active: 'routes.activas',
    disabled: 'accounts.desactivadas'
  };
  const labels = {
    pending: function () {
      return window.HospitalI18n.t("accounts.pendiente");
    },
    active: function () {
      return window.HospitalI18n.t("routes.activa");
    },
    disabled: function () {
      return window.HospitalI18n.t("shared.desactivada");
    }
  };
  const roles = {
    staff: function () {
      return window.HospitalI18n.t("trace_view.personal");
    },
    records_manager: function () {
      return window.HospitalI18n.t("accounts.registros_clinicos");
    },
    dispatcher: function () {
      return window.HospitalI18n.t("survey.traslados");
    },
    admin: function () {
      return window.HospitalI18n.t("management.administracion");
    }
  };
  const escape = value =>
    String(value ?? '').replace(
      /[&<>"']/g,
      character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]
    );
  let users = [];
  let selected = null;
  let base = null;
  let action = null;
  let busy = false;
  let self = null;
  let timer;
  let stateFilter = '';
  function render() {
    const query = $('account-search').value.trim().toLocaleLowerCase();
    const state = stateFilter;
    window.HospitalI18n.bindText($('account-state-label'), () =>
      window.HospitalI18n.t(stateLabels[stateFilter])
    );
    window.HospitalI18n.bindAttr(
      stateButton,
      'aria-label',
      () =>
        `${window.HospitalI18n.t('accounts.estado_de_cuenta')}: ${window.HospitalI18n.t(stateLabels[stateFilter])}`
    );
    stateButton.classList.toggle('is-filtered', Boolean(stateFilter));
    stateOptions.forEach(option =>
      option.setAttribute('aria-pressed', String(option.dataset.accountState === stateFilter))
    );
    const rows = users.filter(
      accountRecord =>
        (!state || accountRecord.status === state) &&
        `${accountRecord.username} ${accountRecord.email}`.toLocaleLowerCase().includes(query)
    );
    if (selected && !rows.some(accountRecord => accountRecord.id === selected)) {
      selected = null;
    }
    window.HospitalI18n.setHTML(
      $('account-list'),
      rows
        .map(
          accountRecord =>
            `<button class="route-card${accountRecord.id === selected ? ' is-selected' : ''}" type="button" data-account-id="${escape(accountRecord.id)}"` +
            ` aria-label="${window.HospitalI18n.html("accounts.ver_cuenta_value", { value0: accountRecord.username })}"` +
            ` data-i18n-aria-label="accounts.ver_cuenta_value" data-i18n-aria-label-params="${window.HospitalI18n.escape(JSON.stringify({ value0: accountRecord.username }))}">` +
            `<span class="route-card-top">` +
            `<strong>${escape(accountRecord.username)}` +
            `</strong>` +
            `<span class="status-pill">${labels[accountRecord.status]?.()}` +
            `</span>` +
            `</span>` +
            `<span class="route-card-name">${escape(accountRecord.email)}` +
            `</span>` +
            `<span class="route-card-meta">${escape(accountRecord.roles.map(roleCode => roles[roleCode]?.()).join(' · '))}` +
            `</span>` +
            `</button>`
        )
        .join('')
    );
    window.HospitalI18n.bindText($('account-count'), () =>
      window.HospitalI18n.t("accounts.value_cuentas", { value0: rows.length })
    );
    $('account-list').hidden = !rows.length;
    $('account-empty').hidden = !!rows.length;
    window.HospitalI18n.bindText($('account-empty-title'), () =>
      window.HospitalI18n.t(query || state ? 'accounts.no_matches' : 'accounts.no_accounts')
    );
    window.HospitalI18n.bindText($('account-empty-description'), () =>
      window.HospitalI18n.t(query || state ? 'accounts.no_matches_help' : 'accounts.no_accounts_help')
    );
    const accountRecord = users.find(accountRecord => accountRecord.id === selected);
    $('account-detail').classList.toggle('is-empty', !accountRecord);
    if (!accountRecord) {
      window.HospitalI18n.setHTML(
        $('account-detail'),
        `<div class="accounts-placeholder">` +
          `<span class="account-empty-icon" aria-hidden="true">` +
          `<img src="../../../assets/Icons/person-icon.svg" alt="">` +
          `</span>` +
          `<h2>${window.HospitalI18n.mark("accounts.select_user_to_begin")}` +
          `</h2>` +
          `<p>${window.HospitalI18n.mark("accounts.selection_help")}` +
          `</p>` +
          `</div>`
      );
      return;
    }
    let actions;
    if (accountRecord.status === 'pending') {
      actions = [['approve', window.HospitalI18n.t("accounts.aprobar_cuenta")]];
    } else if (accountRecord.status === 'disabled') {
      actions = [['reactivate', window.HospitalI18n.t("accounts.reactivar_cuenta")]];
    } else {
      actions = [
        ['roles', window.HospitalI18n.t("accounts.editar_permisos")],
        ['disable', window.HospitalI18n.t("accounts.desactivar_cuenta")],
        ['revoke_sessions', window.HospitalI18n.t("accounts.cerrar_sesiones")]
      ];
    }
    if (accountRecord.status !== 'pending' && accountRecord.id !== self) {
      actions.push(['password', window.HospitalI18n.t("accounts.restablecer_contrasena")]);
    }
    window.HospitalI18n.setHTML(
      $('account-detail'),
      `<h2>${escape(accountRecord.username)}` +
        `</h2>` +
        `<p>${escape(accountRecord.email)}` +
        `</p>` +
        `<p>${labels[accountRecord.status]?.()}` +
        `</p>` +
        `<p>${escape(accountRecord.roles.map(roleCode => roles[roleCode]?.()).join(' · '))}` +
        `</p>` +
        `<div class="detail-actions">${actions.map(([key, label]) => `<button type="button" class="secondary-button" data-action="${key}">${label}</button>`).join('')}` +
        `</div>`
    );
  }
  async function load() {
    users = await api.get('accounts.php?action=directory');
    render();
  }
  function setBusy(value) {
    busy = value;
    [...form.elements].forEach(controlElement => (controlElement.disabled = value));
  }
  $('account-search').addEventListener('input', render);
  // Reuse the Documents filter interaction pattern with the account status codes.
  function closeStateMenu() {
    stateMenu.hidden = true;
    stateButton.setAttribute('aria-expanded', 'false');
  }
  function openStateMenu() {
    stateMenu.hidden = false;
    stateButton.setAttribute('aria-expanded', 'true');
  }
  stateButton.addEventListener('click', () => {
    if (stateMenu.hidden) {
      openStateMenu();
    } else {
      closeStateMenu();
    }
  });
  stateButton.addEventListener('keydown', event => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      openStateMenu();
      const current = stateOptions.find(option => option.dataset.accountState === stateFilter);
      (event.key === 'ArrowUp' ? stateOptions[stateOptions.length - 1] : current).focus();
    } else if (event.key === 'Escape') {
      event.preventDefault();
      closeStateMenu();
    }
  });
  stateMenu.addEventListener('click', event => {
    const option = event.target.closest('[data-account-state]');
    if (!option) {
      return;
    }
    stateFilter = option.dataset.accountState;
    closeStateMenu();
    render();
    $('account-list').scrollTop = 0;
    stateButton.focus();
  });
  stateMenu.addEventListener('keydown', event => {
    const index = stateOptions.indexOf(document.activeElement);
    if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
      event.preventDefault();
      let next;
      if (event.key === 'Home') {
        next = 0;
      } else {
        if (event.key === 'End') {
          next = stateOptions.length - 1;
        } else {
          next = (index + (event.key === 'ArrowDown' ? 1 : -1) + stateOptions.length) % stateOptions.length;
        }
      }
      stateOptions[next].focus();
    } else if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      closeStateMenu();
      stateButton.focus();
    }
  });
  document.addEventListener('click', event => {
    if (!stateButton.parentElement.contains(event.target)) {
      closeStateMenu();
    }
  });
  document.addEventListener('focusin', event => {
    if (!stateButton.parentElement.contains(event.target)) {
      closeStateMenu();
    }
  });
  $('account-list').addEventListener('click', event => {
    const button = event.target.closest('[data-account-id]');
    if (button && !busy) {
      selected = button.dataset.accountId;
      render();
    }
  });
  $('account-detail').addEventListener('click', event => {
    const button = event.target.closest('[data-action]');
    if (!button || busy) {
      return;
    }
    base = users.find(accountRecord => accountRecord.id === selected);
    if (!base) {
      return;
    }
    action = button.dataset.action;
    form.reset();
    window.HospitalI18n.bindText($('account-title'), () => button.textContent);
    window.HospitalI18n.bindText($('account-name'), () => base.username);
    $('account-roles').hidden = !['approve', 'roles'].includes(action);
    $('password-label').hidden = action !== 'password';
    $('account-password').required = action === 'password';
    form
      .querySelectorAll('[name=role]')
      .forEach(
        controlElement =>
          (controlElement.checked =
            base.roles.includes(controlElement.value) ||
            (action === 'approve' && controlElement.value === 'staff'))
      );
    dialog.showModal();
  });
  $('account-cancel').addEventListener('click', () => {
    if (!busy) {
      form.reset();
      dialog.close();
    }
  });
  dialog.addEventListener('cancel', event => {
    if (busy) {
      event.preventDefault();
    } else {
      form.reset();
    }
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy || !base) {
      return;
    }
    if (!form.checkValidity()) {
      api.report(
        new api.ApiError(
          window.HospitalI18n.t("accounts.usa_una_contrasena_de_al_menos_12_caracteres"),
          422,
          'VALIDATION_FAILED'
        ),
        'accounts'
      );
      return;
    }
    const chosen = [...form.querySelectorAll('[name=role]:checked')].map(
      controlElement => controlElement.value
    );
    const password = $('account-password').value;
    setBusy(true);
    try {
      if (action === 'password') {
        await api.post('accounts.php?action=reset-password', {
          user_id: base.id,
          version: base.version,
          new_password: password
        });
      } else {
        await api.post('accounts.php?action=manage', {
          user_id: base.id,
          version: base.version,
          action,
          roles: chosen
        });
      }
      form.reset();
      dialog.close();
      if (base.id === self) {
        location.assign(api.page('index.php'));
        return;
      }
      await load();
      window.HospitalI18n.bindText($('account-status'), () =>
        window.HospitalI18n.t("accounts.cambio_guardado")
      );
      $('account-status').hidden = false;
      clearTimeout(timer);
      timer = setTimeout(() => ($('account-status').hidden = true), 3500);
    } catch (error) {
      api.report(error, 'accounts');
    } finally {
      setBusy(false);
    }
  });
  try {
    const session = await window.HospitalAuth.ready;
    if (!session) {
      return;
    }
    self = session.user.id;
    await load();
  } catch (error) {
    api.report(error, 'accounts');
  }
  window.addEventListener('hospital:localechange', () => {
    render();
    if (dialog.open && action) {
      window.HospitalI18n.bindText(
        $('account-title'),
        () =>
          ({
            approve: window.HospitalI18n.t('accounts.aprobar_cuenta'),
            reactivate: window.HospitalI18n.t('accounts.reactivar_cuenta'),
            roles: window.HospitalI18n.t('accounts.editar_permisos'),
            disable: window.HospitalI18n.t('accounts.desactivar_cuenta'),
            revoke_sessions: window.HospitalI18n.t('accounts.cerrar_sesiones'),
            password: window.HospitalI18n.t('accounts.restablecer_contrasena')
          })[action]
      );
    }
  });
});
