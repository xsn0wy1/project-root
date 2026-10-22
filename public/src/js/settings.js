document.addEventListener('DOMContentLoaded', async () => {
  'use strict';
  await window.HospitalI18n.ready;
  await window.HospitalAuth.ready;

  const api = window.HospitalApi;
  const auth = window.HospitalAuth;
  const byId = id => document.getElementById(id);
  const tabs = [...document.querySelectorAll('.settings-tab')];
  const panels = [...document.querySelectorAll('.settings-panel')];
  const dirty = new Map();
  let state = null;
  let saveQueue = Promise.resolve();
  let facilities = [];
  let specialtiesBusy = false;
  let pendingSpecialty = null;
  const centerNames = ['Hospital de Clinicas de Montevideo', 'Facultad de Medicina'];
  const centerSelect = byId('settings-center'),
    centerButton = byId('settings-center-button');
  const centerMenu = byId('settings-center-menu'),
    centerRoot = centerButton.parentElement;

  window.HospitalDropdown.enhance(centerSelect, {
    button: centerButton,
    menu: centerMenu,
    root: centerRoot,
    label: byId('settings-center-label'),
    hideEmpty: true,
    choiceAttribute: 'facilityId',
    labelText: value =>
      value
        ? centerSelect.selectedOptions[0]?.textContent
        : window.HospitalI18n.t('settings.selecciona_un_centro')
  });
  const closeCenter = () => window.HospitalDropdown.closeAll();
  function syncCenter() {
    centerSelect.disabled = !state || !facilities.length;
    window.HospitalDropdown.refresh();
  }

  function openTab(selected) {
    closeCenter();
    tabs.forEach(tab => {
      const active = tab === selected;
      tab.classList.toggle('active', active);
      tab.setAttribute('aria-selected', String(active));
      tab.tabIndex = active ? 0 : -1;
    });
    panels.forEach(panel => {
      panel.hidden = panel.id !== selected.dataset.tab;
    });
  }
  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => openTab(tab));
    tab.addEventListener('keydown', event => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      let next;
      if (event.key === 'Home') {
        next = 0;
      } else {
        if (event.key === 'End') {
          next = tabs.length - 1;
        } else {
          next = (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        }
      }
      openTab(tabs[next]);
      tabs[next].focus();
    });
  });

  function render(payload) {
    state = payload;
    const { user, settings } = payload;
    const values = {
      full_name: user.full_name,
      email: user.email,
      default_facility_id: settings.default_facility_id || '',
      locale: window.HospitalI18n.locale
    };
    const controls = {
      full_name: 'settings-name',
      email: 'settings-email',
      default_facility_id: 'settings-center',
      locale: 'settings-language'
    };
    const select = byId('settings-center');
    const previous = select.value;
    select.replaceChildren(window.HospitalI18n.option(() => '', ''));
    facilities.forEach(facility => select.add(window.HospitalI18n.option(() => facility.name, facility.id)));
    if (settings.default_facility_id && !facilities.some(item => item.id === settings.default_facility_id)) {
      const old = window.HospitalI18n.option(
        () => settings.default_facility_name,
        settings.default_facility_id
      );
      old.disabled = true;
      select.add(old);
    }
    select.value = dirty.has('default_facility_id') ? previous : values.default_facility_id;
    Object.entries(controls).forEach(([field, id]) => {
      if (!dirty.has(field)) byId(id).value = values[field];
    });
    syncCenter();
    document.querySelectorAll('[data-preference]').forEach(button => {
      if (dirty.has(button.dataset.preference)) return;
      const on = settings[button.dataset.preference];
      button.classList.toggle('is-on', on);
      button.setAttribute('aria-checked', String(on));
    });
    document.querySelectorAll('[data-theme-option]').forEach(button => {
      const active = button.dataset.themeOption === window.HospitalTheme.current;
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', String(active));
    });
    document.querySelector('.theme-switch').dataset.theme = window.HospitalTheme.current;
    document.querySelectorAll('.specialty-chip[data-specialty-id]').forEach(chip => chip.remove());
    const add = document.querySelector('.specialty-add');
    settings.specialties.forEach(specialty => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'specialty-chip';
      chip.dataset.specialtyId = specialty.id;
      window.HospitalI18n.bindText(chip, () =>
        window.HospitalI18n.t("settings.value", { value0: specialty.name })
      );
      window.HospitalI18n.bindAttr(chip, "title", () =>
        window.HospitalI18n.t("settings.quitar_especializacion")
      );
      chip.disabled = specialtiesBusy;
      chip.addEventListener('click', () => {
        pendingSpecialty = specialty;
        window.HospitalI18n.bindText(byId('specialty-remove-description'), () =>
          window.HospitalI18n.t('settings.confirmar_quitar_especializacion', {
            name: pendingSpecialty?.name || specialty.name
          })
        );
        byId('specialty-remove-dialog').showModal();
      });
      add.before(chip);
    });
    add.disabled = specialtiesBusy || settings.specialties.length >= 10;
    const upload = document.querySelector('.upload-box');
    if (settings.avatar_url) {
      const url = api.page(settings.avatar_url);
      upload.style.backgroundImage = `url("${url}")`;
      upload.dataset.hasPhoto = '';
    } else {
      upload.style.backgroundImage = '';
      delete upload.dataset.hasPhoto;
    }
    document.querySelector('[data-photo-delete]').hidden = !settings.avatar_url;
  }

  function save(endpoint, fields, formData = null) {
    const job = saveQueue.then(async () => {
      if (!state) return false;
      try {
        let payload;
        if (formData) {
          formData.set('version', String(state.settings.version));
          payload = await api.request(endpoint, { method: 'POST', body: formData });
        } else payload = await api.post(endpoint, { version: state.settings.version, ...fields });
        Object.entries(fields).forEach(([key, value]) => {
          if (dirty.get(key) === value) dirty.delete(key);
        });
        auth.settingsChanged(payload);
        return true;
      } catch (error) {
        api.report(error, 'settings');
        if (error.code === 'VERSION_CONFLICT') await auth.refresh();
        return false;
      }
    });
    saveQueue = job.catch(error => {
      api.report(error, 'settings');
      return false;
    });
    return saveQueue;
  }

  for (const [id, field] of [
    ['settings-name', 'full_name'],
    ['settings-email', 'email']
  ]) {
    const input = byId(id);
    input.addEventListener('input', () => dirty.set(field, input.value));
    input.addEventListener('change', () => {
      if (!input.checkValidity()) return;
      save('settings.php?action=profile-update', { [field]: input.value });
    });
  }
  byId('settings-center').addEventListener('change', async event => {
    const value = event.target.value || null;
    dirty.set('default_facility_id', value);
    syncCenter();
    if (
      !(await save('settings.php?action=profile-update', { default_facility_id: value })) &&
      dirty.get('default_facility_id') === value
    ) {
      dirty.delete('default_facility_id');
      render(state);
    }
  });
  byId('settings-language').addEventListener('change', async event => {
    const locale = event.target.value;
    const choice = window.HospitalI18n.preview(locale);
    dirty.set('locale', locale);
    if (!(await save('settings.php?action=preferences-update', { locale }))) {
      dirty.delete('locale');
      render(state);
    }
    window.HospitalI18n.settle(choice);
  });
  document.querySelectorAll('[data-preference]').forEach(button =>
    button.addEventListener('click', async () => {
      if (!state || button.disabled) return;
      const field = button.dataset.preference;
      const value = button.getAttribute('aria-checked') !== 'true';
      dirty.set(field, value);
      button.disabled = true;
      const success = await save('settings.php?action=preferences-update', { [field]: value });
      dirty.delete(field);
      if (success || state) render(state);
      button.disabled = false;
    })
  );
  document.querySelectorAll('[data-theme-option]').forEach(button =>
    button.addEventListener('click', async () => {
      if (!state || button.disabled || button.dataset.themeOption === window.HospitalTheme.current) return;
      const theme = button.dataset.themeOption;
      const choice = window.HospitalTheme.preview(theme);
      dirty.set('theme', theme);
      render(state);
      await save('settings.php?action=preferences-update', { theme });
      if (dirty.get('theme') === theme) dirty.delete('theme');
      window.HospitalTheme.settle(choice);
      render(state);
    })
  );

  const passwordDialog = byId('password-dialog'),
    passwordForm = byId('password-form');
  document.querySelector('.reset-password').addEventListener('click', () => {
    passwordDialog.showModal();
    byId('current-password').focus();
  });
  byId('password-recovery-link').addEventListener('click', event => {
    const url = new URL(api.page('src/views/register-restore/restore-password.php'));
    if (window.HospitalI18n.locale === 'en') url.searchParams.set('lang', 'en');
    event.currentTarget.href = url.href;
  });
  passwordDialog.addEventListener('cancel', event => {
    if (passwordForm.dataset.busy) event.preventDefault();
  });
  passwordDialog.addEventListener('close', () => passwordForm.reset());
  passwordForm.addEventListener('submit', async event => {
    event.preventDefault();
    if (passwordForm.dataset.busy) return;
    if (!passwordForm.checkValidity()) {
      api.report(new Error(window.HospitalI18n.t('errors.revisa_los_campos_indicados')), 'change-password');
      return;
    }
    if (byId('new-password').value !== byId('confirm-password').value) {
      api.report(new Error(window.HospitalI18n.t('recovery.password_mismatch')), 'change-password');
      return;
    }
    const body = {
      current_password: byId('current-password').value,
      new_password: byId('new-password').value
    };
    passwordForm.dataset.busy = 'true';
    passwordForm.querySelectorAll('input,button').forEach(control => {
      control.disabled = true;
    });
    try {
      await api.post('auth.php?action=change-password', body);
      passwordForm.reset();
      location.assign(api.page('index.php'));
    } catch (error) {
      api.report(error, 'change-password');
    } finally {
      delete passwordForm.dataset.busy;
      passwordForm.querySelectorAll('input,button').forEach(control => {
        control.disabled = false;
      });
    }
  });

  async function showSessions() {
    const list = byId('session-list');
    list.setAttribute('aria-busy', 'true');
    try {
      const sessions = await api.get('auth.php?action=sessions');
      list.replaceChildren();
      for (const session of sessions) {
        const item = document.createElement('li');
        item.className = 'session-row';
        const details = document.createElement('div'),
          label = document.createElement('strong'),
          seen = document.createElement('p');
        const browser = ['Edg', 'Firefox', 'Chrome', 'Safari'].find(name =>
          (session.user_agent || '').includes(name)
        );
        window.HospitalI18n.bindText(label, () => {
          if (session.is_current) {
            return window.HospitalI18n.t('security.this_session');
          } else {
            if (browser === 'Edg') {
              return 'Edge';
            } else {
              return browser || window.HospitalI18n.t('security.other_device');
            }
          }
        });
        window.HospitalI18n.bindText(seen, () =>
          window.HospitalI18n.t('security.last_seen', {
            date: new Intl.DateTimeFormat(window.HospitalI18n.locale, {
              dateStyle: 'short',
              timeStyle: 'short',
              timeZone: 'America/Montevideo'
            }).format(new Date(session.last_seen_at))
          })
        );
        details.append(label, seen);
        const actions = document.createElement('div');
        actions.className = 'settings-dialog-actions';
        const revoke = document.createElement('button');
        revoke.type = 'button';
        revoke.className = 'cancel-button';
        revoke.dataset.sessionId = session.id;
        window.HospitalI18n.bindText(revoke, () => window.HospitalI18n.t('main_menu.cerrar_sesion'));
        revoke.addEventListener('click', async () => {
          revoke.disabled = true;
          try {
            const result = await api.post('auth.php?action=revoke-session', { session_id: session.id });
            if (result.current_session_revoked) location.assign(api.page('index.php'));
            else await showSessions();
          } catch (error) {
            api.report(error, 'sessions');
            revoke.disabled = false;
          }
        });
        actions.append(revoke);
        item.append(details, actions);
        list.append(item);
      }
      if (!sessions.length) {
        const empty = document.createElement('li');
        window.HospitalI18n.bindText(empty, () => window.HospitalI18n.t('security.no_sessions'));
        list.append(empty);
      }
    } catch (error) {
      api.report(error, 'sessions');
    } finally {
      list.setAttribute('aria-busy', 'false');
    }
  }
  document.querySelector('[data-sessions]').addEventListener('click', () => {
    byId('sessions-dialog').showModal();
    showSessions();
  });
  const specialtyDialog = byId('specialty-dialog');
  document.querySelector('.specialty-add').addEventListener('click', () => {
    specialtyDialog.showModal();
    byId('specialty-name').focus();
  });
  async function changeSpecialties(names) {
    if (specialtiesBusy) return false;
    specialtiesBusy = true;
    render(state);
    document.querySelectorAll('#specialty-form,#specialty-remove-form').forEach(form => {
      form.dataset.busy = 'true';
      form.querySelectorAll('input,button').forEach(control => {
        control.disabled = true;
      });
    });
    const success = await save('settings.php?action=profile-update', { specialties: names });
    specialtiesBusy = false;
    render(state);
    document.querySelectorAll('#specialty-form,#specialty-remove-form').forEach(form => {
      delete form.dataset.busy;
      form.querySelectorAll('input,button').forEach(control => {
        control.disabled = false;
      });
    });
    return success;
  }
  byId('specialty-form').addEventListener('submit', async event => {
    event.preventDefault();
    if (!event.target.checkValidity() || specialtiesBusy || !state) return;
    const name = byId('specialty-name').value.trim();
    if (await changeSpecialties([...state.settings.specialties.map(item => item.name), name])) {
      byId('specialty-name').value = '';
      specialtyDialog.close();
    }
  });
  byId('specialty-remove-form').addEventListener('submit', async event => {
    event.preventDefault();
    if (!pendingSpecialty || specialtiesBusy || !state) return;
    if (
      await changeSpecialties(
        state.settings.specialties.filter(item => item.id !== pendingSpecialty.id).map(item => item.name)
      )
    )
      byId('specialty-remove-dialog').close();
  });
  for (const dialog of [specialtyDialog, byId('specialty-remove-dialog')]) {
    dialog.addEventListener('cancel', event => {
      if (specialtiesBusy) event.preventDefault();
    });
  }
  byId('specialty-remove-dialog').addEventListener('close', () => {
    const chip =
      document.querySelector(`[data-specialty-id="${pendingSpecialty?.id}"]`) ||
      document.querySelector('.specialty-add');
    pendingSpecialty = null;
    chip.focus({ preventScroll: true });
  });

  const photoInput = byId('settings-photo');
  const upload = document.querySelector('.upload-box');
  upload.addEventListener('click', () => photoInput.click());
  photoInput.addEventListener('change', async () => {
    const photo = photoInput.files?.[0];
    if (!photo || !state) return;
    if (photo.size > 5 * 1024 * 1024) {
      api.report(new Error(window.HospitalI18n.t("settings.la_foto_supera_5_mib")), 'settings');
      return;
    }
    upload.disabled = true;
    const data = new FormData();
    data.set('photo', photo);
    if (await save('settings.php?action=photo', {}, data)) photoInput.value = '';
    upload.disabled = false;
  });
  document.querySelector('[data-photo-delete]').addEventListener('click', async event => {
    event.currentTarget.disabled = true;
    await save('settings.php?action=photo-delete', {});
    document.querySelector('[data-photo-delete]').disabled = false;
  });

  document.querySelectorAll('[data-close-dialog]').forEach(button =>
    button.addEventListener('click', () => {
      if (!button.closest('form')?.dataset.busy) button.closest('dialog').close();
    })
  );
  document.querySelector('[data-security-notifications]').addEventListener('click', async () => {
    const dialog = byId('account-dialog');
    dialog.showModal();
    const list = byId('security-notifications');
    list.replaceChildren();
    try {
      const notifications = await api.get('settings.php?action=notifications');
      notifications.forEach(notification => {
        const item = document.createElement('li');
        const date = () =>
          new Intl.DateTimeFormat(window.HospitalI18n.locale, {
            dateStyle: 'short',
            timeStyle: 'short',
            timeZone: 'America/Montevideo'
          }).format(new Date(notification.created_at));
        window.HospitalI18n.bindText(
          item,
          () => `${window.HospitalI18n.t("settings.nuevo_inicio_de_sesion")} · ${date()}`
        );
        list.append(item);
      });
      if (notifications.length)
        await api.post('settings.php?action=notifications-read', { through_id: notifications[0].id });
    } catch (error) {
      api.report(error, 'notifications');
    }
  });

  const inputs = document.querySelectorAll(
    '#settings-name, #settings-email, #settings-center, #settings-language, [data-preference], .upload-box, .specialty-add, .user-control'
  );
  inputs.forEach(input => {
    input.disabled = true;
  });
  window.addEventListener('hospital:session', event => render(event.detail));
  auth.ready
    .then(async payload => {
      if (!payload) return;
      inputs.forEach(input => {
        input.disabled = false;
      });
      render(payload);
      const results = await Promise.allSettled([
        api.get('catalogs.php?action=facilities'),
        api.get('catalogs.php?action=specialties')
      ]);
      if (results[0].status === 'fulfilled') {
        // Restrict this preference to the two requested institutions; save real catalog IDs.
        const nameKey = name =>
          name
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim()
            .toLowerCase();
        facilities = centerNames.flatMap(name => {
          const facility = results[0].value.find(
            item => nameKey(item.name.split(' · ')[0]) === nameKey(name)
          );
          return facility ? [{ ...facility, name }] : [];
        });
        render(state);
      } else api.report(results[0].reason, 'catalogs');
      if (results[1].status === 'fulfilled')
        results[1].value.forEach(item =>
          byId('specialty-options').append(window.HospitalI18n.option(() => item.name, item.name))
        );
      else api.report(results[1].reason, 'catalogs');
    })
    .catch(error => api.report(error, 'settings'));
});
