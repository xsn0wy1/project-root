/* Lists surveys, filters, templates, details and lifecycle/archive actions.
 * API: surveys list/get/transition/links/link-update via HospitalSurveys.
 * DOM: survey-search, survey-status, survey-category and survey-pagination.
 * $ finds an element by ID; label functions translate at render time.
 */
(() => {
  'use strict';
  function transitionLabel(survey) {
    if (survey.lifecycle === 'draft') {
      return window.HospitalI18n.t("survey_view.publicar");
    }
    if (survey.lifecycle === 'closed') {
      return window.HospitalI18n.t("survey_view.reabrir");
    }
    return window.HospitalI18n.t("survey_view.cerrar_encuesta");
  }

  function transitionAction(survey) {
    if (survey.lifecycle === 'draft') {
      return 'publish';
    }
    if (survey.lifecycle === 'closed') {
      return 'reopen';
    }
    return 'close';
  }

  // Saved surveys and totals come from SQL; no browser records are imported.
  let surveys = [];
  const surveyApi = window.HospitalSurveys;
  const api = window.HospitalApi;
  let pendingDeleteSurvey = null;
  let mutating = false;
  let loadRevision = 0;
  async function load() {
    const revision = ++loadRevision;
    const rows = await surveyApi.directory($('survey-status').value === 'archived');
    if (revision !== loadRevision) {
      return;
    }
    surveys = rows;
    render();
  }
  const rating = value => {
    if (value === null || value === undefined) {
      return '—';
    } else {
      return `${Number(value).toLocaleString(window.HospitalI18n.locale, { maximumFractionDigits: 1 })}/5`;
    }
  };
  const categories = {
    patient_care: {
      label: function () {
        return window.HospitalI18n.t("survey.atencion_al_paciente");
      },
      icon: 'hrate-icon.svg',
      color: 'violet'
    },
    transfers: {
      label: function () {
        return window.HospitalI18n.t("survey.traslados");
      },
      icon: 'ambulance-icon.png',
      color: 'cyan'
    },
    emergency: {
      label: function () {
        return window.HospitalI18n.t("survey.emergencias");
      },
      icon: 'medkit-icon.png',
      color: 'rose'
    },
    staff: {
      label: function () {
        return window.HospitalI18n.t("trace_view.personal");
      },
      icon: 'people-svgrepo-com.svg',
      color: 'amber'
    },
    other: {
      label: function () {
        return window.HospitalI18n.t("survey.otras");
      },
      icon: 'survey-icon.svg',
      color: 'violet'
    }
  };
  const audiences = {
    patients: function () {
      return window.HospitalI18n.t("management.pacientes");
    },
    staff: function () {
      return window.HospitalI18n.t("trace_view.personal");
    },
    everyone: function () {
      return window.HospitalI18n.t("survey_new.pacientes_y_personal");
    }
  };
  const statuses = {
    draft: function () {
      return window.HospitalI18n.t("shared.borrador");
    },
    published: function () {
      return window.HospitalI18n.t("shared.activa");
    },
    closed: function () {
      return window.HospitalI18n.t("shared.finalizada");
    },
    archived: function () {
      return window.HospitalI18n.t("shared.archivada");
    }
  };
  let templates = [];
  const $ = id => document.getElementById(id);
  const escape = window.HospitalUI.escapeHtml;
  const normalize = value => {
    const normalizedText = String(value).normalize('NFD');
    const replacedText = normalizedText.replace(/[\u0300-\u036f]/g, '');
    return replacedText.toLowerCase();
  };
  const icon = name => `<img src="../../../assets/Icons/${name}" alt="">`;
  const tile = category =>
    `<span class="survey-icon ${categories[category].color}">${icon(categories[category].icon)}</span>`;
  const collator = {
    compare: (...args) =>
      new Intl.Collator(window.HospitalI18n.locale, { sensitivity: 'base', numeric: true }).compare(...args)
  };
  const dateFormat = {
    format: (...args) =>
      new Intl.DateTimeFormat(window.HospitalI18n.locale, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'America/Montevideo'
      }).format(...args)
  };
  const timeFormat = {
    format: (...args) =>
      new Intl.DateTimeFormat(window.HospitalI18n.locale, {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'America/Montevideo'
      }).format(...args)
  };
  let selectedId = null;
  let pendingDeleteId = null;
  let page = 1;
  let view = 'list';
  let renderedPageSize = null;
  const filterDropdowns = [];

  function syncFilterDropdowns() {
    filterDropdowns.forEach(({ control, button, label, options }) => {
      window.HospitalI18n.bindText(label, () =>
        window.HospitalI18n.t(control.selectedOptions[0].dataset.i18n)
      );
      button.classList.toggle('is-filtered', control.value !== control.options[0].value);
      options.forEach(option =>
        option.setAttribute('aria-pressed', String(option.dataset.filterValue === control.value))
      );
    });
  }
  function closeFilterDropdown(dropdown) {
    dropdown.menu.hidden = true;
    dropdown.button.setAttribute('aria-expanded', 'false');
  }
  function setupFilterDropdown(control) {
    const button = $(control.id + '-button');
    const menu = $(control.id + '-menu');
    const label = $(control.id + '-label');
    // Keep existing option codes/labels as the source for filtering and sorting.
    const options = [...control.options].map(option => {
      const choice = document.createElement('button');
      choice.type = 'button';
      choice.dataset.filterValue = option.value;
      choice.dataset.i18n = option.dataset.i18n;
      choice.textContent = option.textContent;
      menu.append(choice);
      return choice;
    });
    const dropdown = { control, button, menu, label, options, root: button.parentElement };
    filterDropdowns.push(dropdown);
    function open() {
      filterDropdowns.forEach(closeFilterDropdown);
      menu.hidden = false;
      button.setAttribute('aria-expanded', 'true');
    }
    button.addEventListener('click', () => {
      if (menu.hidden) {
        open();
      } else {
        closeFilterDropdown(dropdown);
      }
    });
    button.addEventListener('keydown', event => {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        open();
        (event.key === 'ArrowUp'
          ? options[options.length - 1]
          : options.find(option => option.dataset.filterValue === control.value)
        ).focus();
      } else if (event.key === 'Escape') {
        event.preventDefault();
        closeFilterDropdown(dropdown);
      }
    });
    menu.addEventListener('click', event => {
      const choice = event.target.closest('[data-filter-value]');
      if (!choice) {
        return;
      }
      const changed = control.value !== choice.dataset.filterValue;
      control.value = choice.dataset.filterValue;
      closeFilterDropdown(dropdown);
      if (changed) {
        control.dispatchEvent(new Event('change', { bubbles: true }));
      }
      button.focus();
    });
    menu.addEventListener('keydown', event => {
      if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
        event.preventDefault();
        const index = options.indexOf(document.activeElement);
        let next;
        if (event.key === 'Home') {
          next = 0;
        } else {
          if (event.key === 'End') {
            next = options.length - 1;
          } else {
            next = (index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
          }
        }
        options[next].focus();
      } else if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        closeFilterDropdown(dropdown);
        button.focus();
      }
    });
    control.addEventListener('change', () => {
      syncFilterDropdowns();
      page = 1;
      if (control.id === 'survey-status') {
        load().catch(error => api.report(error, 'surveys'));
      } else {
        render();
      }
    });
  }

  function activity(date) {
    const today = dateFormat.format(new Date());
    if (dateFormat.format(new Date(date)) === today) {
      return window.HospitalI18n.t("survey.hoy_value", { value0: timeFormat.format(new Date(date)) });
    } else {
      return dateFormat.format(new Date(date));
    }
  }
  function selected() {
    return surveys.find(survey => survey.id === selectedId);
  }
  function questionOptions(question) {
    if (Array.isArray(question.options)) {
      return question.options;
    } else {
      return String(question.options || '')
        .split('\n')
        .filter(Boolean);
    }
  }
  function pageSize() {
    if (!window.matchMedia('(min-width: 771px)').matches)
      if (view === 'grid') {
        return 4;
      } else {
        return 6;
      }
    const directory = document.querySelector('.survey-directory');
    const heading = directory.querySelector('.directory-heading').getBoundingClientRect().height;
    const footer = Math.max(46, directory.querySelector('.survey-footer').getBoundingClientRect().height);
    let columnsValue;
    if (view === 'list') {
      columnsValue = $('survey-list-head').getBoundingClientRect().height || 32;
    } else {
      columnsValue = 0;
    }
    const columns = columnsValue;
    const available = directory.clientHeight - heading - footer - columns - 10;
    if (view === 'grid') {
      if (available < 300) {
        return 2;
      } else {
        return 4;
      }
    } else {
      return Math.max(1, Math.min(6, Math.floor((available + 4) / 48)));
    }
  }
  function filteredSurveys() {
    const search = normalize($('survey-search').value.trim());
    const status = $('survey-status').value;
    const category = $('survey-category').value;
    return surveys
      .filter(
        survey =>
          (status === 'all' || survey.status === status) &&
          (category === 'all' || survey.category === category) &&
          normalize(`${survey.title} ${survey.description} ${categories[survey.category].label()}`).includes(
            search
          )
      )
      .sort((leftValue, rightValue) => {
        if ($('survey-sort').value === 'name') {
          return collator.compare(leftValue.title, rightValue.title) || rightValue.id - leftValue.id;
        }
        if ($('survey-sort').value === 'responses') {
          return rightValue.response_count - leftValue.response_count || rightValue.id - leftValue.id;
        }
        const difference =
          leftValue.updated_at.localeCompare(rightValue.updated_at) || leftValue.id - rightValue.id;
        if ($('survey-sort').value === 'oldest') {
          return difference;
        } else {
          return -difference;
        }
      });
  }
  function resetFilters() {
    $('survey-search').value = '';
    $('survey-status').value = 'all';
    $('survey-category').value = 'all';
    filterDropdowns.forEach(closeFilterDropdown);
    syncFilterDropdowns();
    page = 1;
  }
  function renderStats() {
    window.HospitalI18n.bindText($('stat-total'), () => surveys.length);
    const drafts = surveys.filter(survey => survey.status === 'draft').length;
    window.HospitalI18n.bindText(
      $('stat-drafts'),
      () =>
        `${drafts} ${drafts === 1 ? window.HospitalI18n.t("shared.borrador_2") : window.HospitalI18n.t("shared.borradores")}`
    );
    window.HospitalI18n.bindText(
      $('stat-active'),
      () => surveys.filter(survey => survey.accepting_responses).length
    );
    window.HospitalI18n.bindText($('stat-responses'), () =>
      surveys
        .reduce((total, survey) => total + survey.response_count, 0)
        .toLocaleString(window.HospitalI18n.locale)
    );
    const rated = surveys.filter(
      surveyRecord => surveyRecord.average_rating !== null && surveyRecord.rating_count > 0
    );
    const ratingCount = rated.reduce((sum, surveyRecord) => sum + surveyRecord.rating_count, 0);
    window.HospitalI18n.bindText($('stat-satisfaction'), () => {
      if (ratingCount) {
        return rating(
          rated.reduce(
            (sum, surveyRecord) => sum + surveyRecord.average_rating * surveyRecord.rating_count,
            0
          ) / ratingCount
        );
      } else {
        return '—';
      }
    });
    window.HospitalI18n.bindText($('stat-satisfaction').nextElementSibling, () => {
      if (ratingCount) {
        return window.HospitalI18n.t("survey.valoraciones_normalizadas_sobre_5");
      } else {
        return window.HospitalI18n.t("survey.sin_valoraciones_todavia");
      }
    });
  }
  function renderPagination(total) {
    const pages = Math.ceil(total / pageSize());
    if (pages <= 1) {
      window.HospitalI18n.setHTML($('survey-pagination'), '');
      return;
    }
    const numbers = [...new Set([1, page - 1, page, page + 1, pages])]
      .filter(number => number > 0 && number <= pages)
      .sort((leftValue, rightValue) => leftValue - rightValue);
    let previous = 0;
    const buttons = numbers
      .map(number => {
        let gapValue;
        if (previous && number - previous > 1) {
          gapValue = '<span aria-hidden="true">…</span>';
        } else {
          gapValue = '';
        }
        const gap = gapValue;
        previous = number;
        return (
          `${gap}` +
          `<button type="button" data-page="${number}" aria-label="${window.HospitalI18n.html("ambulances.pagina_value", { value0: number })}"` +
          ` data-i18n-aria-label="ambulances.pagina_value" data-i18n-aria-label-params="${window.HospitalI18n.escape(JSON.stringify({ value0: number }))}"` +
          ` ${number === page ? 'aria-current="page"' : ''}>${number}` +
          `</button>`
        );
      })
      .join('');
    window.HospitalI18n.setHTML(
      $('survey-pagination'),
      `<button type="button" data-page="${page - 1}" aria-label="${window.HospitalI18n.html("trace.pagina_anterior")}"` +
        ` data-i18n-aria-label="trace.pagina_anterior" ${page === 1 ? 'disabled' : ''}>‹` +
        `</button>${buttons}` +
        `<button type="button" data-page="${page + 1}" aria-label="${window.HospitalI18n.html("trace.pagina_siguiente")}"` +
        ` data-i18n-aria-label="trace.pagina_siguiente" ${page === pages ? 'disabled' : ''}>›` +
        `</button>`
    );
  }
  function renderDetail() {
    const survey = selected();
    $('detail-actions').hidden = !survey;
    document.querySelector('.survey-detail').classList.toggle('has-selection', Boolean(survey));
    $('edit-survey').hidden = Boolean(survey?.archived_at);
    window.HospitalI18n.bindAttr($('delete-survey'), "aria-label", () => {
      if (survey?.archived_at) {
        return window.HospitalI18n.t("survey.restaurar_encuesta");
      } else {
        return window.HospitalI18n.t("survey.archivar_encuesta");
      }
    });
    if (survey) {
      $('edit-survey').href = `survey-new.php?id=${encodeURIComponent(survey.id)}`;
    }
    $('survey-detail-content').scrollTop = 0;
    if (!survey) {
      window.HospitalI18n.setHTML(
        $('survey-detail-content'),
        `<div class="detail-placeholder">` +
          `<span class="survey-icon violet">${icon('survey-icon.svg')}` +
          `</span>` +
          `<h3>${window.HospitalI18n.mark("survey_view.selecciona_una_encuesta")}` +
          `</h3>` +
          `<p>${window.HospitalI18n.mark("survey.aca_encontraras_sus_preguntas_informacion_y_opciones_de_acceso")}` +
          `</p>` +
          `</div>`
      );
      return;
    }

    window.HospitalI18n.setHTML(
      $('survey-detail-content'),
      `
            ` +
        `<div class="detail-identity">${tile(survey.category)}` +
        `<div>` +
        `<h3 title="${escape(survey.title)}">${escape(survey.title)}` +
        `</h3>` +
        `<span class="survey-status ${survey.status}">${statuses[survey.status]?.()}` +
        `</span>` +
        `</div>` +
        `</div>
            ` +
        `<dl class="detail-meta">` +
        `<div>${icon('calendar-icon.svg')}` +
        `<dt>${window.HospitalI18n.mark("survey.creada")}` +
        `</dt>` +
        `<dd>${escape(dateFormat.format(new Date(survey.created_at)))}` +
        `</dd>` +
        `</div>` +
        `<div>${icon('layers-icon.svg')}` +
        `<dt>${window.HospitalI18n.mark("survey.categoria")}` +
        `</dt>` +
        `<dd>${categories[survey.category].label()}` +
        `</dd>` +
        `</div>` +
        `<div>${icon('person-icon.svg')}` +
        `<dt>${window.HospitalI18n.mark("survey_new.dirigida_a")}` +
        `</dt>` +
        `<dd>${audiences[survey.audience]?.()}` +
        `</dd>` +
        `</div>` +
        `</dl>
            ` +
        `<div class="detail-numbers">` +
        `<div>` +
        `<strong>${survey.response_count}` +
        `</strong>` +
        `<small>${window.HospitalI18n.mark("survey.respuestas")}` +
        `</small>` +
        `</div>` +
        `<div>` +
        `<strong>${survey.questions.filter(questionRecord => !['section', 'divider'].includes(questionRecord.type)).length}` +
        `</strong>` +
        `<small>${survey.questions.filter(questionRecord => !['section', 'divider'].includes(questionRecord.type)).length === 1 ? window.HospitalI18n.t("survey.pregunta") : window.HospitalI18n.t("survey_new.preguntas")}` +
        `</small>` +
        `</div>` +
        `<div>` +
        `<strong>${rating(survey.average_rating)}` +
        `</strong>` +
        `<small>${window.HospitalI18n.mark("survey.valoracion_2")}` +
        `</small>` +
        `</div>` +
        `</div>
            ` +
        `<section class="patient-access">` +
        `<h4>${window.HospitalI18n.mark("survey.acceso_y_resultados")}` +
        `</h4>` +
        `<div class="share-controls">` +
        `<button type="button" class="survey-button secondary" id="read-survey">${icon('eye.svg')}${window.HospitalI18n.mark("survey_view.ver_formulario")}` +
        `</button>` +
        `<button type="button" class="survey-button secondary" data-share-survey ${!survey.published_at || survey.archived_at ? 'disabled' : ''}>${icon('share-icon.svg')}${window.HospitalI18n.mark("survey_view.copiar_enlace")}` +
        `</button>` +
        `<button type="button" class="survey-button secondary" data-share-survey ${!survey.published_at || survey.archived_at ? 'disabled' : ''}>${icon('qr-icon.svg')}${window.HospitalI18n.mark("survey_view.codigo_qr")}` +
        `</button>` +
        `<a class="survey-button secondary" href="survey-view.php?id=${encodeURIComponent(survey.id)}">${icon('analytics-icon.svg')}${window.HospitalI18n.mark("survey.ver_resultados")}` +
        `</a>${survey.archived_at || !['draft', 'closed'].includes(survey.lifecycle) ? '' : `<button type="button" class="survey-button secondary" data-transition="${transitionAction(survey)}">${transitionLabel(survey)}</button>`}` +
        `</div>` +
        `</section>`
    );
    $('survey-detail-content').scrollTop = 0;
  }
  function render() {
    syncFilterDropdowns();
    const filtered = filteredSurveys();
    page = Math.max(1, Math.min(page, Math.ceil(filtered.length / pageSize()) || 1));
    renderedPageSize = pageSize();
    const start = (page - 1) * renderedPageSize;
    const visible = filtered.slice(start, start + renderedPageSize);
    if (!visible.some(survey => survey.id === selectedId)) {
      selectedId = visible[0]?.id ?? null;
    }
    window.HospitalI18n.bindText($('survey-count'), () => filtered.length);
    $('survey-list').classList.toggle('is-grid', view === 'grid');
    $('survey-list').style.setProperty('--survey-page-rows', String(renderedPageSize));
    $('survey-list').style.setProperty('--survey-grid-rows', renderedPageSize === 2 ? '1' : '2');
    $('survey-list').hidden = !visible.length;
    $('survey-list-head').hidden = view === 'grid';
    window.HospitalI18n.setHTML(
      $('survey-list'),
      visible
        .map(
          survey =>
            `<article class="survey-row ${survey.id === selectedId ? 'is-selected' : ''}" data-id="${survey.id}">
            ` +
            `<div class="survey-identity">${tile(survey.category)}` +
            `<div>` +
            `<button type="button" class="survey-title-button" data-select="${survey.id}" aria-pressed="${survey.id === selectedId}"` +
            ` title="${escape(survey.title)}">${escape(survey.title)}` +
            `</button>` +
            `<span class="survey-subtitle">${categories[survey.category].label()} · ${survey.questions.filter(questionRecord => !['section', 'divider'].includes(questionRecord.type)).length} ${survey.questions.filter(questionRecord => !['section', 'divider'].includes(questionRecord.type)).length === 1 ? window.HospitalI18n.t("survey.pregunta") : window.HospitalI18n.t("survey_new.preguntas")}` +
            `</span>` +
            `</div>` +
            `</div>
            ` +
            `<span class="survey-status ${survey.status}" aria-label="${window.HospitalI18n.html("survey.estado_value", { value0: (statuses[survey.status]?.()).toLowerCase() })}"` +
            ` data-i18n-aria-label="survey.estado_value" data-i18n-aria-label-params="${window.HospitalI18n.escape(JSON.stringify({ value0: (statuses[survey.status]?.()).toLowerCase() }))}">${statuses[survey.status]?.()}` +
            `</span>
            ` +
            `<span class="row-responses">` +
            `<span class="visually-hidden">${window.HospitalI18n.mark("survey.respuestas_2")} ` +
            `</span>${survey.response_count}` +
            `<small>${window.HospitalI18n.mark("survey.respuestas")}` +
            `</small>` +
            `</span>` +
            `<span class="row-satisfaction" data-i18n-caption="survey.satisfaction_caption">` +
            `<span class="visually-hidden">${window.HospitalI18n.mark("survey.valoracion_3")} ` +
            `</span>${rating(survey.average_rating)}` +
            `</span>` +
            `<time class="row-updated" datetime="${survey.updated_at}">${escape(activity(survey.updated_at))}` +
            `</time>
            ` +
            `<details class="row-menu">` +
            `<summary aria-label="${window.HospitalI18n.html("survey.acciones_de_value", { value0: survey.title })}" data-i18n-aria-label="survey.acciones_de_value"` +
            ` data-i18n-aria-label-params="${window.HospitalI18n.escape(JSON.stringify({ value0: survey.title }))}">⋮` +
            `</summary>` +
            `<div class="row-menu-content">` +
            `<button type="button" data-edit="${survey.id}">${window.HospitalI18n.mark("survey_view.editar_encuesta")}` +
            `</button>` +
            `<button type="button" data-duplicate="${survey.id}">${window.HospitalI18n.mark("survey.duplicar_borrador")}` +
            `</button>` +
            `<button type="button" data-delete="${survey.id}">${survey.archived_at ? window.HospitalI18n.t("survey.restaurar_encuesta") : window.HospitalI18n.t("survey.archivar_encuesta")}` +
            `</button>` +
            `</div>` +
            `</details>` +
            `</article>`
        )
        .join('')
    );
    $('survey-empty').hidden = !!visible.length;
    const hasFilter =
      $('survey-search').value.trim() ||
      $('survey-status').value !== 'all' ||
      $('survey-category').value !== 'all';
    window.HospitalI18n.bindText($('empty-title'), () => {
      if (hasFilter) {
        return window.HospitalI18n.t("survey.no_encontramos_encuestas");
      } else {
        return window.HospitalI18n.t("survey.crea_tu_primera_encuesta");
      }
    });
    window.HospitalI18n.bindText($('empty-description'), () => {
      if (hasFilter) {
        return window.HospitalI18n.t(
          "survey.proba_con_otra_busqueda_o_cambia_los_filtros_para_encontrar_tu_encuesta"
        );
      } else {
        return window.HospitalI18n.t(
          "survey.conoce_como_viven_la_atencion_tus_pacientes_y_como_se_siente_tu_equipo_empeza_desde_cero_o"
        );
      }
    });
    $('empty-actions').hidden = !!hasFilter;
    $('clear-filters').hidden = !hasFilter;
    window.HospitalI18n.bindText($('results-summary'), () => {
      if (visible.length) {
        return window.HospitalI18n.t("survey.mostrando_value_value_de_value_value", {
          value0: start + 1,
          value1: start + visible.length,
          value2: filtered.length,
          value3:
            filtered.length === 1
              ? window.HospitalI18n.t("survey.encuesta_2")
              : window.HospitalI18n.t("survey.encuestas")
        });
      } else {
        return window.HospitalI18n.t("survey.0_encuestas");
      }
    });
    renderPagination(filtered.length);
    renderStats();
    renderDetail();
  }
  function openDelete(id) {
    const survey = surveys.find(item => item.id === id);
    if (!survey) {
      return;
    }
    pendingDeleteId = id;
    pendingDeleteSurvey = { ...survey };
    window.HospitalI18n.bindText($('delete-title'), () => {
      if (survey.archived_at) {
        return window.HospitalI18n.t("survey.restaurar_esta_encuesta");
      } else {
        return window.HospitalI18n.t("survey.archivar_esta_encuesta");
      }
    });
    window.HospitalI18n.bindText($('confirm-delete'), () => {
      if (survey.archived_at) {
        return window.HospitalI18n.t("personnel.restaurar");
      } else {
        return window.HospitalI18n.t("patients.archivar");
      }
    });
    window.HospitalI18n.bindText($('delete-description'), () => survey.title);
    $('delete-dialog').showModal();
  }
  function readSurvey() {
    if (selected()) {
      location.assign(`survey-view.php?id=${selected().id}&tab=form`);
    }
  }

  function renderTemplates() {
    window.HospitalI18n.setHTML(
      $('template-grid'),
      templates
        .map(
          template =>
            `<a class="template-card" href="survey-new.php?template=${encodeURIComponent(template.category)}">${tile(template.category)}` +
            `<strong>${escape(template.title)}` +
            `</strong>` +
            `<p>${escape(template.description)}` +
            `</p>` +
            `<small>${window.HospitalI18n.mark("survey.value_preguntas_value", { value0: template.questions.length, value1: audiences[template.audience]?.() })} ` +
            `<span aria-hidden="true">↗` +
            `</span>` +
            `</small>` +
            `</a>`
        )
        .join('')
    );
  }

  document.addEventListener('DOMContentLoaded', async () => {
    await window.HospitalI18n.ready;
    await window.HospitalAuth.ready;
    window.addEventListener('hospital:localechange', () => {
      templates = window.HospitalSurveyTemplates();
      renderTemplates();
      render();
    });

    templates = window.HospitalSurveyTemplates();
    renderTemplates();
    document
      .querySelectorAll('[data-open-templates]')
      .forEach(button => button.addEventListener('click', () => $('templates-dialog').showModal()));
    document
      .querySelectorAll('[data-close-dialog]')
      .forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
    $('survey-list').addEventListener('click', event => {
      const row = event.target.closest('[data-id]');
      if (!row) {
        return;
      }
      const id = row.dataset.id;
      if (event.target.closest('[data-edit]')) {
        location.href = `survey-new.php?id=${encodeURIComponent(id)}`;
      } else if (event.target.closest('[data-delete]')) {
        openDelete(id);
      } else if (event.target.closest('[data-duplicate]')) {
        location.href = `survey-new.php?copy_id=${encodeURIComponent(id)}`;
      } else if (!event.target.closest('.row-menu')) {
        selectedId = id;
        // Keep the clicked control in the DOM so keyboard focus is preserved.
        $('survey-list')
          .querySelectorAll('[data-id]')
          .forEach(item => {
            const active = item.dataset.id === id;
            item.classList.toggle('is-selected', active);
            item.querySelector('[data-select]').setAttribute('aria-pressed', String(active));
          });
        renderDetail();
      }
      if (event.target.closest('.row-menu-content')) {
        row.querySelector('details').open = false;
      }
    });
    document.addEventListener('click', event => {
      document.querySelectorAll('.row-menu[open]').forEach(menu => {
        if (!menu.contains(event.target)) {
          menu.open = false;
        }
      });
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        document.querySelectorAll('.row-menu[open]').forEach(menu => {
          menu.open = false;
          menu.querySelector('summary').focus();
        });
      }
    });
    $('survey-detail-content').addEventListener('click', async event => {
      if (event.target.closest('#read-survey')) {
        readSurvey();
      }
      const current = selected();
      if (!current || mutating) {
        return;
      }
      try {
        if (event.target.closest('[data-share-survey]')) {
          await surveyApi.share(current, () => load().catch(error => api.report(error, 'surveys')));
        }
        const button = event.target.closest('[data-transition]');
        if (button) {
          mutating = true;
          button.disabled = true;
          await surveyApi.transition(current, button.dataset.transition);
          await load();
        }
      } catch (error) {
        api.report(error, 'surveys');
      } finally {
        mutating = false;
      }
    });
    $('delete-survey').addEventListener('click', () => {
      if (selected()) {
        openDelete(selectedId);
      }
    });
    $('confirm-delete').addEventListener('click', async () => {
      if (mutating || !pendingDeleteSurvey) {
        return;
      }
      mutating = true;
      const record = pendingDeleteSurvey;
      $('delete-dialog').inert = true;
      try {
        await surveyApi.transition(record, record.archived_at ? 'restore' : 'archive');
        $('delete-dialog').close();
        await load();
      } catch (error) {
        api.report(error, 'surveys');
      } finally {
        mutating = false;
        $('delete-dialog').inert = false;
      }
    });
    $('delete-dialog').addEventListener('close', () => {
      pendingDeleteId = null;
    });
    $('survey-search').addEventListener('input', () => {
      page = 1;
      render();
    });
    ['survey-status', 'survey-category', 'survey-sort'].map($).forEach(setupFilterDropdown);
    syncFilterDropdowns();
    for (const eventName of ['click', 'focusin']) {
      document.addEventListener(eventName, event => {
        filterDropdowns.forEach(dropdown => {
          if (!dropdown.root.contains(event.target)) {
            closeFilterDropdown(dropdown);
          }
        });
      });
    }
    $('clear-filters').addEventListener('click', () => {
      resetFilters();
      load().catch(error => api.report(error, 'surveys'));
      $('survey-search').focus();
    });
    document.querySelectorAll('[data-view]').forEach(button =>
      button.addEventListener('click', () => {
        view = button.dataset.view;
        page = 1;
        document.querySelectorAll('[data-view]').forEach(control => {
          control.classList.toggle('is-active', control === button);
          control.setAttribute('aria-pressed', String(control === button));
        });
        render();
      })
    );
    $('survey-pagination').addEventListener('click', event => {
      const button = event.target.closest('[data-page]');
      if (!button || button.disabled) {
        return;
      }
      page = Number(button.dataset.page);
      render();
      $('survey-list').scrollTop = 0;
      $('survey-pagination').querySelector('[aria-current]')?.focus();
    });
    try {
      if (await window.HospitalAuth.ready) {
        await load();
      }
    } catch (error) {
      api.report(error, 'surveys');
    }
    new ResizeObserver(() => {
      if (window.matchMedia('(min-width: 771px)').matches && pageSize() !== renderedPageSize) {
        render();
      }
    }).observe(document.querySelector('.survey-directory'));
  });
})();
