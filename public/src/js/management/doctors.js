/* Manages the doctor directory, schedules, record editors and archive dialogs.
 * API: doctors list/get/catalogs/create/update/archive/restore.
 * DOM: doctor-rows, doctor-form, doctor-detail and doctor-dialog.
 * HospitalI18n.t translates labels when rendered.
 */
(async () => {
  "use strict";
  const i18nSourceUrl = document.currentScript.src;
  await window.HospitalI18n.ready;
  await window.HospitalAuth.ready;

  const api = window.HospitalApi;
  const statusLabels = {
    available: function () {
      return window.HospitalI18n.t("doctors.disponible");
    },
    consulting: function () {
      return window.HospitalI18n.t("doctors.en_consulta");
    },
    on_call: function () {
      return window.HospitalI18n.t("doctors.de_guardia");
    },
    inactive: function () {
      return window.HospitalI18n.t("destinations.inactivo");
    }
  };
  const shiftLabels = {
    morning: function () {
      return window.HospitalI18n.t("doctors.manana");
    },
    afternoon: function () {
      return window.HospitalI18n.t("doctors.tarde");
    },
    night: function () {
      return window.HospitalI18n.t("doctors.noche");
    },
    rotating: function () {
      return window.HospitalI18n.t("doctors.rotativo");
    }
  };
  const dayLabels = {
    weekdays: function () {
      return window.HospitalI18n.t("doctors.lunes_a_viernes");
    },
    mon_wed_fri: function () {
      return window.HospitalI18n.t("doctors.lunes_miercoles_y_viernes");
    },
    tue_thu: function () {
      return window.HospitalI18n.t("doctors.martes_y_jueves");
    },
    weekends: function () {
      return window.HospitalI18n.t("doctors.fines_de_semana");
    },
    rotating: function () {
      return window.HospitalI18n.t("doctors.rotativo");
    }
  };
  const fromApi = doctorRecord => ({
    ...doctorRecord,
    name: doctorRecord.full_name,
    document: doctorRecord.national_id,
    service: doctorRecord.department,
    archived: doctorRecord.status === "archived",
    status:
      doctorRecord.status === "archived" || doctorRecord.staff_status !== "active"
        ? "inactive"
        : doctorRecord.availability,
    shift: doctorRecord.shift,
    days: doctorRecord.days,
    startTime: doctorRecord.start_time || "",
    endTime: doctorRecord.end_time || ""
  });
  const toApi = doctorRecord => ({
    title: doctorRecord.title,
    full_name: doctorRecord.name,
    national_id: doctorRecord.document,
    license: doctorRecord.license,
    specialty: doctorRecord.specialty,
    department: doctorRecord.service,
    office: doctorRecord.office,
    phone: doctorRecord.phone,
    email: doctorRecord.email,
    availability: doctorRecord.status,
    shift: doctorRecord.shift,
    days: doctorRecord.days,
    start_time: doctorRecord.startTime || null,
    end_time: doctorRecord.endTime || null,
    notes: doctorRecord.notes
  });
  const pageSize = 5;
  const statusStyles = {
    available: "available",
    consulting: "consulting",
    on_call: "on-call",
    inactive: "inactive"
  };
  const fields = [
    "title",
    "name",
    "document",
    "license",
    "specialty",
    "service",
    "office",
    "phone",
    "email",
    "status",
    "shift",
    "days",
    "startTime",
    "endTime",
    "notes"
  ];
  const rows = document.getElementById("doctor-rows");
  const directory = document.querySelector(".doctor-directory");
  const search = document.getElementById("doctor-search");
  const specialtyFilter = document.getElementById("specialty-filter");
  const filters = [...document.querySelectorAll("[data-status]")];
  const pagination = document.getElementById("doctor-pagination");
  const detail = document.getElementById("doctor-detail");
  const scrim = document.getElementById("detail-scrim");
  const form = document.getElementById("doctor-form");
  const dialog = document.getElementById("doctor-dialog");
  const deleteDialog = document.getElementById("delete-dialog");
  const formError = document.getElementById("form-error");
  const newButton = document.getElementById("new-doctor-button");
  if (!api || !rows || !form || !dialog || !detail) {
    return;
  }

  const escapeHtml = window.HospitalUI.escapeHtml;
  const normalize = window.HospitalUI.normalize;
  const identifier = value => normalize(value).replace(/[^a-z0-9]/g, "");
  const fullName = doctor => `${doctor.title} ${doctor.name}`;
  const icon = file => `<img src="../../../assets/Icons/${file}" alt="" aria-hidden="true">`;
  const pill = doctor =>
    `<span class="status-pill status-pill--${statusStyles[doctor.status]}">${escapeHtml(doctor.archived ? window.HospitalI18n.t("personnel.archivado") : statusLabels[doctor.status]?.())}` +
    `</span>`;
  const hours = doctor => {
    if (doctor.startTime && doctor.endTime) {
      return `${doctor.startTime} – ${doctor.endTime}`;
    } else {
      return window.HospitalI18n.t("doctors.horario_no_indicado");
    }
  };
  let doctors = [];
  let catalogs = { specialties: [], departments: [] };
  let busy = false;
  let editingVersion = null;
  let deletingVersion = null;
  let activeStatus = "all";
  let currentPage = 1;
  let selectedId = null;
  let editingId = null;
  let deletingId = null;
  let lastAction = null;
  let formReturnFocus = null;
  let deleteReturnFocus = null;
  let toastTimer;

  const report = error => api.report(error, "doctors");
  const upsert = record => {
    const doctorRecord = fromApi(record);
    doctors = [doctorRecord, ...doctors.filter(item => item.id !== doctorRecord.id)];
    return doctorRecord;
  };
  function setBusy(value) {
    busy = value;
    [...form.elements, ...deleteDialog.querySelectorAll("button")].forEach(controlElement => {
      controlElement.disabled = value;
    });
    newButton.disabled = value;
  }
  async function refresh() {
    const [data, suggestions] = await Promise.all([
      api.get("doctors.php?action=list"),
      api.get("doctors.php?action=catalogs")
    ]);
    doctors = data.map(fromApi);
    catalogs = suggestions;
    updateCatalogs();
    renderList();
  }
  async function openCurrent(id, edit = false) {
    try {
      const doctorRecord = upsert(await api.get(`doctors.php?action=get&id=${encodeURIComponent(id)}`));
      if (edit) {
        if (!doctorRecord.archived) {
          openForm(doctorRecord);
        }
      } else {
        showDetail(doctorRecord);
      }
    } catch (error) {
      report(error);
    }
  }

  function toast(message) {
    const notification = document.getElementById("doctor-toast");
    clearTimeout(toastTimer);
    window.HospitalI18n.bindText(notification, () => window.HospitalI18n.resolve(message));
    notification.hidden = false;
    toastTimer = setTimeout(() => {
      notification.hidden = true;
    }, 5000);
  }

  function updateCatalogs() {
    const current = specialtyFilter.value;
    // Group spelling and accent variants as the same specialty.
    const unique = key => {
      const activeDoctors = doctors.filter(doctorRecord => !doctorRecord.archived);
      const normalizedNames = activeDoctors.map(doctor => [normalize(doctor[key]), doctor[key]]);
      const namesBySpelling = new Map(normalizedNames);
      const names = [...namesBySpelling.values()];
      return names.sort((leftValue, rightValue) =>
        leftValue.localeCompare(rightValue, window.HospitalI18n.locale)
      );
    };
    const normalizedSpecialties = doctors.map(doctorRecord => [
      normalize(doctorRecord.specialty),
      doctorRecord.specialty
    ]);
    const specialtiesBySpelling = new Map(normalizedSpecialties);
    const specialties = [...specialtiesBySpelling.values()].sort((leftValue, rightValue) =>
      leftValue.localeCompare(rightValue, window.HospitalI18n.locale)
    );
    window.HospitalI18n.setHTML(
      specialtyFilter,
      `<option value="" data-i18n="doctors.todas_las_especialidades">${window.HospitalI18n.html("doctors.todas_las_especialidades")}</option>` +
        specialties
          .map(value => `<option value="${escapeHtml(normalize(value))}">${escapeHtml(value)}</option>`)
          .join("")
    );
    specialtyFilter.value = specialties.some(value => normalize(value) === current) ? current : "";
    window.HospitalI18n.setHTML(
      document.getElementById("specialty-options"),
      [...new Set([...catalogs.specialties, ...specialties])]
        .map(value => `<option value="${escapeHtml(value)}"></option>`)
        .join("")
    );
    window.HospitalI18n.setHTML(
      document.getElementById("service-options"),
      [...new Set([...catalogs.departments, ...unique("service")])]
        .map(value => `<option value="${escapeHtml(value)}"></option>`)
        .join("")
    );
    window.HospitalI18n.bindText(
      document.getElementById("metric-total"),
      () => doctors.filter(doctorRecord => !doctorRecord.archived).length
    );
    window.HospitalI18n.bindText(
      document.getElementById("metric-available"),
      () => doctors.filter(doctor => doctor.status === "available").length
    );
    window.HospitalI18n.bindText(
      document.getElementById("metric-on-call"),
      () => doctors.filter(doctor => doctor.status === "on_call").length
    );
    window.HospitalI18n.bindText(
      document.getElementById("metric-specialties"),
      () => unique("specialty").length
    );
  }

  function matchingDoctors() {
    const query = normalize(search.value);
    const compactQuery = identifier(query);
    return doctors.filter(doctor => {
      let matchesStatusValue;
      if (activeStatus === "all") {
        matchesStatusValue = !doctor.archived;
      } else {
        matchesStatusValue = doctor.status === activeStatus;
      }
      const matchesStatus = matchesStatusValue;
      const matchesSpecialty =
        !specialtyFilter.value || normalize(doctor.specialty) === specialtyFilter.value;
      const values = [
        fullName(doctor),
        doctor.document,
        doctor.license,
        doctor.phone,
        doctor.specialty,
        doctor.service,
        doctor.email
      ];
      const matchesQuery =
        !query ||
        values.some(
          value =>
            normalize(value).includes(query) || (compactQuery && identifier(value).includes(compactQuery))
        );
      return matchesStatus && matchesSpecialty && matchesQuery;
    });
  }

  function actionButton(doctor, action, label, file) {
    return (
      `<button type="button" class="row-action" data-action="${action}" data-id="${escapeHtml(doctor.id)}" aria-label="${label} ${escapeHtml(fullName(doctor))}"` +
      ` title="${label} ${escapeHtml(fullName(doctor))}">${icon(file)}` +
      `</button>`
    );
  }

  function row(doctor) {
    return (
      `<tr${selectedId === doctor.id ? ' class="is-selected"' : ""}>
      ` +
      `<td>` +
      `<div class="doctor-identity">` +
      `<span class="doctor-avatar">${icon("doctor-icon.svg")}</span>` +
      `<div>` +
      `<strong title="${escapeHtml(fullName(doctor))}">${escapeHtml(fullName(doctor))}</strong>` +
      `<small>${escapeHtml(doctor.license)}</small>` +
      `</div>` +
      `</div>` +
      `</td>
      ` +
      `<td>` +
      `<div class="doctor-info">${icon("specialty-icon.svg")}<div>` +
      `<b title="${escapeHtml(doctor.specialty)}">${escapeHtml(doctor.specialty)}</b>` +
      `<small title="${escapeHtml(doctor.service)}">${escapeHtml(doctor.service)}</small>` +
      `</div>` +
      `</div>` +
      `</td>
      ` +
      `<td>` +
      `<div class="doctor-info">${icon("time-icon.svg")}<div>` +
      `<b>${escapeHtml(shiftLabels[doctor.shift]?.())} ` +
      `<span class="shift-hours">· ${escapeHtml(hours(doctor))}</span>` +
      `</b>` +
      `<small>${escapeHtml(doctor.phone)}</small>` +
      `</div>` +
      `</div>` +
      `</td>
      ` +
      `<td>${pill(doctor)}</td>
      ` +
      `<td>` +
      `<div class="row-actions">${actionButton(doctor, "view", window.HospitalI18n.t("doctors.ver_detalle_de"), "arrow-left.svg")}</div>` +
      `</td>
    ` +
      `</tr>`
    );
  }

  function renderList() {
    const matches = matchingDoctors();
    const pageCount = Math.max(1, Math.ceil(matches.length / pageSize));
    currentPage = Math.max(1, Math.min(currentPage, pageCount));
    const start = (currentPage - 1) * pageSize;
    const visible = matches.slice(start, start + pageSize);
    window.HospitalI18n.setHTML(rows, visible.map(row).join(""));
    document.querySelector(".doctor-table-container").classList.toggle("is-empty", matches.length === 0);
    document.getElementById("doctor-empty").hidden = matches.length > 0;
    window.HospitalI18n.bindText(document.getElementById("empty-description"), () => {
      if (doctors.length) {
        return window.HospitalI18n.t("doctors.prueba_con_otro_nombre_o_ajusta_los_filtros");
      } else {
        return window.HospitalI18n.t("doctors.agrega_el_primer_medico_para_comenzar_el_directorio");
      }
    });
    document.getElementById("clear-filters").hidden = doctors.length === 0;
    window.HospitalI18n.bindText(document.getElementById("results-summary"), () => {
      if (matches.length) {
        return window.HospitalI18n.t("ambulances.mostrando_value_a_value_de_value_value", {
          value0: start + 1,
          value1: start + visible.length,
          value2: matches.length,
          value3:
            matches.length === 1
              ? window.HospitalI18n.t("doctors.medico")
              : window.HospitalI18n.t("doctors.medicos")
        });
      } else {
        return window.HospitalI18n.t("doctors.mostrando_0_medicos");
      }
    });
    const pages = new Set([1, pageCount, currentPage - 1, currentPage, currentPage + 1]);
    let previous = 0;
    const validPages = [...pages].filter(page => page >= 1 && page <= pageCount);
    const sortedPages = validPages.sort((leftValue, rightValue) => leftValue - rightValue);
    const pageButtons = sortedPages.map(page => {
      let gapValue;
      if (previous && page - previous > 1) {
        gapValue = '<span aria-hidden="true">…</span>';
      } else {
        gapValue = "";
      }
      const gap = gapValue;
      previous = page;
      return (
        `${gap}` +
        `<button type="button" data-page="${page}" aria-label="${window.HospitalI18n.html("ambulances.pagina_value", { value0: page })}"` +
        ` data-i18n-aria-label="ambulances.pagina_value" data-i18n-aria-label-params="${window.HospitalI18n.escape(JSON.stringify({ value0: page }))}"${page === currentPage ? ' class="is-active" aria-current="page"' : ""}>${page}` +
        `</button>`
      );
    });
    const numbers = pageButtons.join("");
    window.HospitalI18n.setHTML(
      pagination,
      `<button type="button" data-page="${currentPage - 1}" aria-label="${window.HospitalI18n.html("trace.pagina_anterior")}"` +
        ` data-i18n-aria-label="trace.pagina_anterior"${currentPage === 1 ? " disabled" : ""}>‹` +
        (`</button>${numbers}` +
          `<button type="button" data-page="${currentPage + 1}" aria-label="${window.HospitalI18n.html("trace.pagina_siguiente")}"` +
          ` data-i18n-aria-label="trace.pagina_siguiente"${currentPage === pageCount ? " disabled" : ""}>›`) +
        `</button>`
    );
  }

  function detailItem(label, value) {
    return `<dt>${label}</dt><dd>${escapeHtml(value || window.HospitalI18n.t("doctors.no_indicado"))}</dd>`;
  }

  function renderDetail(doctor) {
    document.getElementById("edit-from-detail").hidden = doctor.archived;
    window.HospitalI18n.setHTML(
      document.getElementById("delete-from-detail"),
      icon(doctor.archived ? "save-floppy.svg" : "trash-icon.svg") +
        (doctor.archived
          ? window.HospitalI18n.t("personnel.restaurar")
          : window.HospitalI18n.t("routes.eliminar"))
    );
    window.HospitalI18n.setHTML(
      document.getElementById("detail-content"),
      `<section class="detail-profile">
      ` +
        `<div class="detail-identity">` +
        `<span class="doctor-avatar">${icon("doctor-icon.svg")}</span>` +
        `<div>` +
        `<h3>${escapeHtml(fullName(doctor))}</h3>` +
        `<p>${escapeHtml(doctor.specialty)}</p>` +
        `</div>` +
        `</div>
      ` +
        `<div class="detail-profile-footer">` +
        `<span class="registration-label">${window.HospitalI18n.mark("doctors.registro_value", { value0: doctor.license })}</span>${pill(doctor)}</div>
    ` +
        `</section>
    ` +
        `<section class="detail-card">` +
        `<h3>${icon("person-icon.svg")}${window.HospitalI18n.mark("doctors.datos_de_contacto")}</h3>` +
        (`<dl>${detailItem(window.HospitalI18n.t("personnel.documento_c_i"), doctor.document)}${detailItem(window.HospitalI18n.t("destinations.telefono"), doctor.phone)}${detailItem(window.HospitalI18n.t("doctors.correo_electronico"), doctor.email)}` +
          `</dl>`) +
        `</section>
    ` +
        `<section class="detail-card">` +
        `<h3>${icon("specialty-icon.svg")}${window.HospitalI18n.mark("doctors.informacion_profesional")}</h3>` +
        (`<dl>${detailItem(window.HospitalI18n.t("doctors.registro_msp_2"), doctor.license)}${detailItem(window.HospitalI18n.t("patients.especialidad"), doctor.specialty)}${detailItem(window.HospitalI18n.t("patients.servicio"), doctor.service)}${detailItem(window.HospitalI18n.t("doctors.consultorio_area"), doctor.office)}` +
          `</dl>`) +
        `</section>
    ` +
        `<section class="detail-card">` +
        `<h3>${icon("calendar-icon.svg")}${window.HospitalI18n.mark("doctors.turno_y_atencion")}</h3>` +
        (`<dl>${detailItem(window.HospitalI18n.t("doctors.disponibilidad"), statusLabels[doctor.status]?.())}${detailItem(window.HospitalI18n.t("doctors.turno"), shiftLabels[doctor.shift]?.())}${detailItem(window.HospitalI18n.t("doctors.dias_de_atencion"), dayLabels[doctor.days]?.())}${detailItem(window.HospitalI18n.t("shared.horario"), hours(doctor))}` +
          `</dl>`) +
        `</section>
    ` +
        `<section class="detail-card">` +
        `<h3>${icon("document-icon.svg")}${window.HospitalI18n.mark("doctors.observaciones")}</h3>` +
        `<p>${escapeHtml(doctor.notes || window.HospitalI18n.t("doctors.sin_observaciones_registradas"))}</p>` +
        `</section>`
    );
  }

  function setBackgroundInert(value) {
    [
      directory,
      document.querySelector(".topbar"),
      document.querySelector(".sidebar"),
      document.querySelector(".doctor-summary-row")
    ].forEach(element => {
      element.inert = value;
    });
  }

  function restoreFocus(target) {
    if (target?.isConnected && !target.closest("[inert]")) {
      target.focus();
      return;
    }
    const button =
      lastAction &&
      [...rows.querySelectorAll("[data-action]")].find(
        item => item.dataset.id === lastAction.id && item.dataset.action === lastAction.action
      );
    (button || newButton).focus();
  }

  function showDetail(doctor) {
    selectedId = doctor.id;
    renderDetail(doctor);
    renderList();
    setBackgroundInert(true);
    scrim.hidden = false;
    detail.inert = false;
    detail.setAttribute("aria-hidden", "false");
    detail.classList.add("is-open");
    document.getElementById("detail-content").scrollTop = 0;
    requestAnimationFrame(() => {
      if (selectedId === doctor.id && !dialog.open && !deleteDialog.open) {
        document.getElementById("close-detail").focus();
      }
    });
  }

  function closeDetail(restore = true) {
    selectedId = null;
    detail.classList.remove("is-open");
    detail.setAttribute("aria-hidden", "true");
    detail.inert = true;
    scrim.hidden = true;
    setBackgroundInert(false);
    renderList();
    if (restore) {
      restoreFocus();
    }
  }

  function openForm(doctor = null) {
    formReturnFocus = document.activeElement;
    editingId = doctor?.id || null;
    editingVersion = doctor?.version || null;
    form.reset();
    formError.hidden = true;
    window.HospitalI18n.bindText(document.getElementById("form-title"), () => {
      if (doctor) {
        return window.HospitalI18n.t("doctors.editar_medico");
      } else {
        return window.HospitalI18n.t("doctors.nuevo_medico");
      }
    });
    window.HospitalI18n.bindText(document.getElementById("form-subtitle"), () => {
      if (doctor) {
        return window.HospitalI18n.t("doctors.actualiza_la_informacion_de_value", {
          value0: fullName(doctor)
        });
      } else {
        return window.HospitalI18n.t("doctors.completa_la_informacion_para_agregar_un_medico_al_directorio");
      }
    });
    window.HospitalI18n.bindText(document.getElementById("save-label"), () => {
      if (doctor) {
        return window.HospitalI18n.t("trace_view.guardar_cambios");
      } else {
        return window.HospitalI18n.t("doctors.guardar_medico");
      }
    });
    if (doctor) {
      fields.forEach(key => {
        form.elements[key].value = doctor[key];
      });
    }
    document.getElementById("form-status-dot").dataset.status = form.elements.status.value;
    dialog.showModal();
    document.querySelector(".doctor-form-fields").scrollTop = 0;
    form.elements.name.focus();
  }

  function openDelete(doctor) {
    deletingId = doctor.id;
    deletingVersion = doctor.version;
    deleteReturnFocus = document.activeElement;
    window.HospitalI18n.bindText(document.querySelector(".delete-note"), () => {
      if (doctor.archived) {
        return window.HospitalI18n.t(
          "doctors.volvera_al_directorio_con_sus_datos_y_disponibilidad_anteriores"
        );
      } else {
        return window.HospitalI18n.t("doctors.se_conservara_en_inactivos_y_en_el_historial_de_los_pacientes");
      }
    });
    window.HospitalI18n.bindText(document.getElementById("delete-title"), () => {
      if (doctor.archived) {
        return window.HospitalI18n.t("doctors.restaurar_este_medico");
      } else {
        return window.HospitalI18n.t("doctors.eliminar_este_medico");
      }
    });
    window.HospitalI18n.bindText(document.getElementById("confirm-delete"), () => {
      if (doctor.archived) {
        return window.HospitalI18n.t("doctors.restaurar_medico");
      } else {
        return window.HospitalI18n.t("doctors.eliminar_medico");
      }
    });
    window.HospitalI18n.bindText(document.getElementById("delete-description"), () =>
      window.HospitalI18n.t("doctors.se_value_a_value_value_deseas_continuar", {
        value0: doctor.archived
          ? window.HospitalI18n.t("doctors.restaurara")
          : window.HospitalI18n.t("doctors.archivara"),
        value1: fullName(doctor),
        value2: doctor.license
      })
    );
    deleteDialog.showModal();
  }

  function resetFilters() {
    search.value = "";
    specialtyFilter.value = "";
    activeStatus = window.HospitalI18n.t("trace.todos");
    currentPage = 1;
    filters.forEach(button => {
      const active = button.dataset.status === activeStatus;
      button.classList.toggle("is-active", active);
      button.setAttribute("aria-pressed", String(active));
    });
  }

  rows.addEventListener("click", event => {
    const button = event.target.closest("button[data-action]");
    if (!button) {
      return;
    }
    const doctor = doctors.find(item => item.id === button.dataset.id);
    if (!doctor) {
      return;
    }
    lastAction = { id: doctor.id, action: button.dataset.action };
    if (button.dataset.action === "view") {
      openCurrent(doctor.id);
    }
  });
  search.addEventListener("input", () => {
    currentPage = 1;
    renderList();
  });
  specialtyFilter.addEventListener("change", () => {
    currentPage = 1;
    renderList();
  });
  filters.forEach(button =>
    button.addEventListener("click", () => {
      activeStatus = button.dataset.status;
      currentPage = 1;
      filters.forEach(item => {
        const active = item === button;
        item.classList.toggle("is-active", active);
        item.setAttribute("aria-pressed", String(active));
      });
      renderList();
    })
  );
  document.getElementById("clear-filters").addEventListener("click", () => {
    resetFilters();
    renderList();
    search.focus();
  });
  pagination.addEventListener("click", event => {
    const button = event.target.closest("button[data-page]");
    if (!button || button.disabled) {
      return;
    }
    currentPage = Number(button.dataset.page);
    renderList();
    pagination.querySelector('[aria-current="page"]').focus();
  });
  newButton.addEventListener("click", () => openForm());
  document.getElementById("close-detail").addEventListener("click", () => closeDetail());
  scrim.addEventListener("click", () => closeDetail());
  document.getElementById("edit-from-detail").addEventListener("click", () => openCurrent(selectedId, true));
  document
    .getElementById("delete-from-detail")
    .addEventListener("click", () => openDelete(doctors.find(doctor => doctor.id === selectedId)));
  document.addEventListener("keydown", event => {
    if (!selectedId || dialog.open || deleteDialog.open) {
      return;
    }
    if (event.key === "Escape") {
      event.preventDefault();
      closeDetail();
    }
    if (event.key === "Tab") {
      const buttons = [...detail.querySelectorAll("button")];
      if (event.shiftKey && document.activeElement === buttons[0]) {
        event.preventDefault();
        buttons.at(-1).focus();
      } else if (!event.shiftKey && document.activeElement === buttons.at(-1)) {
        event.preventDefault();
        buttons[0].focus();
      }
    }
  });

  document
    .querySelectorAll("[data-close-form]")
    .forEach(button => button.addEventListener("click", () => dialog.close()));
  dialog.addEventListener("click", event => {
    if (event.target === dialog && !busy) {
      dialog.close();
    }
  });
  dialog.addEventListener("cancel", event => {
    if (busy) {
      event.preventDefault();
    }
  });
  dialog.addEventListener("close", () => {
    editingId = null;
    if (formReturnFocus) {
      restoreFocus(formReturnFocus);
    }
    formReturnFocus = null;
  });
  form.addEventListener("input", () => {
    formError.hidden = true;
  });
  form.addEventListener("change", () => {
    formError.hidden = true;
    document.getElementById("form-status-dot").dataset.status = form.elements.status.value;
  });
  form.addEventListener("submit", async event => {
    event.preventDefault();
    if (busy) {
      return;
    }
    formError.hidden = true;
    const record = Object.fromEntries(fields.map(key => [key, form.elements[key].value.trim()]));
    const fail = (key, message) => {
      console.error(message);
      form.elements[key].focus();
    };
    const missing = ["name", "document", "license", "specialty", "service", "phone"].find(
      key => !record[key]
    );
    if (missing) {
      fail(missing, window.HospitalI18n.t("doctors.completa_los_campos_obligatorios_con_informacion_valida"));
      return;
    }
    if (record.phone.replace(/\D/g, "").length < 8) {
      fail("phone", window.HospitalI18n.t("doctors.ingresa_un_telefono_con_al_menos_8_digitos"));
      return;
    }
    for (const [key, label] of [
      ["document", window.HospitalI18n.t("doctors.documento")],
      ["license", window.HospitalI18n.t("doctors.registro_msp_3")]
    ]) {
      if (!identifier(record[key])) {
        fail(key, window.HospitalI18n.t("doctors.ingresa_un_value_valido", { value0: label }));
        return;
      }
      if (
        doctors.some(doctor => doctor.id !== editingId && identifier(doctor[key]) === identifier(record[key]))
      ) {
        fail(key, window.HospitalI18n.t("doctors.ya_existe_un_medico_con_ese_value", { value0: label }));
        return;
      }
    }
    if (Boolean(record.startTime) !== Boolean(record.endTime)) {
      fail(
        record.startTime ? "endTime" : "startTime",
        window.HospitalI18n.t("doctors.completa_ambas_horas_o_deja_el_horario_sin_indicar")
      );
      return;
    }
    const wasEditing = Boolean(editingId);
    const payload = toApi(record);
    if (wasEditing) {
      Object.assign(payload, { id: editingId, version: editingVersion });
    }
    setBusy(true);
    try {
      const saved = upsert(await api.post(`doctors.php?action=${wasEditing ? "update" : "create"}`, payload));
      resetFilters();
      updateCatalogs();
      currentPage =
        Math.floor(matchingDoctors().findIndex(doctorRecord => doctorRecord.id === saved.id) / pageSize) + 1;
      formReturnFocus = null;
      dialog.close();
      showDetail(saved);
      toast(() => {
        if (wasEditing) {
          return window.HospitalI18n.t("doctors.datos_de_value_actualizados", { value0: fullName(saved) });
        } else {
          return window.HospitalI18n.t("doctors.value_agregado_al_directorio", { value0: fullName(saved) });
        }
      });
    } catch (error) {
      report(error);
    } finally {
      setBusy(false);
    }
  });

  document
    .querySelectorAll("[data-close-delete]")
    .forEach(button => button.addEventListener("click", () => deleteDialog.close()));
  deleteDialog.addEventListener("click", event => {
    if (event.target === deleteDialog && !busy) {
      deleteDialog.close();
    }
  });
  deleteDialog.addEventListener("cancel", event => {
    if (busy) {
      event.preventDefault();
    }
  });
  deleteDialog.addEventListener("close", () => {
    deletingId = null;
    if (deleteReturnFocus) {
      restoreFocus(deleteReturnFocus);
    }
    deleteReturnFocus = null;
  });
  document.getElementById("confirm-delete").addEventListener("click", async () => {
    const doctor = doctors.find(item => item.id === deletingId);
    if (!doctor) {
      return;
    }
    if (busy) {
      return;
    }
    setBusy(true);
    try {
      await api
        .post(`doctors.php?action=${doctor.archived ? "restore" : "archive"}`, {
          id: doctor.id,
          version: deletingVersion
        })
        .then(upsert);
      deleteReturnFocus = null;
      deleteDialog.close();
      updateCatalogs();
      closeDetail(false);
      newButton.focus();
      toast(
        () =>
          `${fullName(doctor)} ${doctor.archived ? window.HospitalI18n.t("doctors.restaurado") : window.HospitalI18n.t("doctors.eliminado_del_directorio")}.`
      );
    } catch (error) {
      report(error);
    } finally {
      setBusy(false);
    }
  });

  async function start() {
    newButton.disabled = true;
    updateCatalogs();
    window.HospitalI18n.bindText(document.getElementById("results-summary"), () =>
      window.HospitalI18n.t("doctors.cargando_medicos")
    );
    try {
      const session = await window.HospitalAuth.ready;
      if (!session) {
        return;
      }
      if (!session.user.roles.includes("records_manager")) {
        location.replace(api.page("src/views/management/management.php"));
        return;
      }
      await refresh();
      newButton.disabled = false;
    } catch (error) {
      report(error);
      window.HospitalI18n.bindText(document.getElementById("results-summary"), () => "");
    }
  }
  window.addEventListener("focus", () => {
    if (!busy && !dialog.open && !deleteDialog.open && !selectedId && !newButton.disabled) {
      refresh().catch(report);
    }
  });
  start();
  window.addEventListener('hospital:localechange', () => {
    updateCatalogs();
    renderList();
    const item = doctors.find(doctorRecord => doctorRecord.id === selectedId);
    if (item) {
      renderDetail(item);
    }
  });
})();
