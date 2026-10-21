(() => {
  'use strict';
  const controls = new Map(), suggestions = new Map();
  let opened = null, serial = 0;

  function focusChoice(choice, menu) {
    if (!choice) return;
    choice.focus({preventScroll: true});
    if (choice.offsetTop < menu.scrollTop) menu.scrollTop = choice.offsetTop;
    else if (choice.offsetTop + choice.offsetHeight > menu.scrollTop + menu.clientHeight)
      menu.scrollTop = choice.offsetTop + choice.offsetHeight - menu.clientHeight;
  }
  function close() {
    if (!opened) return;
    opened.menu.hidden = true;
    opened.button.setAttribute('aria-expanded', 'false');
    opened = null;
  }
  function sync(item) {
    if (item.suggestions) {syncSuggestions(item); return;}
    const {select, button, label, menu} = item;
    const options = [...select.options];
    const selected = select.selectedOptions[0];
    const text = item.labelText ? item.labelText(select.value) : selected?.textContent || select.getAttribute('aria-label') || '';
    if (label.textContent !== text) label.textContent = text;
    button.disabled = select.disabled;
    button.setAttribute('aria-required', String(select.required));
    const signature = options.map(o => [o.value, o.textContent, o.disabled, o.hidden].join('\0')).join('\u0001');
    if (item.signature !== signature) {
      item.signature = signature;
      menu.replaceChildren(...options.filter(o => !o.hidden && !(item.hideEmpty && !o.value)).map(option => {
        const choice = document.createElement('button');
        choice.type = 'button'; choice.dataset.value = option.value;
        if (item.choiceAttribute) choice.dataset[item.choiceAttribute] = option.value;
        choice.textContent = option.textContent; choice.disabled = option.disabled;
        choice.setAttribute('role', 'option');
        return choice;
      }));
    }
    for (const choice of menu.children) {
      const active = choice.dataset.value === select.value;
      choice.disabled = select.disabled || options.find(option => option.value === choice.dataset.value)?.disabled;
      choice.setAttribute('aria-selected', String(active));
      choice.setAttribute('aria-pressed', String(active));
    }
    if (select.disabled && opened === item) close();
  }
  function open(item) {
    if (item.select.disabled) return;
    close(); sync(item);
    // The menu lives in the dialog's top layer, or the document, outside scroll clips.
    const host = item.select.closest('dialog') || document.body;
    if (item.menu.parentElement !== host) host.append(item.menu);
    item.menu.style.position = 'fixed';
    item.menu.style.margin = '0';
    item.menu.hidden = false;
    const rect = item.button.getBoundingClientRect();
    item.anchor = {x: rect.left, y: rect.top};
    const roomBelow = innerHeight - rect.bottom - 14, roomAbove = rect.top - 14;
    const above = roomBelow < Math.min(250, item.menu.scrollHeight) && roomAbove > roomBelow;
    Object.assign(item.menu.style, {
      position: 'fixed', minWidth: rect.width + 'px', width: Math.min(innerWidth - 24, Math.max(rect.width, 190)) + 'px',
      maxWidth: 'calc(100vw - 24px)', maxHeight: Math.max(40, above ? roomAbove : roomBelow) + 'px',
      left: Math.max(12, Math.min(rect.left, innerWidth - Math.max(rect.width, 190) - 12)) + 'px',
      top: above ? 'auto' : rect.bottom + 7 + 'px',
      bottom: above ? innerHeight - rect.top + 7 + 'px' : 'auto',
    });
    item.menu.hidden = false; item.button.setAttribute('aria-expanded', 'true'); opened = item;
  }
  function typeChoice(item, event) {
    if (event.key.length !== 1 || !event.key.trim() || event.ctrlKey || event.metaKey || event.altKey) return false;
    event.preventDefault();
    item.search = (Date.now() - (item.typedAt || 0) < 700 ? item.search || '' : '') + event.key.toLocaleLowerCase();
    item.typedAt = Date.now(); if (opened !== item) open(item);
    focusChoice([...item.menu.children].find(choice => !choice.disabled && choice.textContent.toLocaleLowerCase().startsWith(item.search)), item.menu);
    return true;
  }
  function enhance(select, options = {}) {
    if (controls.has(select)) return controls.get(select);
    // These existing backed menus already use the shared design and their own IDs.
    if (select.multiple || (select.hidden && !options.button) || select.closest('.trace-select')) return null;
    const computed = getComputedStyle(select);
    const style = {height: computed.height, font: computed.font, letterSpacing: computed.letterSpacing};
    const adopted = Boolean(options.button);
    const fieldRoot = !adopted && select.parentElement.matches('.field-control,.dialog-select,.specialty-filter,.sort-control');
    const root = options.root || (fieldRoot ? select.parentElement : document.createElement('span'));
    if (!fieldRoot && !adopted) {select.before(root); root.append(select);}
    root.classList.add('document-filter');
    if (!adopted) root.classList.add('hospital-select');
    if (fieldRoot) root.classList.add('hospital-field-select');
    else if (root.parentElement.matches('.builder-select')) root.parentElement.classList.add('hospital-select-container');
    if (!adopted) {select.classList.add('hospital-native-select'); select.tabIndex = -1; select.setAttribute('aria-hidden', 'true');}
    const button = options.button || document.createElement('button'); button.type = 'button';
    if (!adopted) button.className = 'filter-trigger hospital-select-trigger';
    button.id ||= (select.id || 'hospital-select-' + ++serial) + '-button';
    const label = options.label || document.createElement('span');
    if (!adopted) label.className = 'hospital-select-label';
    const arrow = document.createElement('span'); arrow.className = 'filter-chevron'; arrow.setAttribute('aria-hidden', 'true');
    const menu = options.menu || document.createElement('div'); menu.classList.add('filter-menu', 'hospital-select-menu');
    menu.id ||= button.id + '-menu'; menu.hidden = true; menu.setAttribute('role', 'listbox');
    button.setAttribute('aria-controls', menu.id); button.setAttribute('aria-expanded', 'false'); button.setAttribute('aria-haspopup', 'listbox');
    const field = select.labels[0];
    const fieldLabel = field?.contains(select) ? [...field.children].find(node => node !== root && (node.matches('i18n-text,[data-i18n],span'))) : field;
    if (fieldLabel && !adopted) {
      fieldLabel.id ||= button.id + '-field'; label.id = button.id + '-label';
      button.setAttribute('aria-labelledby', fieldLabel.id + ' ' + label.id); menu.setAttribute('aria-labelledby', fieldLabel.id);
    } else if (!adopted) {
      const name = select.getAttribute('aria-label') || field?.childNodes[0]?.textContent.trim() || select.name || '';
      button.setAttribute('aria-label', name); menu.setAttribute('aria-label', name);
    }
    // Keep the native element in layout/form validation; only its painting is replaced.
    if (!adopted && !fieldRoot && style.height && style.height !== 'auto') select.style.height = style.height;
    if (!adopted) {button.style.font = style.font; button.style.letterSpacing = style.letterSpacing;}
    if (fieldRoot) {
      const icon = [...root.children].find(node => node.matches('img,.status-dot'));
      if (icon) button.append(icon.cloneNode(true));
    }
    if (!adopted) {button.append(label, arrow); root.append(button, menu);}
    select.dataset.hospitalButton = button.id;
    const item = {select, root, button, label, menu, ...options}; controls.set(select, item); sync(item);
    button.addEventListener('click', event => {event.preventDefault(); opened === item ? close() : open(item);});
    button.addEventListener('keydown', event => {
      if (typeChoice(item, event)) return;
      if (!['ArrowDown', 'ArrowUp', 'Home', 'End', 'Escape'].includes(event.key)) return;
      event.preventDefault();
      if (event.key === 'Escape') {close(); return;}
      open(item);
      const choices = [...menu.children].filter(c => !c.disabled);
      focusChoice(event.key === 'Home' ? choices[0] : event.key === 'End' ? choices.at(-1) : choices.find(c => c.dataset.value === select.value) || (event.key === 'ArrowUp' ? choices.at(-1) : choices[0]), menu);
    });
    menu.addEventListener('click', event => {
      const choice = event.target.closest('button'); if (!choice || choice.disabled || select.disabled) return;
      const changed = select.value !== choice.dataset.value; select.value = choice.dataset.value;
      close(); sync(item); button.focus();
      if (changed) {select.dispatchEvent(new Event('input', {bubbles: true})); select.dispatchEvent(new Event('change', {bubbles: true}));}
    });
    menu.addEventListener('keydown', event => {
      const choices = [...menu.children].filter(c => !c.disabled);
      if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
        event.preventDefault(); const index = choices.indexOf(document.activeElement);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? choices.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + choices.length) % choices.length;
        focusChoice(choices[next], menu);
      } else if (event.key === 'Escape') {event.preventDefault(); event.stopPropagation(); close(); button.focus();}
      else if (event.key === 'Tab') close();
      else typeChoice(item, event);
    });
    select.addEventListener('invalid', () => button.focus());
    if (!adopted) {
      select.addEventListener('click', event => event.preventDefault());
      select.addEventListener('focus', () => button.focus({preventScroll: true}));
    }
    select.addEventListener('change', () => sync(item));
    select.form?.addEventListener('reset', () => queueMicrotask(() => sync(item)));
    return item;
  }
  function syncSuggestions(item) {
    const input = item.select, query = input.value.trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const list = document.getElementById(item.suggestions);
    const choices = [...(list?.options || [])].filter(option => !option.disabled && option.value &&
      (option.value + ' ' + option.label).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().includes(query));
    const signature = choices.map(option => option.value + '\0' + option.label).join('\u0001');
    if (signature === item.signature) return;
    item.signature = signature; item.index = -1; input.removeAttribute('aria-activedescendant');
    item.menu.replaceChildren(...choices.map((option, index) => {
      const choice = document.createElement('button'); choice.type = 'button'; choice.tabIndex = -1;
      choice.id = item.menu.id + '-' + index; choice.dataset.value = option.value;
      choice.textContent = option.label && option.label !== option.value ? option.value + ' · ' + option.label : option.value;
      choice.setAttribute('role', 'option'); choice.setAttribute('aria-selected', 'false'); return choice;
    }));
  }
  function enhanceSuggestions(input) {
    if (suggestions.has(input)) return;
    const list = input.getAttribute('list'); if (!list) return;
    input.dataset.hospitalList = list; input.removeAttribute('list');
    const menu = document.createElement('div'); menu.className = 'filter-menu hospital-select-menu';
    menu.id = (input.id || 'hospital-suggestions-' + ++serial) + '-menu'; menu.hidden = true; menu.setAttribute('role', 'listbox');
    input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', menu.id); input.setAttribute('aria-expanded', 'false');
    const item = {select: input, button: input, root: input.parentElement, menu, suggestions: list, index: -1};
    suggestions.set(input, item);
    const show = () => {
      syncSuggestions(item);
      if (!input.disabled && !input.readOnly && menu.children.length && item.dismissedValue !== input.value) open(item);
      else if (opened === item) close();
    };
    const choose = choice => {
      input.value = choice.dataset.value; close(); input.removeAttribute('aria-activedescendant');
      input.dispatchEvent(new Event('input', {bubbles: true})); input.dispatchEvent(new Event('change', {bubbles: true}));
      item.dismissedValue = input.value; if (opened === item) close();
    };
    input.addEventListener('focus', () => {item.dismissedValue = null; show();});
    input.addEventListener('input', () => {item.dismissedValue = null; show();});
    input.addEventListener('keydown', event => {
      if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
        show(); const choices = [...menu.children]; if (!choices.length) return;
        event.preventDefault(); item.index = (item.index + (event.key === 'ArrowDown' ? 1 : -1) + choices.length) % choices.length;
        choices.forEach((choice, index) => choice.setAttribute('aria-selected', String(index === item.index)));
        input.setAttribute('aria-activedescendant', choices[item.index].id); const choice = choices[item.index];
        if (choice.offsetTop < menu.scrollTop) menu.scrollTop = choice.offsetTop;
        else if (choice.offsetTop + choice.offsetHeight > menu.scrollTop + menu.clientHeight) menu.scrollTop = choice.offsetTop + choice.offsetHeight - menu.clientHeight;
      } else if (event.key === 'Enter' && opened === item && item.index >= 0) {
        event.preventDefault(); choose(menu.children[item.index]);
      } else if (event.key === 'Escape' && opened === item) {
        event.preventDefault(); event.stopPropagation(); item.dismissedValue = input.value; close();
      } else if (event.key === 'Tab') close();
    });
    menu.addEventListener('pointerdown', event => event.preventDefault());
    menu.addEventListener('click', event => {const choice = event.target.closest('button'); if (choice && !input.disabled) choose(choice);});
    item.refresh = () => {if (document.activeElement === input) show();};
  }
  function refresh() {
    document.querySelectorAll('select').forEach(select => enhance(select));
    document.querySelectorAll('input[list]').forEach(enhanceSuggestions);
    for (const [input, item] of suggestions) {
      if (!input.isConnected) {item.menu.remove(); suggestions.delete(input);} else item.refresh();
    }
    for (const [select, item] of controls) {
      if (!select.isConnected) {item.menu.remove(); controls.delete(select); continue;}
      sync(item);
    }
  }
  document.addEventListener('click', event => {if (opened && !opened.root.contains(event.target) && !opened.menu.contains(event.target)) close();});
  document.addEventListener('focusin', event => {if (opened && !opened.root.contains(event.target) && !opened.menu.contains(event.target)) close();});
  document.addEventListener('scroll', event => {
    if (!opened || opened.menu.contains(event.target)) return;
    const rect = opened.button.getBoundingClientRect();
    // Ignore an already-queued scroll that occurred before this menu opened.
    if (Math.abs(rect.left - opened.anchor.x) > 1 || Math.abs(rect.top - opened.anchor.y) > 1) close();
  }, true);
  window.addEventListener('resize', close);
  window.addEventListener('hospital:localechange', () => queueMicrotask(refresh));
  const observer = new MutationObserver(changes => {
    if (changes.some(change => change.target.closest?.('select') || change.target.closest?.('datalist') || (change.type === 'attributes' && change.attributeName === 'open' && change.target instanceof HTMLDialogElement) ||
        [...change.addedNodes].some(node => node.nodeType === 1 && (node.matches('select,input[list],datalist') || node.querySelector('select,input[list],datalist'))))) refresh();
  });
  function start() {refresh(); observer.observe(document.body, {subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['disabled', 'selected', 'required', 'open', 'hidden', 'label']});}
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true}); else start();
  window.HospitalDropdown = Object.freeze({enhance, refresh, closeAll: close});
})();
