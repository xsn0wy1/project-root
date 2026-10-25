/* Shared page utilities for text, accessible menus and request notifications.
 * API: listens for shared request failures; does not send requests.
 * DOM: staff-navigation, profile-menu, api-notification and existing page toasts.
 */
(() => {
  'use strict';
  const escapeHtml = value =>
    String(value ?? '').replace(
      /[&<>"']/g,
      character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]
    );
  const normalize = value => {
    const normalizedText = String(value ?? '').normalize('NFD');
    const replacedText = normalizedText.replace(/[\u0300-\u036f]/g, '');
    const lowercaseText = replacedText.toLocaleLowerCase('es');
    return lowercaseText.trim();
  };
  const focusable = root =>
    [
      ...root.querySelectorAll(
        'button:not(:disabled),a[href],input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex="0"]'
      )
    ].filter(
      node =>
        !node.closest('[inert]') &&
        node.getClientRects().length &&
        getComputedStyle(node).visibility !== 'hidden'
    );

  let notification;
  let notificationKey;
  let notificationTimer;
  const toastSelector = '.toast, .ambulance-toast, .patient-toast, .doctor-toast, .route-toast';
  const messageSelector = '[role="alert"], .form-message, .form-feedback, .form-error, ' +
    '#loginMessage, #registerMessage, #recovery-status, #view-message, #documentActionStatus, ' +
    toastSelector;

  function visibleMessages() {
    const messages = new Map();
    for (const element of document.querySelectorAll(messageSelector)) {
      const text = element.textContent.trim();
      if (!text || !element.checkVisibility() || element.closest('.visually-hidden, [aria-hidden="true"]')) {
        continue;
      }
      if (element === notification && text === window.HospitalI18n.t(notificationKey)) {
        continue;
      }
      messages.set(element, text);
    }
    return messages;
  }

  window.addEventListener('hospital:requesterror', event => {
    const previousMessages = visibleMessages();
    // The page's catch handler can display its own message before this fallback runs.
    setTimeout(() => {
      for (const [element, text] of visibleMessages()) {
        if (element.matches('[role="alert"], .error, .is-error') || previousMessages.get(element) !== text) {
          clearTimeout(notificationTimer);
          if (notification && notification !== element) {
            notification.hidden = true;
          }
          return;
        }
      }
      const parent = document.querySelector('dialog[open]') || document.body;
      let existingToast;
      for (const element of parent.querySelectorAll(toastSelector)) {
        if (element.parentElement.checkVisibility() && (element.hidden || element === notification)) {
          existingToast = element;
          break;
        }
      }
      const previousNotification = notification;
      if (existingToast) {
        notification = existingToast;
      } else {
        notification = document.getElementById('api-notification');
        if (!notification) {
          notification = document.createElement('div');
          notification.id = 'api-notification';
          notification.className = 'api-notification';
          notification.setAttribute('role', 'status');
          notification.setAttribute('aria-live', 'polite');
        }
        parent.append(notification);
      }
      if (previousNotification && previousNotification !== notification) {
        previousNotification.hidden = true;
      }
      notificationKey = 'api_client.no_se_pudo_completar_la_operacion';
      if (event.detail.code === 'NETWORK_ERROR') {
        notificationKey = 'api_client.no_se_pudo_conectar_con_el_servidor_revisa_la_conexion_e_intenta_nuevamente';
      }
      window.HospitalI18n.bindText(notification, () => window.HospitalI18n.t(notificationKey));
      notification.hidden = false;
      clearTimeout(notificationTimer);
      notificationTimer = setTimeout(() => {
        notification.hidden = true;
      }, 8000);
    }, 0);
  });

  function bindNavigation(options) {
    const shell = options.shell;
    const button = options.button;
    let sidebar = options.sidebar;
    if (sidebar === undefined) {
      sidebar = button?.closest('.sidebar');
    }
    let nav = options.nav;
    if (nav === undefined) {
      nav = sidebar?.querySelector('.sidebar-nav');
    }
    if (!shell || !button || !nav || button.dataset.navigationBound) {
      return;
    }
    button.dataset.navigationBound = 'true';
    const phone = matchMedia(
      '(max-width: 770px), (max-width: 1000px) and (max-height: 500px) and (pointer: coarse)'
    );
    const backdrop = document.createElement('div');
    backdrop.className = 'mobile-nav-backdrop';
    backdrop.hidden = true;
    backdrop.setAttribute('aria-hidden', 'true');
    document.body.append(backdrop);
    nav.id ||= 'staff-navigation';
    button.setAttribute('aria-controls', nav.id);
    const suspended = new Map();
    const restore = () => {
      for (const [node, inert] of suspended) {
        node.inert = inert;
      }
      suspended.clear();
    };
    function setOpen(open, { returnFocus = false } = {}) {
      restore();
      shell.classList.toggle('menu-open', open);
      button.setAttribute('aria-expanded', String(open));
      nav.inert = !open;
      nav.setAttribute('aria-hidden', String(!open));
      window.HospitalI18n?.bindAttr(button, 'aria-label', () =>
        window.HospitalI18n.t(open ? 'trace_map.cerrar_menu' : 'main_menu.abrir_menu')
      );
      const modal = open && phone.matches;
      document.body.classList.toggle('mobile-menu-open', modal);
      backdrop.hidden = !modal;
      if (modal) {
        window.HospitalDropdown?.closeAll();
        sidebar.setAttribute('role', 'dialog');
        sidebar.setAttribute('aria-modal', 'true');
        window.HospitalI18n?.bindAttr(sidebar, 'aria-label', () =>
          window.HospitalI18n.t('trace_map.navegacion_principal')
        );
        for (let branch = sidebar; branch.parentElement; branch = branch.parentElement) {
          for (const sibling of branch.parentElement.children) {
            if (sibling === branch || sibling === backdrop || /^(SCRIPT|STYLE|LINK)$/.test(sibling.tagName)) {
              continue;
            }
            suspended.set(sibling, sibling.inert);
            sibling.inert = true;
          }
          if (branch.parentElement === document.body) {
            break;
          }
        }
        focusable(nav)[0]?.focus({ preventScroll: true });
      } else {
        sidebar.removeAttribute('role');
        sidebar.removeAttribute('aria-modal');
        if (returnFocus) {
          button.focus({ preventScroll: true });
        }
      }
      shell.dispatchEvent(new CustomEvent('hospital:navigation', { detail: { open } }));
    }
    button.addEventListener('click', () =>
      setOpen(!shell.classList.contains('menu-open'), { returnFocus: shell.classList.contains('menu-open') })
    );
    backdrop.addEventListener('click', () => setOpen(false, { returnFocus: true }));
    nav.addEventListener('click', event => {
      const link = event.target.closest('a');
      if (phone.matches && link) {
        setOpen(false, { returnFocus: link.getAttribute('href') === '#' });
      }
    });
    document.addEventListener('pointerdown', event => {
      if (phone.matches && shell.classList.contains('menu-open') && !sidebar.contains(event.target)) {
        setOpen(false, { returnFocus: true });
      }
    });
    document.addEventListener('keydown', event => {
      if (event.defaultPrevented || !shell.classList.contains('menu-open')) {
        return;
      }
      if (event.key === 'Escape') {
        event.preventDefault();
        setOpen(false, { returnFocus: true });
      } else if (phone.matches && event.key === 'Tab') {
        const controls = focusable(sidebar);
        const first = controls[0];
        const last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first?.focus();
        }
      }
    });
    phone.addEventListener('change', () => setOpen(false));
    setOpen(shell.classList.contains('menu-open'));
    return { close: () => setOpen(false) };
  }

  function bindProfileMenu(root) {
    const button = root?.querySelector('.profile-button,.user-control');
    const menu = root?.querySelector('.profile-menu');
    if (!button || !menu || button.dataset.profileBound) {
      return;
    }
    button.dataset.profileBound = 'true';
    menu.id ||= 'profile-menu';
    button.setAttribute('aria-haspopup', 'menu');
    button.setAttribute('aria-controls', menu.id);
    menu.setAttribute('role', 'menu');
    menu.querySelectorAll('button,a').forEach(item => item.setAttribute('role', 'menuitem'));
    function setOpen(open, returnFocus = false) {
      root.classList.toggle('profile-active', open);
      menu.inert = !open;
      menu.setAttribute('aria-hidden', String(!open));
      button.setAttribute('aria-expanded', String(open));
      if (returnFocus) {
        button.focus({ preventScroll: true });
      }
    }
    button.addEventListener('click', () => setOpen(!root.classList.contains('profile-active')));
    button.addEventListener('keydown', event => {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        setOpen(true);
        const items = focusable(menu);
        (event.key === 'ArrowDown' ? items[0] : items.at(-1))?.focus();
      }
    });
    menu.addEventListener('click', event => {
      if (event.target.closest('button,a')) {
        setOpen(false);
      }
    });
    root.addEventListener('keydown', event => {
      if (!root.classList.contains('profile-active')) {
        return;
      }
      if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        setOpen(false, true);
      } else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key) && menu.contains(event.target)) {
        event.preventDefault();
        const items = focusable(menu);
        const index = items.indexOf(document.activeElement);
        let next;
        if (event.key === 'Home') {
          next = 0;
        } else {
          if (event.key === 'End') {
            next = items.length - 1;
          } else {
            next = (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
          }
        }
        items[next]?.focus();
      }
    });
    document.addEventListener('pointerdown', event => {
      if (!root.contains(event.target)) {
        setOpen(false);
      }
    });
    document.addEventListener('focusin', event => {
      if (!root.contains(event.target)) {
        setOpen(false);
      }
    });
    setOpen(false);
  }

  function detailRows(root, fields) {
    root.replaceChildren(
      ...fields.map(([key, value]) => {
        const row = document.createElement('div');
        row.className = 'catalog-detail-field';
        const term = document.createElement('dt');
        const description = document.createElement('dd');
        window.HospitalI18n.bindText(term, () => window.HospitalI18n.t(key));
        window.HospitalI18n.bindText(description, typeof value === 'function' ? value : () => value);
        row.append(term, description);
        return row;
      })
    );
  }

  // Card labels follow the same translated headings as the desktop tables.
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.personnel-table,.destinations-table').forEach(table => {
      function labelRows() {
        const headings = [...table.tHead.rows[0].cells].map(cell => cell.textContent.trim());
        for (const row of table.tBodies[0].rows) {
          [...row.cells].forEach((cell, index) => {
            cell.dataset.label = headings[index];
          });
        }
      }
      new MutationObserver(labelRows).observe(table.tBodies[0], { childList: true });
      window.addEventListener('hospital:localechange', () => queueMicrotask(labelRows));
      labelRows();
    });
  });
  window.HospitalUI = Object.freeze({ escapeHtml, normalize, bindNavigation, bindProfileMenu, detailRows });
})();
