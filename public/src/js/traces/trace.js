/* Lists/transitions transfer views and creates transfers with retained drafts.
 * API: transfers list/catalogs/patients/create-token/create/draft through TraceApi.
 * DOM: transferGrid, transferList, newTransferForm and pageNumbers.
 * $ finds an element by ID; TraceApi translates labels when rendered.
 */
document.addEventListener('DOMContentLoaded', async () => {
  'use strict';
  function cardStatusClass(item) {
    if (item.status === 'scheduled') {
      return 'assigned-label';
    }
    if (['completed', 'cancelled'].includes(item.status)) {
      return 'completed-label';
    }
    return 'progress-label';
  }

  await window.HospitalI18n.ready;
  await window.HospitalAuth.ready;

  const api = window.TraceApi;
  const $ = id => document.getElementById(id);
  if ($('transferGrid')) {
    window.addEventListener('hospital:localechange', () => render());
    const grid = $('transferGrid');
    const list = $('transferList');
    const template = $('transferCardTemplate');
    const pagination = document.querySelector('.pagination');
    let filter = 'active';
    let page = 1;
    let total = 0;
    let items = [];
    let loading = false;
    let loaded = false;
    let revision = 0;
    let type = 'all';
    let sort = 'recent';
    let timer;
    const isList = () => filter === 'all';
    function makeCard(item) {
      const card = template.content.firstElementChild.cloneNode(true);
      card.className = 'transfer-card ' + ['blue-card', 'green-card', 'purple-card'][Number(item.id) % 3];
      card.dataset.transferId = item.id;
      card.dataset.status = item.status;
      window.HospitalI18n.bindText(card.querySelector('.card-title h2'), () =>
        window.HospitalI18n.t("trace.traslado_value", { value0: item.id })
      );
      window.HospitalI18n.bindText(card.querySelector('.card-title p'), () =>
        window.HospitalI18n.t("trace.ambulancia_value", { value0: item.ambulance.code })
      );
      const status = card.querySelector('.status-label');
      window.HospitalI18n.bindText(status, () =>
        (item.is_delayed
          ? window.HospitalI18n.t("trace_map.demorado_2")
          : api.labels[item.status]?.()
        ).toUpperCase()
      );

      status.className = 'status-label ' + cardStatusClass(item);
      const values = card.querySelectorAll('.info-value p');
      [
        item.crew.driver?.name ?? '—',
        item.crew.companion?.name ?? '—',
        api.cargo[item.cargo_type]?.()
      ].forEach((text, valueIndex) => {
        window.HospitalI18n.bindText(values[valueIndex], () => text);
      });
      const stops = card.querySelectorAll('.route-text > div');
      window.HospitalI18n.bindText(stops[0].querySelector('strong'), () => item.origin.name);
      window.HospitalI18n.bindText(stops[0].querySelector('p'), () =>
        api.dateTime(item.started_at ?? item.scheduled_at)
      );
      window.HospitalI18n.bindText(stops[1].querySelector('strong'), () => item.destination.name);
      window.HospitalI18n.bindText(stops[1].querySelector('p'), () =>
        api.dateTime(item.arrived_at ?? item.eta)
      );
      card.querySelector('.details-button').dataset.openTransfer = item.id;
      return card;
    }
    function makeListRow(item) {
      const card = makeCard(item);
      const row = document.createElement('article');
      row.className = 'transfer-list-row ' + ['blue-card', 'green-card', 'purple-card'][Number(item.id) % 3];
      const identity = document.createElement('div');
      identity.className = 'list-row-identity';
      identity.append(card.querySelector('.ambulance-icon'));
      const copy = document.createElement('div');
      copy.className = 'list-row-identity-copy';
      copy.append(card.querySelector('.card-title'));
      const badges = document.createElement('div');
      badges.className = 'list-row-badges';
      badges.append(card.querySelector('.status-label'));
      const typeBadge = document.createElement('span');
      typeBadge.className = 'list-type-badge';
      window.HospitalI18n.bindText(
        typeBadge,
        () => window.HospitalI18n.t('trace.cargo_prefix') + api.cargo[item.cargo_type]?.()
      );
      badges.append(typeBadge);
      copy.append(badges);
      identity.append(copy);
      const route = document.createElement('div');
      route.className = 'list-row-route';
      route.append(card.querySelector('.route-box'));
      const crew = document.createElement('div');
      crew.className = 'list-row-crew';
      const info = card.querySelector('.transfer-information');
      info.querySelector('.info-group:last-child').remove();
      crew.append(info);
      const action = document.createElement('div');
      action.className = 'list-row-action';
      action.append(card.querySelector('.details-button'));
      row.append(identity, route, crew, action);
      return row;
    }
    function render() {
      const all = isList();
      const scroll = list.scrollTop;
      grid.hidden = all;
      $('allTransfers').hidden = !all;
      document.querySelector('.content-card').classList.toggle('all-view-active', all);
      grid.replaceChildren(...(all ? [] : items.map(makeCard)));
      list.replaceChildren(...(all ? items.map(makeListRow) : []));
      list.scrollTop = scroll;
      $('emptyMessage').hidden = all || !loaded || items.length > 0;
      $('listEmptyMessage').hidden = !all || !loaded || items.length > 0;
      pagination.hidden = all;
      pagination.style.display = all ? 'none' : '';
      grid.setAttribute('aria-busy', String(loading));
      list.setAttribute('aria-busy', String(loading));
      window.HospitalI18n.bindText($('resultsText'), () => {
        if (loaded) {
          if (all) {
            return window.HospitalI18n.t('trace.transfer_count', { count: total });
          } else {
            return window.HospitalI18n.t("trace.mostrando_value_de_value_traslados", {
              value0: items.length,
              value1: total
            });
          }
        } else {
          if (loading) {
            return window.HospitalI18n.t("trace.cargando_traslados");
          } else {
            return '';
          }
        }
      });
      const pages = Math.ceil(total / 3);
      $('previousPage').disabled = loading || page <= 1;
      $('nextPage').disabled = loading || page >= pages;
      $('pageNumbers').replaceChildren();
      if (!all) {
        for (
          let pageNumber = Math.max(1, Math.min(page - 1, pages - 2));
          pageNumber <= Math.min(pages, Math.max(3, page + 1));
          pageNumber++
        ) {
          const controlButton = document.createElement('button');
          controlButton.type = 'button';
          controlButton.className = 'page-button' + (pageNumber === page ? ' active' : '');
          window.HospitalI18n.bindText(controlButton, () => pageNumber);
          controlButton.dataset.page = pageNumber;
          window.HospitalI18n.bindAttr(controlButton, "aria-label", () =>
            window.HospitalI18n.t("trace.ir_a_la_pagina_value", { value0: pageNumber })
          );
          controlButton.disabled = loading;
          if (pageNumber === page) {
            controlButton.setAttribute('aria-current', 'page');
          }
          $('pageNumbers').append(controlButton);
        }
      }
    }
    async function load(append = false, quiet = false) {
      if (append && (loading || items.length >= total)) {
        return;
      }
      const requestRevision = ++revision;
      let requestedValue;
      if (append) {
        requestedValue = page + 1;
      } else {
        requestedValue = page;
      }
      const requested = requestedValue;
      let success = false;
      loading = true;
      if (!append && !quiet) {
        items = [];
        loaded = false;
        list.scrollTop = 0;
      }
      render();
      try {
        const result = await api.list({
          state: filter,
          page: requested,
          page_size: isList() ? 30 : 3,
          q: isList() ? $('listTransferSearch').value.trim() : '',
          type: isList() ? type : 'all',
          sort
        });
        if (requestRevision !== revision) {
          return;
        }
        total = result.meta.total;
        page = requested;
        items = append
          ? [...new Map([...items, ...result.data].map(item => [item.id, item])).values()]
          : result.data;
        loaded = true;
        success = true;
        window.HospitalI18n.bindText($('activeCount'), () => result.meta.counts.active_count);
        window.HospitalI18n.bindText($('completedCount'), () => result.meta.counts.completed_count);
        window.HospitalI18n.bindText($('allCount'), () => result.meta.counts.all_count);
        document.querySelector('.primary-action').disabled = !result.meta.can_manage;
        if (!isList() && page > 1 && !items.length && total) {
          page = Math.ceil(total / 3);
          return load();
        }
      } catch (error) {
        api.report(error, {silent: quiet});
      } finally {
        if (requestRevision === revision) {
          loading = false;
          render();
          if (success && isList() && items.length < total && list.scrollHeight <= list.clientHeight + 100) {
            requestAnimationFrame(() => load(true));
          }
        }
      }
    }
    document.querySelectorAll('.filter-button').forEach(button =>
      button.addEventListener('click', () => {
        document.querySelectorAll('.filter-button').forEach(controlButton => {
          controlButton.classList.toggle('active', controlButton === button);
          controlButton.setAttribute('aria-selected', String(controlButton === button));
        });
        filter = button.dataset.filter;
        page = 1;
        load();
      })
    );
    $('listTransferSearch').addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        page = 1;
        load();
      }, 220);
    });
    document.querySelectorAll('[data-type]').forEach(button =>
      button.addEventListener('click', () => {
        document.querySelectorAll('[data-type]').forEach(controlButton => {
          controlButton.classList.toggle('active', controlButton === button);
          controlButton.setAttribute('aria-pressed', String(controlButton === button));
        });
        type = { all: 'all', paciente: 'patient', equipo: 'equipment', biologico: 'biological' }[
          button.dataset.type
        ];
        page = 1;
        load();
      })
    );
    document.querySelectorAll('[data-sort]').forEach(button =>
      button.addEventListener('click', () => {
        document.querySelectorAll('[data-sort]').forEach(controlButton => {
          controlButton.classList.toggle('active', controlButton === button);
          controlButton.setAttribute('aria-pressed', String(controlButton === button));
        });
        sort = button.dataset.sort === 'oldest' ? 'oldest' : 'recent';
        page = 1;
        load();
      })
    );
    $('previousPage').addEventListener('click', () => {
      if (page > 1 && !loading) {
        page--;
        load();
      }
    });
    $('nextPage').addEventListener('click', () => {
      if (page * 3 < total && !loading) {
        page++;
        load();
      }
    });
    $('pageNumbers').addEventListener('click', event => {
      const controlButton = event.target.closest('[data-page]');
      if (controlButton && !loading) {
        page = Number(controlButton.dataset.page);
        load();
      }
    });
    document.querySelector('.transfer-views').addEventListener('click', event => {
      const controlButton = event.target.closest('[data-open-transfer]');
      if (controlButton) {
        location.assign(`trace-view.php?id=${encodeURIComponent(controlButton.dataset.openTransfer)}`);
      }
    });
    list.addEventListener('scroll', () => {
      if (isList() && list.scrollTop + list.clientHeight >= list.scrollHeight - 180) {
        load(true);
      }
    });
    document
      .querySelector('.primary-action')
      .addEventListener('click', () => location.assign('trace-new.php'));
    if (await window.HospitalAuth.ready) {
      await load();
    }
    const refresh = setInterval(() => {
      if (!document.hidden && !isList() && !loading) {
        load(false, true);
      }
    }, 15000);
    window.addEventListener('pagehide', () => clearInterval(refresh));
  }
  const form = $('newTransferForm');
  if (!form) {
    return;
  }
  form.querySelectorAll('select').forEach(control => window.HospitalDropdown.enhance(control));
  const closeDropdowns = () => window.HospitalDropdown.closeAll();
  const syncDropdowns = () => window.HospitalDropdown.refresh();
  let catalog;
  let patient = null;
  let patientChoices = [];
  let patientRevision = 0;
  let patientTimer;
  let draftVersion = 0;
  let token;
  let busy = true;
  let submitted = false;
  const crewLabel = item => `${item.full_name} · #${item.id}`;
  const facilityLabel = api.facilityLabel;
  const patientLabel = item => `${item.full_name} · ${item.national_id}`;
  function applyRoute(fill = true) {
    const route = catalog?.routes.find(item => item.id === $('routeTemplate').value);
    for (const key of ['origin', 'destination']) {
      $(key).readOnly = Boolean(route);
      if (route && fill) {
        $(key).value = route[`${key}_address`] || route[`${key}_name`];
      }
    }
    if (fill) {
      $('routeVersion').value = route ? String(route.version) : '';
    }
    window.HospitalI18n.bindText($('route-itinerary'), () => {
      if (route) {
        return [
          route.origin_name,
          ...route.stops.map(stopRecord => stopRecord.name),
          route.destination_name
        ].join(' → ');
      } else {
        return '';
      }
    });
    $('route-itinerary').hidden = !route;
    if (!$('routeTemplate').value) {
      $('routeVersion').value = '';
    }
  }
  $('routeTemplate').addEventListener('change', () => applyRoute());
  const findCrew = (value, duty) =>
    catalog.crew.find(
      item =>
        crewLabel(item) === value &&
        (duty === 'driver' ? item.role_code === 'driver' : ['copilot', 'doctor'].includes(item.role_code))
    );
  function catalogValidity() {
    for (const name of ['driver', 'companion', 'patientName']) {
      const control = $(name);
      window.HospitalI18n.bindValidity(control, () => {
        let matchedValue;
        if (name === 'patientName') {
          matchedValue = patient && patientLabel(patient) === control.value;
        } else {
          matchedValue = findCrew(control.value, name);
        }
        const matched = matchedValue;
        if (!control.disabled && control.value && !matched) {
          return window.HospitalI18n.t("trace_new.choose_from_list");
        } else {
          return '';
        }
      });
    }
  }
  function fieldLabel(control) {
    return control.getAttribute('aria-label') || control.labels[0]?.textContent.trim() || control.name;
  }
  function catalogHint(control, invalid) {
    let hint = $(control.id + '-validation');
    if (!invalid || !control.validity.customError) {
      hint?.remove();
      return null;
    }
    if (!hint) {
      hint = document.createElement('small');
      hint.id = control.id + '-validation';
      hint.className = 'field-error';
      control.closest('.field').append(hint);
    }
    window.HospitalI18n.bindText(hint, () => window.HospitalI18n.t("trace_new.choose_from_list"));
    return hint;
  }
  function validateForm(focus = false) {
    catalogValidity();
    const invalid = [...form.elements].filter(control => control.willValidate && !control.validity.valid);
    for (const control of form.querySelectorAll('input,select,textarea')) {
      const button = $(control.dataset.hospitalButton);
      const hint = catalogHint(control, invalid.includes(control));
      for (const target of [control, button].filter(Boolean)) {
        if (invalid.includes(control)) {
          target.setAttribute('aria-invalid', 'true');
          target.setAttribute('aria-describedby', (hint ? hint.id + ' ' : '') + 'formMessage');
        } else {
          target.removeAttribute('aria-invalid');
          target.removeAttribute('aria-describedby');
        }
      }
    }
    $('formMessage').classList.toggle('error', invalid.length > 0);
    window.HospitalI18n.bindText($('formMessage'), () => {
      if (invalid.length) {
        return window.HospitalI18n.t("trace_new.invalid_fields", {
          value0: invalid
            .map(
              control =>
                fieldLabel(control) +
                (control.validity.customError
                  ? ': ' + window.HospitalI18n.t("trace_new.choose_from_list")
                  : '')
            )
            .join('; ')
        });
      } else {
        return '';
      }
    });
    if (focus && invalid.length) {
      closeDropdowns();
      const first = $(invalid[0].dataset.hospitalButton) || invalid[0];
      first.scrollIntoView({ block: 'center', behavior: 'auto' });
      first.focus({ preventScroll: true });
    }
    return invalid.length === 0;
  }
  for (const type of ['input', 'change']) {
    form.addEventListener(type, () => {
      if (submitted && !busy) {
        validateForm();
      }
    });
  }
  window.addEventListener('hospital:localechange', () => {
    if (submitted && !busy) {
      validateForm();
    }
  });
  function setBusy(value) {
    busy = value;
    if (value) {
      closeDropdowns();
    }
    form.querySelectorAll('input,select,textarea,button').forEach(control => {
      control.disabled = value;
    });
    $('patientSex').disabled = true;
    form.setAttribute('aria-busy', String(value));
    syncDropdowns();
  }
  function showPatient(item) {
    patient = item;
    $('patientCedula').value = item?.national_id ?? '';
    $('patientAge').value = item?.age ?? '';
    window.HospitalI18n.bindAttr($('patientAge'), "title", () => {
      if (item?.age_is_estimate) {
        return window.HospitalI18n.t("trace.edad_estimada_registrada_el_value", {
          value0: item.age_recorded_on
        });
      } else {
        return '';
      }
    });
    $('patientSex').value =
      { female: 'femenino', male: 'masculino', other: 'otro', unspecified: 'no-indica' }[item?.sex] ?? '';
    syncDropdowns();
  }
  function patientRequirement() {
    const needed = $('transferType').value === 'patient';
    $('patientName').required = needed;
    for (const name of ['patientName', 'patientCedula', 'patientAge', 'patientSex']) {
      $(name).disabled = !needed || busy || name === 'patientSex';
    }
    if (!needed) {
      $('patientName').value = '';
      showPatient(null);
      form.elements.withFamily.checked = false;
      form.elements.criticalPatient.checked = false;
    }
    syncDropdowns();
  }
  $('transferType').addEventListener('change', patientRequirement);
  $('patientName').addEventListener('input', () => {
    clearTimeout(patientTimer);
    const requestRevision = ++patientRevision;
    showPatient(patientChoices.find(item => patientLabel(item) === $('patientName').value) ?? null);
    if (patient || $('patientName').value.trim().length < 2) {
      $('patient-options').replaceChildren();
      return;
    }
    patientTimer = setTimeout(async () => {
      try {
        const found = await api.patients($('patientName').value.trim());
        if (requestRevision !== patientRevision) {
          return;
        }
        patientChoices = found;
        $('patient-options').replaceChildren(
          ...found.map(item => window.HospitalI18n.option(() => patientLabel(item), patientLabel(item)))
        );
        showPatient(found.find(item => patientLabel(item) === $('patientName').value) ?? null);
        if (submitted && !busy) {
          validateForm();
        }
      } catch (error) {
        api.report(error);
      }
    }, 220);
  });
  $('patientName').addEventListener('change', () =>
    showPatient(patientChoices.find(item => patientLabel(item) === $('patientName').value) ?? patient)
  );
  async function saveDraft(clear = false) {
    if (busy) {
      return false;
    }
    const payload = {};
    for (const control of form.querySelectorAll('input,select,textarea')) {
      if (control.name) {
        payload[control.name] = control.type === 'checkbox' ? control.checked : control.value;
      }
    }
    payload.patientId = patient?.id ?? '';
    payload.createToken = token;
    setBusy(true);
    try {
      const result = await api.saveDraft(
        clear ? { version: draftVersion, clear: true } : { version: draftVersion, payload }
      );
      draftVersion = result.version;
      $('formMessage').classList.remove('error');
      window.HospitalI18n.bindText($('formMessage'), () => {
        if (clear) {
          return '';
        } else {
          return window.HospitalI18n.t("trace.borrador_guardado");
        }
      });
      return true;
    } catch (error) {
      api.report(error);
      window.HospitalI18n.bindText($('formMessage'), () => '');
      return false;
    } finally {
      setBusy(false);
      patientRequirement();
    }
  }
  $('saveDraft').addEventListener('click', () => saveDraft());
  $('cancelTransfer').addEventListener('click', async () => {
    if (await saveDraft(true)) {
      location.assign('trace.php');
    }
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy) {
      return;
    }
    submitted = true;
    if (!validateForm(true)) {
      return;
    }
    const driver = findCrew($('driver').value, 'driver');
    let companionValue;
    if ($('companion').value) {
      companionValue = findCrew($('companion').value, 'companion');
    } else {
      companionValue = null;
    }
    const companion = companionValue;
    const body = {
      route_template_id: $('routeTemplate').value || null,
      ambulance_id: $('ambulance').value,
      driver_id: driver.id,
      companion_id: companion?.id ?? null,
      patient_id: patient?.id ?? null,
      ...api.locationFields('origin', $('origin').value, catalog.facilities),
      ...api.locationFields('destination', $('destination').value, catalog.facilities),
      scheduled_at: `${$('startDate').value}T${$('startTime').value}:00-03:00`,
      cargo_type: $('transferType').value,
      priority: $('priority').value,
      reason: $('reason').value,
      notes: $('notes').value,
      create_token: token
    };
    for (const [field, name] of Object.entries({
      special_equipment: 'specialEquipment',
      with_family: 'withFamily',
      interdepartmental: 'interdepartmental',
      critical_patient: 'criticalPatient'
    })) {
      body[field] = form.elements[name].checked;
    }
    if ($('routeTemplate').value && $('routeVersion').value) {
      body.route_version = Number($('routeVersion').value);
    }
    if (draftVersion) {
      body.draft_version = draftVersion;
    }
    setBusy(true);
    window.HospitalI18n.bindText($('formMessage'), () => window.HospitalI18n.t("trace.guardando_traslado"));
    try {
      const item = await api.create(body);
      location.assign(`trace-view.php?id=${encodeURIComponent(item.id)}`);
    } catch (error) {
      api.report(error);
      window.HospitalI18n.bindText($('formMessage'), () => '');
      setBusy(false);
      patientRequirement();
    }
  });
  setBusy(true);
  try {
    if (!(await window.HospitalAuth.ready)) {
      return;
    }
    try {
      localStorage.removeItem('hospitalNewTransferDraft');
    } catch (_) {}
    const result = await Promise.all([api.catalogs(), api.draft(), api.token()]);
    catalog = result[0];
    draftVersion = result[1].version;
    token = result[2].create_token;
    $('ambulance').replaceChildren(
      window.HospitalI18n.option(() => window.HospitalI18n.t("trace_new.buscar_ambulancia"), ''),
      ...catalog.ambulances.map(item =>
        window.HospitalI18n.option(
          () =>
            window.HospitalI18n.t("trace.ambulancia_value_value", { value0: item.code, value1: item.plate }),
          item.id
        )
      )
    );
    $('crew-options').replaceChildren(
      ...[...new Map(catalog.crew.map(item => [item.id, item])).values()].map(item =>
        window.HospitalI18n.option(() => crewLabel(item), crewLabel(item))
      )
    );
    $('facility-options').replaceChildren(
      ...catalog.facilities.map(item =>
        window.HospitalI18n.option(() => facilityLabel(item), facilityLabel(item))
      )
    );
    $('routeTemplate').replaceChildren(
      window.HospitalI18n.option(
        () => window.HospitalI18n.t("trace_view.escribir_recorrido_manualmente"),
        ''
      ),
      ...catalog.routes.map(item => window.HospitalI18n.option(() => `${item.code} · ${item.name}`, item.id))
    );
    const now = api.localParts(Date.now());
    $('startDate').min = now.date;
    $('startDate').value = now.date;
    $('startTime').value = now.time;
    const savedRoute = result[1].payload?.routeTemplate;
    if (savedRoute && !catalog.routes.some(routeRecord => routeRecord.id === savedRoute)) {
      $('routeTemplate').add(
        window.HospitalI18n.option(
          () => window.HospitalI18n.t("trace.ruta_del_borrador_no_disponible"),
          savedRoute
        )
      );
    }
    for (const [name, value] of Object.entries(result[1].payload || {})) {
      const control = form.elements.namedItem(name);
      if (!control) {
        continue;
      }
      if (control.type === 'checkbox') {
        control.checked = value === true;
      } else {
        control.value = value;
      }
    }
    applyRoute(false);
    if (/^[a-f0-9]{64}$/.test(result[1].payload?.createToken ?? '')) {
      token = result[1].payload.createToken;
    }
    if (result[1].payload?.patientId && $('patientCedula').value) {
      patientChoices = await api.patients($('patientCedula').value);
      showPatient(patientChoices.find(item => item.id === result[1].payload.patientId) ?? null);
    }
    setBusy(false);
    patientRequirement();
  } catch (error) {
    api.report(error);
  }
});
