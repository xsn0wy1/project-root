/* Shares transfer requests, date/location fields and lazily translated labels.
 * API: transfers list/get/catalogs/patients/create-token/create/update/status/draft;
 * tracking snapshot, including cursor-based fleet loading.
 * DOM: none; the existing TraceApi object is used by the transfer pages.
 */
(() => {
  'use strict';
  const api = window.HospitalApi;
  const path = name => `transfers.php?action=${name}`;
  const labels = {
    scheduled: function () {
      return window.HospitalI18n.t("trace_map.programado_2");
    },
    in_progress: function () {
      return window.HospitalI18n.t("trace_map.en_curso_2");
    },
    arrived: function () {
      return window.HospitalI18n.t("trace_view.en_destino");
    },
    completed: function () {
      return window.HospitalI18n.t("trace_view.finalizado");
    },
    cancelled: function () {
      return window.HospitalI18n.t("trace_api.cancelado");
    }
  };
  const cargo = {
    patient: function () {
      return window.HospitalI18n.t("trace_view.paciente");
    },
    biological: function () {
      return window.HospitalI18n.t("trace_new.biologico");
    },
    equipment: function () {
      return window.HospitalI18n.t("trace.equipo");
    }
  };
  const priority = {
    low: function () {
      return window.HospitalI18n.t("trace_view.baja");
    },
    normal: function () {
      return window.HospitalI18n.t("trace_view.normal");
    },
    high: function () {
      return window.HospitalI18n.t("trace_view.alta");
    },
    critical: function () {
      return window.HospitalI18n.t("trace_view.critica");
    }
  };
  const reason = {
    consulta: function () {
      return window.HospitalI18n.t("trace_new.consulta_medica");
    },
    estudio: function () {
      return window.HospitalI18n.t("trace_new.estudio_o_tratamiento");
    },
    alta: function () {
      return window.HospitalI18n.t("trace_new.alta_medica");
    },
    emergencia: function () {
      return window.HospitalI18n.t("trace_new.emergencia");
    },
    insumos: function () {
      return window.HospitalI18n.t("trace_new.traslado_de_insumos");
    }
  };
  const dateTime = value => {
    if (value) {
      return new Intl.DateTimeFormat(window.HospitalI18n.locale, {
        timeZone: 'America/Montevideo',
        dateStyle: 'short',
        timeStyle: 'short',
        hour12: false
      }).format(new Date(value));
    } else {
      return '—';
    }
  };
  const time = value => {
    if (value) {
      return new Intl.DateTimeFormat(window.HospitalI18n.locale, {
        timeZone: 'America/Montevideo',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      }).format(new Date(value));
    } else {
      return '—';
    }
  };
  function localParts(value) {
    const parts = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'America/Montevideo',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23'
    }).formatToParts(new Date(value));
    const dateParts = Object.fromEntries(parts.map(part => [part.type, part.value]));
    return {
      date: `${dateParts.year}-${dateParts.month}-${dateParts.day}`,
      time: `${dateParts.hour}:${dateParts.minute}`
    };
  }
  const facilityLabel = item => [item.name, item.address, item.locality].filter(Boolean).join(' · ');
  function findFacility(text, facilities) {
    const exact = facilities.find(facilityRecord => facilityLabel(facilityRecord) === text);
    if (exact) {
      return exact;
    }
    const names = facilities.filter(facilityRecord => facilityRecord.name === text);
    if (names.length === 1) {
      return names[0];
    } else {
      return null;
    }
  }
  function locationFields(prefix, text, facilities, previous = null) {
    text = text.trim();
    const keep = previous && (text === facilityLabel(previous) || text === previous.name);
    let itemValue;
    if (keep) {
      itemValue = previous;
    } else {
      itemValue = findFacility(text, facilities);
    }
    const item = itemValue;
    return {
      [`${prefix}_facility_id`]: item?.id ?? null,
      [`${prefix}_name`]: item?.name ?? text,
      [`${prefix}_address`]: item?.address ?? ''
    };
  }
  function fields(item) {
    const dateParts = localParts(item.scheduled_at);
    return {
      route_template_id: item.route_template_id,
      ambulance_id: item.ambulance.id,
      driver_id: item.crew.driver.id,
      companion_id: item.crew.companion?.id ?? null,
      patient_id: item.patient?.id ?? null,
      origin_facility_id: item.origin.id,
      destination_facility_id: item.destination.id,
      origin_name: item.origin.name,
      origin_address: item.origin.address,
      destination_name: item.destination.name,
      destination_address: item.destination.address,
      scheduled_at: `${dateParts.date}T${dateParts.time}:00-03:00`,
      cargo_type: item.cargo_type,
      priority: item.priority,
      reason: item.reason,
      notes: item.notes,
      special_equipment: item.special_equipment,
      with_family: item.with_family,
      interdepartmental: item.interdepartmental,
      critical_patient: item.critical_patient
    };
  }
  window.TraceApi = Object.freeze({
    labels,
    cargo,
    priority,
    reason,
    dateTime,
    time,
    localParts,
    fields,
    facilityLabel,
    locationFields,
    report: (error, options = {}) => api.report(error, 'transfers', options),
    get: id => api.get(`${path('get')}&${new URLSearchParams({ id })}`),
    list: query =>
      api.request(
        path('list'),
        { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(query) },
        true,
        true
      ),
    catalogs: () => api.get(path('catalogs')),
    patients: query => api.post(path('patients'), { q: query }),
    token: () => api.get(path('create-token')),
    create: body => api.post(path('create'), body),
    update: body => api.post(path('update'), body),
    status: (id, version, status) => api.post(path('status'), { id, version, status }),
    draft: () => api.get(path('draft')),
    saveDraft: body => api.post(path('draft'), body),
    snapshot: query => api.get(`tracking.php?action=snapshot&${new URLSearchParams(query || {})}`),
    async fleet() {
      let cursor = null;
      let items = [];
      let result;
      do {
        result = await this.snapshot(cursor ? { after_id: cursor } : {});
        items.push(...result.transfers);
        cursor = result.next_cursor;
      } while (cursor);
      return { ...result, transfers: items };
    }
  });
})();
