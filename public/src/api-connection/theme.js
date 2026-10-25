/* Applies cached/account themes and updates shared theme controls.
 * API: none; auth-session supplies account preferences.
 * DOM: html[data-theme], data-theme-choice controls and theme-color meta.
 * current retains its existing live property interface.
 */
(() => {
  'use strict';
  const root = new URL('../../', document.currentScript.src);
  const publicPage =
    document.documentElement.hasAttribute('data-cached-theme') ||
    location.pathname === root.pathname ||
    location.pathname === new URL('index.php', root).pathname ||
    location.pathname.includes('/register-restore/') ||
    location.pathname.endsWith('/surveys/user-view/survey-user.php');
  const cacheKey = `hospital-theme:${root.pathname}`;
  const normalize = value => {
    if (value === 'light') {
      return 'light';
    } else {
      return 'dark';
    }
  };
  let current = normalize(document.documentElement.dataset.theme);
  let confirmed = 'dark';
  let pending = null;
  let account = null;
  try {
    if (publicPage) {
      current = normalize(localStorage.getItem(cacheKey));
    }
  } catch (_) {
    /* Appearance works without browser storage. */
  }
  function remember(value) {
    try {
      localStorage.setItem(cacheKey, value);
    } catch (_) {
      /* Keep the in-memory choice. */
    }
  }
  function controls() {
    document.querySelectorAll('[data-public-theme]').forEach(button => {
      const selected = button.dataset.publicTheme === current;
      button.setAttribute('aria-pressed', String(selected));
      button.classList.toggle('active', selected);
    });
    document.querySelectorAll('meta[name="theme-color"]').forEach(meta => {
      meta.content = current === 'light' ? '#f3f5fa' : '#101732';
    });
  }
  function apply(value) {
    const next = normalize(value);
    const changed = next !== current;
    current = next;
    document.documentElement.dataset.theme = current;
    controls();
    if (changed) {
      window.dispatchEvent(new CustomEvent('hospital:themechange', { detail: { theme: current } }));
    }
    return current;
  }
  function accountTheme(value, userId) {
    if (account !== userId) {
      account = userId;
      pending = null;
    }
    confirmed = normalize(value);
    if (!publicPage) {
      remember(confirmed);
      apply(pending?.theme || confirmed);
    }
  }
  function preview(value) {
    pending = { theme: normalize(value) };
    apply(pending.theme);
    return pending;
  }
  function settle(choice) {
    if (pending === choice) {
      pending = null;
      apply(confirmed);
    }
  }
  function setPublicTheme(value) {
    if (!publicPage) {
      return current;
    }
    const next = apply(value);
    remember(next);
    return next;
  }
  window.HospitalTheme = Object.freeze({
    get current() {
      return current;
    },
    accountTheme,
    preview,
    settle,
    setPublicTheme
  });
  apply(current);
  document.addEventListener('DOMContentLoaded', controls, { once: true });
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-public-theme]');
    if (button && !button.disabled) {
      setPublicTheme(button.dataset.publicTheme);
    }
  });
  window.addEventListener('storage', event => {
    if (publicPage && event.key === cacheKey) {
      apply(event.newValue);
    }
  });
})();
