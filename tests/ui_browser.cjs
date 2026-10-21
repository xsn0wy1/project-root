/* UI regression checks using the existing Playwright and isolated fictional fixtures. */
'use strict';
const fs = require('fs'), path = require('path'), assert = require('assert/strict');
const {chromium} = require('playwright');
const root = path.join(__dirname, '..');
const fixture = process.env.HOSPITAL_UI_TEST_DIR || path.join(__dirname, '.runtime/stage5/browser');
const env = JSON.parse(fs.readFileSync(path.join(fixture, 'env.json')));
const records = JSON.parse(fs.readFileSync(path.join(fixture, 'records.json')));
assert(env.HOSPITAL_DB_NAME.endsWith('_test'), 'Only disposable fictional fixtures are allowed.');
const base = process.env.HOSPITAL_UI_URL || env.HOSPITAL_AUTH_TEST_URL;
const output = process.env.HOSPITAL_UI_OUTPUT || path.join(__dirname, '.runtime/ui/verified');
fs.mkdirSync(output, {recursive: true});
const report = {checks: 0, pages: [], interactions: [], errors: [], assetErrors: []};
const rendered = {};
const check = (value, label) => {assert(value, label); report.checks++;};
const ids = {'documents-edit.php': records.document.id, 'documents-viewer.php': records.document.id,
  'trace-view.php': records.transfer.id, 'survey-view.php': records.survey.id, 'survey-new.php': records.survey.id};
const files = fs.readdirSync(path.join(root, 'public'), {recursive: true}).filter(file => file.endsWith('.php')).sort()
  .filter(file => !process.env.HOSPITAL_UI_FILES || process.env.HOSPITAL_UI_FILES.split(',').some(name => file.endsWith(name)));
const sizes = {desktop: {width: 1440, height: 900}, mobile: {width: 390, height: 844},
  narrow: {width: 320, height: 640}, tablet: {width: 768, height: 1024},
  landscape: {width: 844, height: 390}, tabletWide: {width: 1024, height: 768},
  widePhone: {width: 650, height: 900}, compact: {width: 800, height: 900}, medium: {width: 1000, height: 900}, smallDesktop: {width: 1200, height: 900}};
