/* Translates shared interface text while retaining record values, focus and scroll.
 * API: public translations for Spanish/English; staff locale is confirmed by auth-session.
 * DOM: data-i18n bindings and language selects; t translates a key.
 * locale retains its existing live property interface.
 */
(() => {
  'use strict';
  function selectLocaleValue(select) {
    if (select.querySelector('option[value="es-UY"]')) {
      return locale;
    }
    if (locale === 'en') {
      return 'en';
    }
    return 'es';
  }

  const root = new URL('../../', document.currentScript.src);
  const dictionaries = { es: {}, en: {} };
  const bindings = new Map();
  let localeValue;
  if (document.documentElement.lang === 'en') {
    localeValue = 'en';
  } else {
    localeValue = 'es-UY';
  }
  let locale = localeValue;
  let pendingLocale = null;
  let confirmedLocale = 'es-UY';
  let readyDone = false;
  const normalize = value => {
    if (value === 'en') {
      return 'en';
    } else {
      return 'es-UY';
    }
  };
  const publicPage =
    location.pathname === root.pathname ||
    location.pathname === new URL('index.php', root).pathname ||
    location.pathname.includes('/register-restore/') ||
    location.pathname.endsWith('/surveys/user-view/survey-user.php');
  if (publicPage) {
    locale = normalize(new URL(location.href).searchParams.get('lang'));
  }
  function reveal() {
    clearTimeout(revealTimeout);
    document.documentElement.classList.add('i18n-ready');
  }
  const revealTimeout = setTimeout(reveal, 1500);
  const escape = value =>
    String(value ?? '').replace(
      /[&<>"']/g,
      char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]
    );
  function lookup(key, params = {}) {
    let value = dictionaries[locale === 'en' ? 'en' : 'es'][key] ?? dictionaries.es[key];
    if (value && typeof value === 'object') {
      let formValue;
      if (Number(params.count) === 1) {
        formValue = 'one';
      } else {
        formValue = 'other';
      }
      const form = formValue;
      value = value[form] ?? dictionaries.es[key]?.[form];
    }
    if (typeof value !== 'string' && typeof dictionaries.es[key] === 'string') {
      value = dictionaries.es[key];
    }
    if (typeof value === 'string') {
      return value;
    } else {
      return null;
    }
  }
  function t(key, params = {}) {
    const value = lookup(key, params);
    if (value === null) {
      return '';
    }
    return value.replace(/\{([a-zA-Z][a-zA-Z0-9_]*)\}/g, (match, name) => {
      if (Object.hasOwn(params, name)) {
        return String(params[name] ?? '');
      } else {
        return match;
      }
    });
  }
  // Only explicitly bound copy is translated; interpolated record values remain data.
  function mark(key, params = {}) {
    return `<i18n-text data-i18n="${escape(key)}" data-i18n-params="${escape(JSON.stringify(params))}">${escape(t(key, params))}</i18n-text>`;
  }
  function paramsFor(node, name = '') {
    try {
      return JSON.parse(
        node.getAttribute(`data-i18n${name ? '-' + name : ''}-params`) ||
          node.getAttribute('data-i18n-params') ||
          '{}'
      );
    } catch (_) {
      return {};
    }
  }
  function translate(node = document) {
    if (node.nodeType !== Node.ELEMENT_NODE && node.nodeType !== Node.DOCUMENT_NODE) {
      return;
    }
    const elements = [
      ...(node.matches?.(
        '[data-i18n], [data-i18n-title], [data-i18n-placeholder], [data-i18n-alt], [data-i18n-aria-label], [data-i18n-aria-description], [data-i18n-caption]'
      )
        ? [node]
        : []),
      ...node.querySelectorAll(
        '[data-i18n], [data-i18n-title], [data-i18n-placeholder], [data-i18n-alt], [data-i18n-aria-label], [data-i18n-aria-description], [data-i18n-caption]'
      )
    ];
    for (const element of elements) {
      const key = element.getAttribute('data-i18n');
      if (key && lookup(key, paramsFor(element)) !== null) {
        const value = t(key, paramsFor(element));
        if (element.textContent !== value) {
          element.textContent = value;
        }
      }
      for (const name of ['title', 'placeholder', 'alt', 'aria-label', 'aria-description', 'caption']) {
        const attrKey = element.getAttribute('data-i18n-' + name);
        if (attrKey && lookup(attrKey, paramsFor(element, name)) !== null) {
          element.setAttribute(
            name === 'caption' ? 'data-caption' : name,
            t(attrKey, paramsFor(element, name))
          );
        }
      }
    }
    publicLinks(node);
    if (readyDone && node === document) {
      reveal();
    }
  }
  function binding(node, name, getter) {
    if (!node) {
      return '';
    }
    if (!node.nodeType && node !== document) {
      const value = getter();
      node[name] = value;
      return value;
    }
    if (name === 'textContent') {
      node.removeAttribute?.('data-i18n');
      node.removeAttribute?.('data-i18n-params');
    } else {
      node.removeAttribute?.('data-i18n-' + name);
    }
    let fields = bindings.get(node);
    if (!fields) {
      bindings.set(node, (fields = new Map()));
    }
    fields.set(name, getter);
    const value = String(getter() ?? '');
    if (name === 'textContent' || (name === 'title' && node.nodeType === Node.DOCUMENT_NODE)) {
      if (node[name] !== value) {
        node[name] = value;
      }
    } else if (name === '$validity') {
      node.setCustomValidity(value);
    } else if (node.getAttribute(name) !== value) {
      node.setAttribute(name, value);
    }
    return value;
  }
  function publicLinks(node = document) {
    if (!publicPage) {
      return;
    }
    for (const link of [
      ...(node.matches?.('a[href]') ? [node] : []),
      ...(node.querySelectorAll?.('a[href]') || [])
    ]) {
      const url = new URL(link.getAttribute('href'), location.href);
      if (
        url.origin !== location.origin ||
        (url.hash && url.pathname === location.pathname) ||
        !/(?:index\.php|register-restore\/[^/]+\.php)$/.test(url.pathname)
      ) {
        continue;
      }
      if (locale === 'en') {
        url.searchParams.set('lang', 'en');
      } else {
        url.searchParams.delete('lang');
      }
      if (link.href !== url.href) {
        link.href = url.href;
      }
    }
  }
  function apply(value) {
    const next = normalize(value);
    const changed = next !== locale;
    locale = next;
    document.documentElement.lang = locale === 'en' ? 'en' : 'es-UY';
    if (!readyDone) {
      return locale;
    }
    const active = document.activeElement;
    const activeId = active?.id;
    const scopeId = active?.parentElement?.closest('[id]')?.id;
    const stableAttributes = [
      'name',
      'data-action',
      'data-id',
      'data-select-id',
      'data-route-id',
      'data-transfer-id',
      'data-page',
      'data-item-action',
      'data-edit-answer',
      'data-answer-rating',
      'data-answer-choice'
    ];
    const focusedAttributes = stableAttributes.filter(name => active?.hasAttribute(name));
    const attributeSelectors = focusedAttributes.map(
      name => `[${name}="${CSS.escape(active.getAttribute(name))}"]`
    );
    const focusAttributes = attributeSelectors.join('');
    let focusSelectorValue;
    if (focusAttributes) {
      focusSelectorValue = `${active.tagName.toLowerCase()}${focusAttributes}${active.matches('input[type=radio],input[type=checkbox]') ? `[value="${CSS.escape(active.value)}"]` : ''}`;
    } else {
      focusSelectorValue = null;
    }
    const focusSelector = focusSelectorValue;
    let selectionValue;
    if (typeof active?.selectionStart === 'number') {
      selectionValue = [active.selectionStart, active.selectionEnd];
    } else {
      selectionValue = null;
    }
    const selection = selectionValue;
    const scroll = [...document.querySelectorAll('*')]
      .filter(element => element.scrollTop || element.scrollLeft)
      .map(element => [element, element.scrollTop, element.scrollLeft]);
    const pageScroll = [window.scrollX, window.scrollY];
    translate();
    if (changed) {
      window.dispatchEvent(new CustomEvent('hospital:localechange', { detail: { locale } }));
    }
    for (const [node, fields] of bindings) {
      if (!node.isConnected && node !== document) {
        bindings.delete(node);
        continue;
      }
      for (const [name, getter] of fields) {
        binding(node, name, getter);
      }
    }
    document.querySelectorAll('[data-language-select], #settings-language').forEach(select => {
      select.value = selectLocaleValue(select);
      select.lang = document.documentElement.lang;
    });
    if (active && !active.isConnected) {
      let scopeValue;
      if (scopeId) {
        scopeValue = document.getElementById(scopeId);
      } else {
        scopeValue = document;
      }
      const scope = scopeValue;
      let replacement;
      if (activeId) {
        replacement = document.getElementById(activeId);
      } else {
        if (focusSelector) {
          replacement = scope?.querySelector(focusSelector);
        } else {
          replacement = null;
        }
      }
      replacement?.focus({ preventScroll: true });
      if (
        selection &&
        replacement?.setSelectionRange &&
        !['number', 'date', 'time', 'datetime-local'].includes(replacement.type)
      ) {
        replacement.setSelectionRange(...selection);
      }
    }
    scroll.forEach(([element, top, left]) => {
      if (element.isConnected) {
        element.scrollTop = top;
        element.scrollLeft = left;
      }
    });
    window.scrollTo(...pageScroll);
    return locale;
  }
  function accountLocale(value) {
    confirmedLocale = normalize(value);
    if (!publicPage) {
      apply(pendingLocale?.locale || confirmedLocale);
    }
  }
  // Identity of the pending choice prevents older queued acknowledgements from replacing it.
  function preview(value) {
    pendingLocale = { locale: normalize(value) };
    apply(pendingLocale.locale);
    return pendingLocale;
  }
  function settle(value) {
    if (pendingLocale === value) {
      pendingLocale = null;
      apply(confirmedLocale);
    }
  }
  function setPublicLocale(value) {
    const url = new URL(location.href);
    const next = normalize(value);
    if (next === 'en') {
      url.searchParams.set('lang', 'en');
    } else {
      url.searchParams.delete('lang');
    }
    history.replaceState(history.state, '', url);
    apply(next);
  }
  function validationMessage(input) {
    const validity = input.validity;
    if (validity.customError) {
      return input.validationMessage;
    }
    let key;
    if (validity.valueMissing) {
      key = 'validation.required';
    } else {
      if (validity.typeMismatch) {
        key = 'validation.type';
      } else {
        if (validity.tooShort) {
          key = 'validation.too_short';
        } else {
          if (validity.tooLong) {
            key = 'validation.too_long';
          } else {
            if (validity.rangeUnderflow || validity.rangeOverflow) {
              key = 'validation.range';
            } else {
              if (validity.stepMismatch) {
                key = 'validation.step';
              } else {
                key = 'validation.format';
              }
            }
          }
        }
      }
    }
    return t(key, { min: input.minLength, max: input.maxLength, minimum: input.min, maximum: input.max });
  }
  const ready = Promise.all(
    ['es', 'en'].map(async language => {
      try {
        const response = await fetch(new URL(`api/v1/translations.php?lang=${language}`, root), {
          credentials: 'same-origin',
          cache: 'no-cache'
        });
        if (!response.ok) {
          throw new Error('I18N_ASSET_UNAVAILABLE');
        }
        const dictionary = await response.json();
        if (!dictionary || typeof dictionary !== 'object' || Array.isArray(dictionary)) {
          throw new Error('I18N_ASSET_INVALID');
        }
        dictionaries[language] = dictionary;
      } catch (_) {
        console.error('[Hospital i18n]', { code: 'I18N_ASSET_UNAVAILABLE', language });
        reveal();
      }
    })
  ).then(() => {
    readyDone = true;
    apply(locale);
  });
  window.HospitalI18n = Object.freeze({
    ready,
    t,
    mark,
    escape,
    resolve: value => {
      if (typeof value === "function") {
        return value();
      } else {
        return value;
      }
    },
    category: item => {
      if (dictionaries.es["catalog.document_categories." + item?.code] === item?.name) {
        return t("catalog.document_categories." + item.code);
      } else {
        return item?.name || "";
      }
    },
    role: code => t("account_role_code." + code) || code,
    html: (key, params) => escape(t(key, params)),
    translate,
    apply,
    normalize,
    get locale() {
      return locale;
    },
    accountLocale,
    preview,
    settle,
    setPublicLocale,
    validationMessage,
    bindText: (node, getter) => binding(node, 'textContent', getter),
    bindAttr: (node, name, getter) => binding(node, name, getter),
    bindValidity: (node, getter) => binding(node, '$validity', getter),
    bindTitle: getter => binding(document, 'title', getter),
    setHTML(node, html) {
      node.removeAttribute?.('data-i18n');
      bindings.delete(node);
      node.innerHTML = html;
      translate(node);
      return html;
    },
    option(getter, value, defaultSelected = false, selected = false) {
      let optionValue;
      if (value === undefined) {
        optionValue = new Option();
      } else {
        optionValue = new Option('', value, defaultSelected, selected);
      }
      const option = optionValue;
      binding(option, 'textContent', getter);
      return option;
    },
    textNode(getter) {
      const node = document.createTextNode('');
      binding(node, 'textContent', getter);
      return node;
    },
    date(value, options = {}) {
      return new Intl.DateTimeFormat(locale, { timeZone: 'America/Montevideo', ...options }).format(
        value instanceof Date ? value : new Date(value)
      );
    },
    number(value, options = {}) {
      return new Intl.NumberFormat(locale, options).format(value);
    }
  });
  const observer = new MutationObserver(records => {
    for (const record of records) {
      for (const node of record.addedNodes) {
        if (node.nodeType === Node.ELEMENT_NODE) {
          translate(node);
        }
      }
      for (const node of record.removedNodes) {
        if (!node.isConnected) {
          for (const bound of bindings.keys()) {
            if (bound === node || node.contains?.(bound)) {
              bindings.delete(bound);
            }
          }
        }
      }
    }
  });
  observer.observe(document.documentElement, { childList: true, subtree: true });
  document.addEventListener('DOMContentLoaded', () => {
    ready.then(() => translate());
  });
})();
