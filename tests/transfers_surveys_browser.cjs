/* SQL-backed transfer and survey journeys using the existing fictional browser fixtures. */
'use strict';
const fs = require('fs'), path = require('path'), assert = require('assert');
const {spawn} = require('child_process');
const {once} = require('events');
const {chromium} = require('playwright');
const root = path.join(__dirname, '..');
const runtime = process.env.HOSPITAL_WORKFLOW_TEST_DIR || path.join(__dirname, '.runtime/stage5/browser');
const env = JSON.parse(fs.readFileSync(path.join(runtime, 'env.json')));
Object.assign(process.env, env);
assert(env.HOSPITAL_DB_NAME.endsWith('_test'), 'Use disposable fictional data.');
const rootBase = env.HOSPITAL_AUTH_TEST_URL;
const mountedBase = process.env.HOSPITAL_WORKFLOW_MOUNT_URL || 'http://127.0.0.1:8102/hospital';
let checks = 0;
const check = (ok, label) => { assert(ok, label); checks++; console.log('PASS', label); };
const endpoint = (domain, action) => `${domain}.php?action=${action}`;
async function ready(page) {
  await page.waitForFunction(() => Boolean(window.HospitalI18n));
  await page.evaluate(async () => { await HospitalI18n.ready; if (window.HospitalAuth) await HospitalAuth.ready; });
}
async function api(page, route, body) {
  return page.evaluate(({route, body}) => body === undefined ? HospitalApi.get(route) : HospitalApi.post(route, body), {route, body});
}
async function choose(page, selector, value) {
  const button = await page.locator(selector).evaluate(el => el.dataset.hospitalButton);
  await page.locator('#' + button).click();
  await page.locator(`[role=option][data-value="${value}"]:visible`).click();
}
async function signIn(browser, base) {
  const context = await browser.newContext();
  context.setDefaultTimeout(12000); context.setDefaultNavigationTimeout(20000);
  try {
    const page = await context.newPage(); await page.goto(base + '/'); await ready(page);
    await api(page, endpoint('auth', 'login'), {username: 'system_admin', password: env.HOSPITAL_ADMIN_PASSWORD});
    await page.goto(base + '/src/views/main-menu.php'); await ready(page);
    return await context.storageState();
  } finally { await context.close(); }
}
async function transferJourney(browser, context, page, base) {
  await page.goto(base + '/src/views/traces/trace-new.php'); await ready(page);
  await page.waitForFunction(() => document.getElementById('newTransferForm').getAttribute('aria-busy') === 'false');
  const catalog = await api(page, endpoint('transfers', 'catalogs'));
  const driver = catalog.crew.find(item => item.role_code === 'driver');
  const route = catalog.routes.find(item => item.geometry.length >= 2);
  const schedule = await page.evaluate(() => TraceApi.localParts(Date.now() + 6 * 3600000));
  await choose(page, '#ambulance', catalog.ambulances.at(-1).id);
  await page.locator('#driver').fill(`${driver.full_name} · #${driver.id}`);
  await page.locator('#startDate').fill(schedule.date); await page.locator('#startTime').fill(schedule.time);
  await choose(page, '#transferType', 'equipment'); await choose(page, '#reason', 'insumos');
  await choose(page, '#priority', 'high');
  await page.locator('#origin').fill('Fictional typed origin'); await page.locator('#destination').fill('Fictional typed destination');
  await page.locator('#notes').fill('Retained transfer notes');
  await page.locator('#saveDraft').click();
  await page.waitForFunction(() => document.getElementById('newTransferForm').getAttribute('aria-busy') === 'false');
  const draft = await api(page, endpoint('transfers', 'draft'));
  check(draft.payload.origin === 'Fictional typed origin' && draft.payload.notes === 'Retained transfer notes', 'transfer draft persists typed locations and notes');
  await page.reload(); await ready(page);
  await page.waitForFunction(() => document.getElementById('newTransferForm').getAttribute('aria-busy') === 'false');
  check(await page.locator('#origin').inputValue() === draft.payload.origin && await page.locator('#notes').inputValue() === draft.payload.notes, 'transfer draft restores after reload');
  await choose(page, '#routeTemplate', route.id);
  check(await page.locator('#origin').evaluate(el => el.readOnly) && await page.locator('#routeVersion').inputValue() === String(route.version), 'route selection fills immutable endpoints and selected revision');
  const failure = /\/transfers\.php\?action=create$/;
  await page.route(failure, handler => handler.fulfill({status: 503, contentType: 'application/json', body: JSON.stringify({success: false, error: {code: 'SERVICE_UNAVAILABLE', message: 'Focused transfer failure'}})}));
  await page.locator('[type=submit]').click();
  await page.waitForFunction(() => document.getElementById('newTransferForm').getAttribute('aria-busy') === 'false');
  check(await page.locator('#notes').inputValue() === draft.payload.notes && await page.locator('#routeTemplate').inputValue() === route.id && await page.locator('#formMessage').textContent() === '', 'failed transfer save retains route and notes with console-only errors');
  check(await page.locator('#patientName').isDisabled() && await page.locator('#patientSex').isDisabled(), 'equipment transfer retains disabled patient controls after failure');
  await page.unroute(failure);
  await page.locator('[type=submit]').click(); await page.waitForURL('**/trace-view.php?id=*'); await ready(page);
  await page.waitForFunction(() => Boolean(document.getElementById('transfer-detail').dataset.transferId));
  const id = new URL(page.url()).searchParams.get('id');
  const item = await api(page, endpoint('transfers', 'get') + '&id=' + id);
  check(item.status === 'scheduled' && item.notes === draft.payload.notes && item.route_template_id === route.id, 'transfer retry creates the actual selected route and metadata');
  check((await api(page, endpoint('transfers', 'draft'))).payload.notes === undefined, 'successful creation clears the acknowledged SQL draft');
  await page.locator('#edit-transfer').click(); await page.locator('#edit-dialog[open]').waitFor();
  await page.locator('#edit-form [name=notes]').fill('Persisted transfer edit');
  await page.locator('#edit-form [type=submit]').click(); await page.locator('#edit-dialog').waitFor({state: 'hidden'});
  const edited = await api(page, endpoint('transfers', 'get') + '&id=' + id);
  check(edited.notes === 'Persisted transfer edit' && JSON.stringify(edited.geometry) === JSON.stringify(item.geometry), 'transfer edit retains its route snapshot');
  await page.locator('#update-status').click(); await page.locator('#status-form [type=submit]').click();
  await page.locator('#status-dialog').waitFor({state: 'hidden'});
  check((await api(page, endpoint('transfers', 'get') + '&id=' + id)).status === 'in_progress', 'status dialog starts the booked transfer');
  await page.goto(base + '/src/views/traces/trace-map.php?transfer=' + id); await ready(page);
  await page.waitForFunction(id => window.TraceMap?.transfers.some(item => item.id === id), id);
  check(await page.locator(`[data-transfer-id="${id}"]`).count() > 0, 'map displays the persisted transfer');
  const observer = await browser.newContext({storageState: await signIn(browser, base)});
  observer.setDefaultTimeout(15000); observer.setDefaultNavigationTimeout(20000);
  let worker;
  try {
    const second = await observer.newPage(); await second.goto(base + '/src/views/traces/trace-map.php?transfer=' + id); await ready(second);
    const state = await api(page, endpoint('tracking', 'control'));
    await api(page, endpoint('tracking', 'control'), {command: 'start', version: state.version, speed: 100});
    worker = spawn(env.HOSPITAL_TEST_PHP || '/Applications/XAMPP/xamppfiles/bin/php', ['backend/php/cli/simulate-transfers.php', 'worker', '20'], {cwd: root, env: process.env, stdio: 'ignore'});
    for (const target of [page, second]) await target.waitForFunction(id => window.TraceMap?.transfers.find(item => item.id === id)?.lifecycle === 'arrived', id, {timeout: 25000});
    const positions = await Promise.all([page, second].map(target => target.evaluate(id => {
      const item = TraceMap.transfers.find(item => item.id === id); return [item.positionId, item.currentPosition, item.sampledAt];
    }, id)));
    check(JSON.stringify(positions[0]) === JSON.stringify(positions[1]), 'independent staff browsers display the same server-generated position and timestamp');
    const stateNow = await api(page, endpoint('tracking', 'control'));
    await api(page, endpoint('tracking', 'control'), {command: 'stop', version: stateNow.version});
  } finally {
    if (worker && worker.exitCode === null) { worker.kill(); await once(worker, 'exit'); }
    await observer.close();
  }
  await page.goto(base + '/src/views/traces/trace-view.php?id=' + id); await ready(page);
  await page.locator('#update-status').waitFor({state: 'visible'}); await page.waitForFunction(() => !document.getElementById('update-status').disabled);
  await page.locator('#update-status').click(); await page.locator('#status-form [type=submit]').click();
  await page.locator('#status-dialog').waitFor({state: 'hidden'});
  check((await api(page, endpoint('transfers', 'get') + '&id=' + id)).status === 'completed' && await page.locator('#edit-transfer').isDisabled(), 'completion retains history and locks assignment editing');
  return id;
}
async function surveyJourney(browser, page, base, label, mobile, errors, dialogs) {
  await page.goto(base + '/src/views/surveys/survey-new.php'); await ready(page);
  await page.waitForFunction(() => !document.getElementById('save-survey').disabled);
  await page.locator('#internal-title').fill('Fictional workflow ' + label);
  await page.locator('[data-info=description]').fill('Private fictional management description');
  await page.locator('.builder-step[data-step="2"]').click();
  await page.locator('#form-title').fill('Fictional participant form');
  await page.locator('.question-type-button[data-add-type=rating]').click(); await page.locator('#question-title').fill('How was the fictional service?');
  await page.locator('.builder-step[data-step="3"]').click();
  if (mobile) await choose(page, '#answer-access', 'individual_link');
  await page.locator('#completion-message').fill('Fictional response received.');
  const failure = /\/surveys\.php\?action=create$/;
  await page.route(failure, handler => handler.fulfill({status: 503, contentType: 'application/json', body: JSON.stringify({success: false, error: {code: 'SERVICE_UNAVAILABLE', message: 'Focused survey failure'}})}));
  await page.locator('#save-survey').click(); await page.waitForFunction(() => !document.getElementById('save-survey').disabled);
  check(await page.locator('#internal-title').inputValue() === 'Fictional workflow ' + label && await page.locator('#form-title').inputValue() === 'Fictional participant form', label + ': survey failure retains internal/public draft text');
  await page.unroute(failure);
  await page.locator('#save-survey').click(); await page.waitForURL('**/survey-new.php?id=*');
  await page.waitForFunction(() => !document.getElementById('save-survey').disabled);
  const id = new URL(page.url()).searchParams.get('id');
  const saved = await api(page, endpoint('surveys', 'get') + '&id=' + id);
  check(saved.questions.length === 1 && saved.questions[0].type === 'rating' && saved.form_title === 'Fictional participant form', label + ': survey authoring persists the actual question and participant form');
  await page.locator('#publish-survey').click(); await page.waitForURL('**/survey-view.php?id=*'); await ready(page);
  await page.waitForFunction(() => document.getElementById('view-question-count').textContent === '1');
  await page.locator('[data-share-survey]').first().click(); await page.locator('#survey-public-url').waitFor();
  if (mobile) await page.locator('#survey-new-invitation').click();
  await page.waitForFunction(() => Boolean(document.getElementById('survey-public-url').value));
  const url = await page.locator('#survey-public-url').inputValue(), target = new URL(url), token = new URLSearchParams(target.hash.slice(1)).get('token');
  check(target.pathname.endsWith('/surveys/user-view/survey-user.php') && token.length === 64 && await page.locator('#survey-public-qr svg').count() === 1, label + ': participant link and QR preserve the current mount and fragment credential');
  const participant = await browser.newContext({viewport: mobile ? {width: 390, height: 844} : {width: 1440, height: 900}});
  participant.setDefaultTimeout(12000); participant.setDefaultNavigationTimeout(20000);
  try {
    const patient = await participant.newPage();
    patient.on('pageerror', error => errors.push(error.message));
    patient.on('dialog', async dialog => { dialogs.push(dialog.type()); await dialog.dismiss(); });
    await patient.goto(url); await ready(patient);
    await patient.locator('[data-answer-rating]').last().check({force: true});
    const oldHash = await patient.evaluate(() => location.hash);
    if (mobile) {
      check(await patient.locator('html').getAttribute('lang') === 'en', label + ': participant URL hydrates English on mobile');
      // The original mobile layout hides this picker; test state preservation through its existing locale action.
      await patient.evaluate(() => HospitalI18n.setPublicLocale('es-UY'));
    } else await choose(patient, '[data-language-select]', 'en');
    check(await patient.locator('[data-answer-rating]:checked').inputValue() === '5' && await patient.evaluate(() => location.hash) === oldHash, label + ': participant language switching retains its answer and signed access');
    check(!(await patient.locator('body').innerText()).includes('Private fictional management description'), label + ': participant interface reveals no internal survey notes');
    await patient.locator('#next-question').click();
    const submitFailure = /\/surveys\.php\?action=public-submit$/;
    await patient.route(submitFailure, handler => handler.fulfill({status: 503, contentType: 'application/json', body: JSON.stringify({success: false, error: {code: 'SERVICE_UNAVAILABLE', message: 'Focused response failure'}})}));
    await patient.locator('#submit-answers').click(); await patient.waitForFunction(() => !document.getElementById('patient-form').inert);
    check(await patient.locator('#submit-answers').isEnabled() && (await patient.locator('#question-stage').innerText()).includes('5'), label + ': failed participant submission retains review answers');
    await patient.unroute(submitFailure);
    await patient.locator('#submit-answers').click(); await patient.getByText('Fictional response received.', {exact: true}).waitFor();
    await page.locator('[data-close-links]').click(); await page.reload(); await ready(page);
    await page.waitForFunction(() => document.getElementById('view-response-count').textContent === '1');
    const result = await api(page, endpoint('surveys', 'get') + '&id=' + id);
    check(result.response_count === 1 && result.average_rating === 5 && result.results[result.questions[0].id].answer_count === 1, label + ': retry commits one response and accurate staff results');
    if (mobile) {
      const used = await participant.request.post(base + '/api/v1/' + endpoint('surveys', 'public-get'), {data: {token}});
      check(used.status() === 409, label + ': individual invitation rejects further participation');
    } else {
      await page.locator('[data-share-survey]').first().click(); await page.locator('#survey-revoke-link').click();
      await page.waitForFunction(old => document.getElementById('survey-public-url').value && document.getElementById('survey-public-url').value !== old, url);
      const revoked = await participant.request.post(base + '/api/v1/' + endpoint('surveys', 'public-get'), {data: {token}});
      check(revoked.status() === 404, label + ': shared-link revocation blocks its previous credential');
      await page.locator('[data-close-links]').click();
    }
  } finally { await participant.close(); }
}
(async () => {
  const browser = await chromium.launch({channel: 'chrome', headless: true});
  const errors = [], dialogs = [];
  try {
    const states = {root: await signIn(browser, rootBase), mounted: await signIn(browser, mountedBase)};
    let transferId;
    for (const [mount, theme, mobile] of [['root', 'dark', false], ['root', 'light', true], ['mounted', 'dark', true], ['mounted', 'light', false]]) {
      const base = mount === 'root' ? rootBase : mountedBase, label = `${mount}-${theme}-${mobile ? 'mobile' : 'desktop'}`;
      const context = await browser.newContext({storageState: states[mount], viewport: mobile ? {width: 390, height: 844} : {width: 1440, height: 900}});
      context.setDefaultTimeout(12000); context.setDefaultNavigationTimeout(20000);
      context.on('page', page => { page.on('pageerror', error => errors.push(error.message)); page.on('dialog', async dialog => { dialogs.push(dialog.type()); await dialog.dismiss(); }); });
      const page = await context.newPage();
      try {
        await page.goto(base + '/src/views/main-menu.php'); await ready(page);
        const current = await api(page, endpoint('settings', 'get'));
        await api(page, endpoint('settings', 'preferences-update'), {version: current.settings.version, locale: mobile ? 'en' : 'es-UY', theme});
        if (!transferId) transferId = await transferJourney(browser, context, page, base);
        else {
          await page.goto(base + '/src/views/traces/trace-view.php?id=' + transferId); await ready(page);
          await page.waitForFunction(() => document.getElementById('transfer-detail').dataset.status === 'completed');
          check(await page.locator('#edit-transfer').isDisabled(), label + ': completed transfer remains read-only after navigation');
        }
        await surveyJourney(browser, page, base, label, mobile, errors, dialogs);
        check(errors.length === 0 && dialogs.length === 0, label + ': no unhandled errors or native error dialogs');
        await page.screenshot({path: path.join(runtime, label + '-results.png')});
        console.log('Completed workflow case:', label);
      } catch (error) { await page.screenshot({path: path.join(runtime, 'workflow-failure.png')}).catch(() => {}); throw error; }
      finally { await context.close(); }
    }
    console.log('TOTAL', checks, 'transfer/survey browser workflow checks');
  } finally { await browser.close(); }
})().catch(error => { console.error(error.stack.replace(/token=[a-f0-9]{64}/g, 'token=[redacted]')); process.exitCode = 1; });