const matrix = (process.env.HOSPITAL_UI_SIZES || 'desktop,mobile').split(',');
const journeys = (process.env.HOSPITAL_UI_JOURNEYS || 'settings,map,surveys,dialogs').split(',');
async function ready(page) {
  await page.evaluate(async () => {
    await HospitalI18n.ready; await window.HospitalAuth?.ready;
    await Promise.race([document.fonts.ready, new Promise(resolve => setTimeout(resolve, 2000))]);
  });
  if (await page.locator('#documentForm,#newTransferForm').count()) {
    await page.waitForFunction(() => [...document.querySelectorAll('#documentForm,#newTransferForm')].every(form => form.getAttribute('aria-busy') === 'false'));
  }
  await page.waitForTimeout(180);
}
async function visit(page, file, id) {
  report.phase = file + ' at ' + page.viewportSize().width + 'px';
  await page.goto(base + '/' + file + (id ? '?id=' + id : ''), {waitUntil: 'domcontentloaded'}); await ready(page);
}
async function searchNavigation(page) {
  await visit(page, 'src/views/main-menu.php');
  await page.locator('.search-action').click();
  for (const [locale, queries] of [['es-UY', ['calendario', 'reportes']], ['en', ['calendar', 'reports']]]) {
    await page.evaluate(locale => HospitalI18n.preview(locale), locale);
    for (const query of queries) {
      await page.locator('.search-input').fill(query);
      check(await page.locator('#search-results-list .search-result').count() === 0, locale + ' search omits cancelled feature: ' + query);
    }
  }
  await page.locator('.search-input').fill('patients');
  await page.locator('#search-results-list a[href$="management/patients.php"]').click();
  await page.waitForURL('**/management/patients.php'); await ready(page);
  check(await page.locator('.patients-page').count() === 1, 'navigation search opens the guarded Patients page');
  report.interactions.push('English/Spanish navigation search omits cancelled features and opens an implemented management page.');
}
async function api(page, route, body) {
  return page.evaluate(({route, body}) => body === undefined ? HospitalApi.get(route) : HospitalApi.post(route, body), {route, body});
}
async function choose(page, selector, value) {
  const id = await page.locator(selector).getAttribute('data-hospital-button');
  await page.locator('#' + id).click();
  await page.locator('#' + await page.locator('#' + id).getAttribute('aria-controls')).locator(`[data-value="${value}"]`).click();
}
async function within(page, locator, label) {
  await locator.waitFor({state: 'visible'});
  const box = await locator.boundingBox(), view = page.viewportSize();
  check(box && box.x >= -1 && box.y >= -1 && box.x + box.width <= view.width + 1 && box.y + box.height <= view.height + 1, label + ' fits the viewport');
}
async function overflow(page) {
  return page.evaluate(() => [...document.querySelectorAll('body *')]
    .filter(el => !el.closest('.leaflet-pane,.leaflet-control-container,svg,.sidebar,.document-viewport,.visually-hidden') &&
      !(el.closest('thead') && getComputedStyle(el.closest('thead')).clipPath !== 'none') && el.checkVisibility({checkOpacity: true, checkVisibilityCSS: true}))
    .map(el => {const box = el.getBoundingClientRect(); return {element: el.id || (typeof el.className === 'string' ? el.className : el.tagName), x: box.x, right: box.right, width: box.width};})
    .filter(box => box.width > 0 && (box.x < -1 || box.right > innerWidth + 1)).slice(0, 12));
}
async function managementFit(page, label) {
  const issues = await page.evaluate(() => {
    const issues = [], box = node => node.getBoundingClientRect();
    const visible = node => node.checkVisibility({checkOpacity: true, checkVisibilityCSS: true});
    for (const card of document.querySelectorAll('.metric-card,.doctor-metric,.management-card,.ambulance-row,.patient-row,.doctor-table tr,.status-filters button,.doctor-status-filters button,.role-tabs button,.toolbar-button,.primary-button,.new-ambulance-button')) {
      if (!visible(card)) continue;
      const outer = box(card), textNodes = document.createTreeWalker(card, NodeFilter.SHOW_TEXT);
      for (let text; text = textNodes.nextNode();) {
        if (!text.textContent.trim() || !visible(text.parentElement)) continue;
        let clipped = false;
        for (let node = text.parentElement; node !== card; node = node.parentElement) {
          if (['hidden', 'clip'].includes(getComputedStyle(node).overflowX)) {clipped = true; break;}
        }
        if (clipped) continue;
        const range = document.createRange(); range.selectNodeContents(text);
        for (const rect of range.getClientRects()) if (rect.width && rect.height &&
          (rect.x < outer.x - 1 || rect.right > outer.right + 1 || rect.y < outer.y - 1 || rect.bottom > outer.bottom + 1)) {
          issues.push({card: card.className, text: text.textContent.trim()});
        }
      }
    }
    for (const row of document.querySelectorAll('.directory-controls,.doctor-controls,.toolbar-left,.toolbar-right,.catalog-filters,.accounts-toolbar,.panel-heading,.directory-heading,.ambulance-list,.patient-list,.doctor-table tbody')) {
      const children = [...row.children].filter(node => visible(node) && box(node).width && box(node).height);
      for (let i = 0; i < children.length; i++) for (let j = i + 1; j < children.length; j++) {
        const a = box(children[i]), b = box(children[j]);
        if (Math.min(a.right, b.right) - Math.max(a.x, b.x) > 2 && Math.min(a.bottom, b.bottom) - Math.max(a.y, b.y) > 2) {
          issues.push({row: row.className, first: children[i].className, second: children[j].className});
        }
      }
    }
    return issues;
  });
  check(!issues.length, label + ' cards and controls do not overlap: ' + JSON.stringify(issues));
}
async function routeOutline(page, label) {
  const button = page.locator('#route-sort-button'); if (!await button.count()) return;
  for (const state of ['idle', 'hover', 'focus']) {
    if (state === 'hover') await button.hover();
    if (state === 'focus') await button.focus();
    await page.waitForTimeout(250);
    const border = await button.evaluate(node => ({outer: getComputedStyle(node.parentElement).borderTopColor, inner: getComputedStyle(node).borderTopWidth}));
    check(border.outer === 'rgba(0, 0, 0, 0)' && border.inner === '1px', label + ' sorting has one border in ' + state + ': ' + JSON.stringify(border));
  }
  await page.screenshot({path: path.join(output, label.replace(/[\/\s]/g, '_') + '-sort.png'), animations: 'disabled'});
  await page.locator('.topbar').click({position: {x: 60, y: 10}});
}
async function navigation(page, label) {
  const button = page.locator('.sidebar-action'); if (!await button.count()) return;
  const frame = page.locator('.topbar,.content-card,.hero-panel,.doctors-content,.surveys-layout,.routes-layout').first();
  const before = await frame.boundingBox(); await button.click(); await page.waitForTimeout(650);
  check(await button.getAttribute('aria-expanded') === 'true', label + ' drawer opens');
  await within(page, page.locator('.sidebar'), label + ' drawer');
  const after = await frame.boundingBox();
  check(Math.abs(before.x - after.x) < 1 && Math.abs(before.width - after.width) < 1, label + ' drawer does not squeeze content');
  check(await page.locator('.sidebar').getAttribute('aria-modal') === 'true', label + ' drawer is modal');
  if (/main-menu.php|trace-map.php/.test(label)) await page.screenshot({path: path.join(output, label.replace(/[\/\s]/g, '_') + '-drawer.png'), animations: 'disabled'});
  await button.focus(); await page.keyboard.press('Shift+Tab');
  check(await page.evaluate(() => Boolean(document.activeElement.closest('.sidebar-nav'))), label + ' focus stays inside drawer');
  await page.keyboard.press('Escape');
  check(await button.getAttribute('aria-expanded') === 'false' && await button.evaluate(node => node === document.activeElement), label + ' Escape closes and restores focus');
  await button.click(); await page.mouse.click(page.viewportSize().width - 2, 120);
  check(await button.getAttribute('aria-expanded') === 'false', label + ' outside click closes drawer');
  const current = page.locator('.sidebar-nav a[href="#"]').first();
  if (await current.count()) {
    await button.click(); await current.click();
    check(await button.getAttribute('aria-expanded') === 'false' && await button.evaluate(node => node === document.activeElement), label + ' current-section navigation closes and restores focus');
  }
}
async function phoneHeadings(page) {
  const header = await page.evaluate(() => {
    const brand = document.querySelector('.topbar:not(.search-active) .brand');
    if (!brand) return null;
    const icon = brand.querySelector('img').getBoundingClientRect(), text = brand.querySelector('span').getBoundingClientRect();
    const search = document.querySelector('.topbar .search-action').getBoundingClientRect();
    return {iconRight: icon.right, textLeft: text.left, textRight: text.right, searchLeft: search.left};
  });
  if (header) check(header.iconRight <= header.textLeft + 1 && header.textRight <= header.searchLeft + 1, 'header branding stays clear of its icon and search: ' + JSON.stringify(header));
  if (!await page.evaluate(() => matchMedia('(max-width:770px), (max-width:1000px) and (max-height:500px) and (pointer:coarse)').matches)) return;
  if (await page.locator('.surveys-heading').count()) {
    const title = await page.locator('.surveys-heading h1').boundingBox(), actions = await page.locator('.surveys-heading .heading-actions').boundingBox();
    check(title.y + title.height <= actions.y + 1, 'phone survey heading has a separate readable row');
  }
  const heights = await page.locator('.trace-heading .heading-actions .action-button,.transfer-heading .header-actions .detail-button').evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect().height));
  if (heights.length) check(heights.every(height => height >= 43.9 && height <= 80.1), 'phone transfer actions have comfortable compact heights: ' + JSON.stringify(heights));
}
async function settingsEffects(page) {
  const original = await page.evaluate(() => ({theme: document.documentElement.dataset.theme, locale: document.documentElement.lang}));
  const style = node => {const css = getComputedStyle(node); return {border: css.borderColor, background: css.backgroundColor, shadow: css.boxShadow};};
  for (const theme of ['dark', 'light']) {
    await page.evaluate(async theme => {HospitalTheme.preview(theme); await HospitalI18n.preview(theme === 'light' ? 'en' : 'es-UY');}, theme);
    await page.locator('.specialty-add').click();
    check(await page.locator('#specialty-dialog').evaluate(node => getComputedStyle(node).animationName !== 'none'), theme + ' specialty dialog animates on opening');
    await page.locator('#specialty-title').click(); await page.mouse.move(0, 0); await page.waitForTimeout(250);
    const input = page.locator('#specialty-name'), idle = await input.evaluate(style);
    await input.hover(); await page.waitForTimeout(250);
    const hover = await input.evaluate(style);
    check(hover.border !== idle.border || hover.background !== idle.background, theme + ' specialty field has hover feedback');
    await input.focus(); await page.waitForTimeout(250);
    check((await input.evaluate(style)).shadow !== 'none', theme + ' specialty field has themed focus feedback');
    const option = page.locator('#specialty-options option').first();
    if (await option.count()) {
      const value = await option.getAttribute('value');
      await input.fill(value.slice(0, 2)); await input.press('ArrowDown');
      const menu = page.locator('#' + await input.getAttribute('aria-controls'));
      await within(page, menu, theme + ' specialty suggestions');
      const fieldBox = await input.boundingBox(), menuBox = await menu.boundingBox();
      check(menuBox.y >= fieldBox.y + fieldBox.height + 3 || menuBox.y + menuBox.height <= fieldBox.y - 3, theme + ' suggestions stay outside the animated field');
      await menu.locator('button').first().press('Enter');
      check(await input.inputValue() === value, theme + ' specialty suggestions preserve keyboard selection');
      await input.fill('');
    }
    await input.press('Tab');
    const cancel = page.locator('#specialty-form .cancel-button');
    check(await cancel.evaluate(node => node === document.activeElement && getComputedStyle(node).outlineStyle !== 'none'), theme + ' dialog buttons show keyboard focus');
    await page.screenshot({path: path.join(output, 'settings-add-' + page.viewportSize().width + '-' + theme + '.png'), animations: 'disabled'});
    await cancel.click();
    await page.locator('.user-control').click();
    const notifications = page.locator('[data-security-notifications]');
    await notifications.hover();
    await page.screenshot({path: path.join(output, 'settings-profile-' + page.viewportSize().width + '-' + theme + '.png'), animations: 'disabled'});
    await notifications.click(); await within(page, page.locator('#account-dialog'), theme + ' notifications dialog');
    const close = page.locator('#account-dialog .cancel-button');
    await page.mouse.move(0, 0); await page.waitForTimeout(250); const normal = await close.evaluate(style);
    await close.hover(); await page.waitForTimeout(250); const active = await close.evaluate(style);
    check(normal.background !== active.background && active.shadow !== 'none', theme + ' notifications button has hover feedback');
    await page.screenshot({path: path.join(output, 'settings-notifications-' + page.viewportSize().width + '-' + theme + '.png'), animations: 'disabled'});
    await close.click();
  }
  await page.evaluate(async original => {HospitalTheme.preview(original.theme); await HospitalI18n.preview(original.locale);}, original);
}
async function settings(page) {
  await visit(page, 'src/views/settings.php');
  await settingsEffects(page);
  const saved = await api(page, 'settings.php?action=get'), original = saved.settings.specialties.map(item => item.name);
  try {
    await page.locator('.user-control').click();
    check(await page.locator('.settings-profile-wrapper').evaluate(node => node.classList.contains('profile-active')), 'profile opens a small dropdown');
    check(!await page.locator('#account-dialog').isVisible(), 'profile does not open notifications directly');
    await within(page, page.locator('#settings-profile-menu'), 'profile menu');
    await page.keyboard.press('Escape');
    check(await page.locator('.user-control').evaluate(node => node === document.activeElement), 'profile Escape restores focus');
    await page.locator('.user-control').press('ArrowDown');
    check(await page.locator('[data-security-notifications]').evaluate(node => node === document.activeElement), 'profile keyboard navigation');
    await page.locator('[data-security-notifications]').click(); await page.locator('#account-dialog').waitFor();
    await within(page, page.locator('#account-dialog'), 'notifications dialog'); await page.keyboard.press('Escape');
    if (original.length >= 10) {
      const payload = await api(page, 'settings.php?action=profile-update', {version: saved.settings.version, specialties: original.slice(0, 9)});
      await page.evaluate(payload => HospitalAuth.settingsChanged(payload), payload);
    }
    const name = 'Especialidad UI Ficticia ' + Date.now();
    await page.locator('.specialty-add').click(); await page.locator('#specialty-name').fill(name);
    const route = base + '/api/v1/settings.php?action=profile-update';
    await page.route(route, r => r.fulfill({status: 503, contentType: 'application/json', body: '{"success":false,"error":{"code":"SERVICE_UNAVAILABLE","message":"Fictional offline test"}}'}));
    let response = page.waitForResponse(r => r.url() === route); await page.locator('#specialty-form [type=submit]').click(); await response;
    await page.waitForFunction(() => !document.querySelector('#specialty-form [type=submit]').disabled);
    check(await page.locator('#specialty-name').inputValue() === name && await page.locator('#specialty-dialog').isVisible(), 'failed specialty addition retains text and dialog');
    await page.unroute(route); response = page.waitForResponse(r => r.url() === route);
    await page.locator('#specialty-form [type=submit]').click(); await response; await page.locator('#specialty-dialog').waitFor({state: 'hidden'});
    const chip = page.locator('.specialty-chip[data-specialty-id]').filter({hasText: name});
    check(await chip.count() === 1 && !(await chip.textContent()).includes('×'), 'specialty persists with no delete cross');
    await chip.click(); await within(page, page.locator('#specialty-remove-dialog'), 'specialty confirmation');
    check((await page.locator('#specialty-remove-description').textContent()).includes(name), 'confirmation identifies the specialty');
    await page.locator('#specialty-remove-form [data-close-dialog]').first().click();
    check(await chip.count() === 1, 'cancel keeps the specialty');
    await chip.click(); await page.route(route, r => r.fulfill({status: 503, contentType: 'application/json', body: '{"success":false,"error":{"code":"SERVICE_UNAVAILABLE","message":"Fictional offline test"}}'}));
    response = page.waitForResponse(r => r.url() === route); await page.locator('#specialty-remove-form [type=submit]').click(); await response;
    await page.waitForFunction(() => !document.querySelector('#specialty-remove-form [type=submit]').disabled);
    check(await chip.count() === 1 && await page.locator('#specialty-remove-dialog').isVisible(), 'failed removal retains specialty and confirmation');
    await page.unroute(route); await page.locator('#specialty-remove-form [type=submit]').click(); await page.locator('#specialty-remove-dialog').waitFor({state: 'hidden'});
    check(await chip.count() === 0 && !(await api(page, 'settings.php?action=get')).settings.specialties.some(item => item.name === name), 'confirmed removal persists through the real API');
    await page.locator('#privacy-tab').click(); await page.evaluate(() => HospitalTheme.preview('light'));
    await page.waitForTimeout(300);
    const colors = await page.locator('.privacy-panel .toggle').evaluateAll(nodes => nodes.map(node => ({on: node.classList.contains('is-on'), knob: getComputedStyle(node.querySelector('.toggle-knob')).backgroundColor, track: getComputedStyle(node, '::before').backgroundColor})));
    check(colors.every(color => !['rgb(246, 231, 236)', 'rgb(228, 243, 235)', 'rgb(255, 255, 255)'].includes(color.knob)), 'light switches use visible colors');
    await page.screenshot({path: path.join(output, 'settings-switches.png')});
    report.interactions.push('Settings profile, notifications, specialty addition/cancellation/removal, failed saves and visible light switches.');
  } finally {
    await page.unroute(base + '/api/v1/settings.php?action=profile-update');
    const now = await api(page, 'settings.php?action=get');
    await api(page, 'settings.php?action=profile-update', {version: now.settings.version, specialties: original});
  }
}
async function mapAndControls(page) {
  const tiles = '**/basemaps.cartocdn.com/**';
  await page.route(tiles, route => route.fulfill({status: 200, contentType: 'image/png', body: fs.readFileSync(path.join(root, 'public/assets/Icons/pfp-default.png'))}));
  await visit(page, 'src/views/traces/trace-map.php');
  await page.waitForFunction(() => Boolean(window.TraceMap));
  check(!(await page.locator('.leaflet-control-attribution').textContent()).includes('<i18n-text'), 'map attribution displays credits without escaped markup');
  for (let round = 0; round < 3; round++) {
    await page.locator('#search-toggle').click(); await page.locator('#transfer-search').fill('Ficticio');
    await page.locator('#filter-toggle').click();
    check(await page.locator('#transfer-search').isHidden() && await page.locator('#search-toggle').getAttribute('aria-expanded') === 'false', 'filters collapse search and clear its active state');
    await within(page, page.locator('#filter-panel'), 'map filters');
    check(await page.locator('#transfer-search').inputValue() === 'Ficticio', 'filter switching retains query');
    await page.locator('#search-toggle').click();
    check(await page.locator('#filter-panel').isHidden() && await page.locator('#transfer-search').inputValue() === 'Ficticio', 'search closes filters and restores text');
    await page.locator('#layers-toggle').click();
    check(await page.locator('#transfer-search').isHidden(), 'layers collapse search');
    await within(page, page.locator('#layers-panel'), 'map layers'); await page.keyboard.press('Escape');
  }
  await page.locator('#search-toggle').press('/');
  check(await page.locator('#transfer-search').evaluate(node => node === document.activeElement), 'slash shortcut focuses search');
  await page.keyboard.press('Escape'); check(await page.locator('#transfer-search').isHidden(), 'Escape closes search');
  await page.locator('#filter-toggle').click(); await page.locator('#reset-filters').click();
  check(await page.locator('#transfer-search').inputValue() === '', 'explicit reset clears search');
  await page.screenshot({path: path.join(output, 'map-filters.png')});
  await page.unroute(tiles);
  await visit(page, 'src/views/traces/trace-new.php');
  await page.waitForFunction(() => document.querySelector('#newTransferForm').getAttribute('aria-busy') === 'false');
  check(await page.locator('#patientSex').isDisabled() && await page.locator('#patientSex').evaluate(node => getComputedStyle(node).opacity) === '0', 'patient sex has one painted disabled control');
  check(await page.locator('#patientSex-button').count() === 1 && await page.locator('#patientSex-button').isDisabled(), 'patient sex has exactly one disabled trigger');
  await visit(page, 'src/views/management/routes.php');
  check(await page.locator('#route-sort').evaluate(node => getComputedStyle(node.parentElement, '::after').display === 'none'), 'route sorting has one chevron');
  await choose(page, '#route-sort', 'name'); check(await page.locator('#route-sort').inputValue() === 'name', 'route sorting keeps its authoritative field');
  report.interactions.push('Map search/filter/layer coordination, keyboard shortcuts, preserved query, explicit reset and single dropdown controls.');
}
async function dialogs(page) {
  for (const [file, opener, dialog] of [
    ['management/ambulances.php','#new-ambulance','#ambulance-dialog'],
    ['management/patients.php','#new-patient-button','#patient-dialog'],
    ['management/doctors.php','#new-doctor-button','#doctor-dialog'],
    ['management/routes.php','#new-route','#route-dialog']
  ]) {
    await visit(page, 'src/views/' + file); await page.locator(opener).click();
    await within(page, page.locator(dialog), file + ' form');
    check(!(await overflow(page)).length, file + ' open form has no horizontal overflow');
    const submit = page.locator(dialog + ' [type=submit]'); await submit.scrollIntoViewIfNeeded();
    await within(page, submit, file + ' save action');
    if (file === 'management/doctors.php' && page.viewportSize().width <= 770) {
      const fields = await page.locator('#doctor-form .personal-fields > label').evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect().width));
      check(Math.max(...fields) - Math.min(...fields) < 2, 'doctor fields stack at equal full widths');
    }
    await page.keyboard.press('Escape'); check(!await page.locator(dialog).isVisible(), file + ' form closes');
  }
  await visit(page, 'src/views/documents/documents-viewer.php', records.document.id);
  await page.waitForFunction(() => !document.querySelector('#qr-button').disabled);
  await page.locator('#share-button').click(); await within(page, page.locator('#share-dialog'), 'staff document share'); await page.keyboard.press('Escape');
  await page.locator('#qr-button').click(); await page.locator('#qr-dialog').waitFor();
  await within(page, page.locator('#qr-dialog'), 'patient document QR');
  check(!(await overflow(page)).length, 'document QR dialog has no horizontal overflow');
  await page.locator('#copy-qr-link').scrollIntoViewIfNeeded(); await within(page, page.locator('#copy-qr-link'), 'QR copy action');
  await page.screenshot({path: path.join(output, 'document-qr-' + page.viewportSize().width + '.png')}); await page.keyboard.press('Escape');
  await visit(page, 'src/views/traces/trace.php');
  await phoneHeadings(page);
  await page.locator('[data-filter=all]').click();
  await page.waitForFunction(() => document.querySelector('#transferList').getAttribute('aria-busy') === 'false');
  const issues = await overflow(page); check(!issues.length, 'complete transfer list has no horizontal overflow: ' + JSON.stringify(issues));
  report.interactions.push('Management editors, reachable save actions, full-width doctor fields, document share/QR and the complete transfer list.');
}
async function surveys(page) {
  await visit(page, 'src/views/surveys/survey.php');
  await phoneHeadings(page);
  const published = await page.evaluate(async () => (await HospitalSurveys.directory()).find(survey => survey.lifecycle === 'published'));
  check(Boolean(published), 'published fictional survey fixture is available');
  check(await page.locator('.survey-detail [data-transition="close"]').count() === 0, 'directory detail has no Close survey action');
  await visit(page, 'src/views/surveys/survey-view.php', published.id);
  await page.locator('#survey-transition').waitFor({state: 'visible'});
  check(await page.locator('#survey-transition').isVisible() && await page.locator('#survey-transition').getAttribute('data-action') === 'close', 'full details retains Close survey');
  report.interactions.push('Survey closing is available only in full details.');
}
(async () => {
  const browser = await chromium.launch({channel: 'chrome', headless: true});
  const staff = await browser.newContext({hasTouch: true}), anon = await browser.newContext({hasTouch: true});
  let page;
  try {
    for (const context of [staff, anon]) {
      context.setDefaultTimeout(12000); context.setDefaultNavigationTimeout(20000);
      await context.route(/settings\.php\?action=diagnostics/, route => route.fulfill({status: 200, contentType: 'application/json', body: '{"success":true,"data":{"recorded":true}}'}));
      context.on('page', page => {
        page.on('pageerror', error => report.errors.push(error.message));
        page.on('response', response => {
          const url = new URL(response.url());
          if (url.origin === new URL(base).origin && response.status() >= 400 &&
              /\/(src|assets)\//.test(url.pathname) && /\.(css|js|mjs|wasm|svg|png|jpe?g|ttf|woff2?)$/.test(url.pathname)) {
            report.assetErrors.push(response.status() + ' ' + url.pathname);
          }
        });
        page.on('dialog', async dialog => {report.errors.push('Unexpected native dialog: ' + dialog.type()); await dialog.dismiss();});
      });
    }
    page = await staff.newPage(); await visit(page, 'index.php');
    await api(page, 'auth.php?action=login', {username: 'system_admin', password: env.HOSPITAL_ADMIN_PASSWORD}); await page.close();
    for (const file of files) {
      const publicPage = file === 'index.php' || file.includes('register-restore/') || file.endsWith('survey-user.php');
      page = await (publicPage ? anon : staff).newPage();
      for (const size of matrix) {
        await page.setViewportSize(sizes[size]);
        const id = ids[path.basename(file)];
        await page.goto(base + '/' + file + (id ? '?id=' + id : '') + (file.endsWith('survey-user.php') ? '#token=' + records.token : ''), {waitUntil: 'domcontentloaded'}); await ready(page);
        if (!rendered['public/' + file]) {
          const response = await page.context().request.get(base + '/' + file + (id ? '?id=' + id : ''));
          check(response.status() === 200, file + ' renders through its existing access guard');
          rendered['public/' + file] = await response.text();
        }
        for (const theme of ['dark', 'light']) {
          await page.evaluate(async theme => {HospitalTheme.preview(theme); await HospitalI18n.preview(theme === 'light' ? 'en' : 'es-UY');}, theme);
          await page.waitForTimeout(220);
          const label = file + ' ' + size + ' ' + theme, issues = await overflow(page);
          check(!issues.length, label + ' horizontal overflow: ' + JSON.stringify(issues));
          if (file.includes('/management/')) await managementFit(page, label);
          await routeOutline(page, label);
          const topbar = page.locator('.topbar');
          if (size === 'desktop' && await topbar.count()) check(Math.abs((await topbar.boundingBox()).height - 100) < 1, label + ' desktop header stays 100px');
          await phoneHeadings(page);
          await page.screenshot({path: path.join(output, file.replaceAll('/', '_') + '-' + size + '-' + theme + '.png'), animations: 'disabled'});
          const phone = await page.evaluate(() => matchMedia('(max-width:770px), (max-width:1000px) and (max-height:500px) and (pointer:coarse)').matches);
          if (phone) await navigation(page, label);
          else if (file.includes('/management/')) {
            await page.locator('.sidebar-action').click(); await page.waitForTimeout(650);
            await managementFit(page, label + ' with navigation open');
            await phoneHeadings(page);
            check(!(await overflow(page)).length, label + ' open navigation stays within the viewport');
            await page.screenshot({path: path.join(output, label.replace(/[\/\s]/g, '_') + '-navigation.png'), animations: 'disabled'});
            await page.locator('.sidebar-action').click(); await page.waitForTimeout(650);
          }
          report.pages.push({file, size, theme, locale: theme === 'light' ? 'en' : 'es-UY'});
        }
      }
      await page.close(); console.log('PASS responsive UI:', file);
    }
    if (!process.env.HOSPITAL_UI_LAYOUT_ONLY) {
      page = await staff.newPage();
      await searchNavigation(page);
      for (const viewport of [sizes.desktop, sizes.mobile]) {
        await page.setViewportSize(viewport);
        if (journeys.includes('settings')) await settings(page);
        if (journeys.includes('map')) await mapAndControls(page);
        if (journeys.includes('surveys')) await surveys(page);
        if (journeys.includes('dialogs')) await dialogs(page);
        console.log('PASS requested UI interactions:', viewport.width);
      }
      if (journeys.includes('dialogs')) {
        await page.setViewportSize(sizes.narrow); await dialogs(page);
        console.log('PASS narrow-phone forms, share and QR dialogs.');
      }
      if (journeys.includes('settings')) {
        await visit(page, 'src/views/settings.php'); await page.locator('.user-control').click();
        await page.locator('#settings-profile-menu [data-logout]').click();
        await page.waitForURL(url => url.pathname === new URL(base + '/index.php').pathname || url.pathname === new URL(base + '/').pathname);
        check(!await page.locator('.user-control').count(), 'profile menu logs out through the existing authentication flow');
      }
    }
    check(report.errors.length === 0, 'no unhandled script errors: ' + report.errors.join('; '));
    check(report.assetErrors.length === 0, 'all local page assets load: ' + report.assetErrors.join('; '));
  } catch (error) {
    if (page && !page.isClosed()) {
      await page.screenshot({path: path.join(output, 'failure.png')}).catch(() => {});
      report.failure = await page.evaluate(() => ({pathname: location.pathname, title: document.title,
        readyState: document.readyState, bodyClass: document.body.className,
        qrDisabled: document.querySelector('#qr-button')?.disabled,
        styles: [...document.styleSheets].map(sheet => {let rules; try {rules = sheet.cssRules.length;} catch (_) {} return {pathname: sheet.href ? new URL(sheet.href).pathname : null, rules};})})).catch(() => ({}));
      console.error('UI failure during ' + report.phase + ': ' + report.failure.pathname);
    }
    throw error;
  } finally {
    fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify(report, null, 2)); await browser.close();
    fs.writeFileSync(path.join(output, 'rendered-pages.json'), JSON.stringify(rendered));
  }
  console.log('TOTAL', report.checks, 'checks;', report.pages.length, 'page/theme/viewport comparisons;', report.interactions.length, 'interaction groups.');
})().catch(error => {console.error(String(error.stack || error).replace(/[#?]token=[^\s]+/g, '#token=[private]')); process.exitCode = 1;});
