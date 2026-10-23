"use strict";

/* Run with the disposable environment and records prepared by i18n_integration.py --prepare-browser. */
const fs = require('fs'),
  assert = require('assert');
const firstPaint = require('./i18n_first_paint.cjs');
const {
  chromium
} = require('playwright');
(async () => {
  assert((process.env.HOSPITAL_DB_NAME || '').endsWith('_test'), 'Use a disposable *_test environment.');
  const fixture = {
      env: process.env
    },
    r = JSON.parse(fs.readFileSync(process.env.HOSPITAL_I18N_TEST_RECORDS || 'tests/.runtime/i18n-records.json')),
    base = fixture.env.HOSPITAL_AUTH_TEST_URL;
  const browser = await chromium.launch({
      channel: 'chrome',
      headless: true
    }),
    context = await browser.newContext({
      viewport: {
        width: 1440,
        height: 900
      }
    }),
    p = await context.newPage();
  let errors = [];
  let checks = 0;
  const results = [];
  p.on('pageerror', e => errors.push(e.stack));
  function check(value, label) {
    assert(value, label);
    checks++;
    results.push(label);
    console.log('PASS', label);
  }
  async function ready() {
    await p.evaluate(async () => {
      await HospitalI18n.ready;
      if (window.HospitalAuth) await HospitalAuth.ready;
    });
    await p.waitForTimeout(150);
  }
  async function go(file) {
    await p.goto(base + '/src/views/' + file);
    await ready();
    if (file === 'settings.php') await p.locator('#privacy-tab').click();
  }
  await p.goto(base + '/index.php?lang=en');
  await ready();
  await p.evaluate(async password => {
    await HospitalApi.post('auth.php?action=login', {
      username: 'system_admin',
      password
    });
  }, fixture.env.HOSPITAL_ADMIN_PASSWORD);
  await go('settings.php');
  await p.selectOption('#settings-language', 'en');
  await p.waitForTimeout(250);
  check((await p.locator('h1').innerText()) === 'Settings', 'existing settings selector updates language');
  for (const theme of ['dark', 'light']) {
    await p.evaluate(async theme => {
      const account = await HospitalApi.get('auth.php?action=me');
      await HospitalApi.post('settings.php?action=preferences-update', {version: account.settings.version, theme});
    }, theme);
    for (const [size, viewport] of [['desktop', {width: 1440, height: 900}], ['mobile', {width: 390, height: 844}]]) {
      const paint = await firstPaint.capture(context, base, {theme, viewport, name: theme+'-'+size});
      check(paint.englishAt > 0, theme+' '+size+' English account has no visible Spanish first-paint copy at CPU ×6');
    }
  }
  for (const failure of ['reject', 'stall']) {
    const fallback = await context.newPage(), pending = [];
    await fallback.route('**/api/v1/translations.php?*', route => {
      if (failure === 'reject') return route.fulfill({status: 503, contentType: 'application/json', body: '{}'});
      pending.push(route);
    });
    await fallback.goto(base+'/index.php?lang=en', {waitUntil: 'domcontentloaded'});
    if (failure === 'stall') check(await fallback.locator('body').evaluate(body => getComputedStyle(body).visibility === 'hidden'),
      'English page waits for translation while dictionaries are pending');
    await fallback.waitForFunction(() => document.documentElement.classList.contains('i18n-ready'), null, {timeout: 4000});
    check(await fallback.locator('body').evaluate(body => getComputedStyle(body).visibility === 'visible'),
      'English page is revealed when dictionaries '+failure);
    for (const route of pending) await route.abort();
    await fallback.close();
  }
  const spanishPending = await context.newPage(), spanishRequests = [];
  await spanishPending.route('**/api/v1/translations.php?*', route => {spanishRequests.push(route);});
  await spanishPending.goto(base+'/index.php', {waitUntil: 'domcontentloaded'});
  check(await spanishPending.locator('body').evaluate(body => getComputedStyle(body).visibility === 'visible'),
    'Spanish server text remains visible while dictionaries are pending');
  for (const route of spanishRequests) await route.abort();
  await spanishPending.close();
  for (const file of ['documents/documents.php', 'documents/documents-edit.php?id=' + r.document.id, 'documents/documents-viewer.php?id=' + r.document.id, 'management/patients.php', 'management/doctors.php', 'management/personnel.php', 'management/ambulances.php', 'management/destinations.php', 'management/routes.php', 'traces/trace.php', 'traces/trace-view.php?id=' + r.transfer.id, 'traces/trace-map.php?transfer=' + r.transfer.id, 'surveys/survey.php', 'surveys/survey-new.php?id=' + r.survey.id, 'surveys/survey-view.php?id=' + r.survey.id]) {
    errors = [];
    await go(file);
    await p.waitForTimeout(150);
    await p.evaluate(() => {
      HospitalI18n.apply('es-UY');
      HospitalI18n.apply('en');
    });
    await p.waitForTimeout(80);
    check(!errors.length, file + ' populated switching has no script errors: ' + errors.join('\n'));
    check(!(await p.locator('body').innerText()).includes('<img'), file + ' preserves icon markup');
    if (file.includes('documents-viewer')) {
      check((await p.locator('#document-title').innerText()) === r.document.title, 'document title stays original data and HTML is escaped');
      check((await p.locator('#document-kind').innerText()) === 'Administrative Document', 'fixed document category label translated');
    }
    if (file === 'management/patients.php') check((await p.locator('body').innerText()).includes('Inicio Guardar'), 'record words matching interface labels are never translated');
    if (file === 'management/routes.php') {
      check((await p.locator('body').innerText()).includes('Discharge'), 'route discharge uses context-specific translation');
    }
    if (file.startsWith('traces/trace-map.php')) {
      check(await p.evaluate(() => {
        const theme = HospitalTheme.current;
        HospitalTheme.preview('light');
        const icon = document.querySelector('#filter-toggle .filter-icon');
        const readable = getComputedStyle(icon).backgroundColor === getComputedStyle(icon.parentElement).color;
        HospitalTheme.preview(theme);
        return readable;
      }), 'light map filter icon follows the readable button text color');
    }
  }
  await go('surveys/survey-new.php');
  check(!(await p.locator('.builder-step[data-step="3"]').getAttribute('class')).includes('is-complete') &&
    (await p.locator('.builder-step[data-step="3"] .step-number').innerText()) === '3',
    'empty survey configuration step shows its number instead of a finished icon');
  await p.locator('[data-info=title]').fill('Borrador incompleto');
  check(!(await p.locator('.builder-step[data-step="3"]').getAttribute('class')).includes('is-complete'),
    'survey title alone does not finish the configuration step');
  const forms = [['management/doctors.php', '#doctor-rows [data-action="view"][data-id="' + r.doctor.id + '"]', '#doctor-form'], ['management/personnel.php', '#personnel-rows [data-action="edit"][data-id="' + r.crew.id + '"]', '#personnel-form'], ['management/destinations.php', '#destinations-rows [data-action="edit"][data-id="' + r.destination.id + '"]', '#destinations-form']];
  for (const [file, button, form] of forms) {
    errors = [];
    await go(file);
    await p.locator(button).click();
    if (file === 'management/doctors.php') await p.locator('#edit-from-detail').click();
    await p.locator(form).waitFor({
      state: 'visible'
    });
    await p.waitForFunction(selector => document.querySelector(selector + ' input[type=text]')?.value.length > 0, form);
    const input = p.locator(form + ' input[type=text]').first();
    const value = await input.inputValue();
    await input.fill('Borrador sin traducir');
    await input.focus();
    await p.evaluate(() => HospitalI18n.apply('es-UY'));
    check((await input.inputValue()) === 'Borrador sin traducir', file + ' preserves unsaved input when switching');
    await p.evaluate(() => HospitalI18n.apply('en'));
    await input.fill(value);
    const domain = file.split('/').pop().replace('.php', '');
    const response = p.waitForResponse(res => res.url().includes('/' + domain + '.php?action=update') && res.request().method() === 'POST');
    await p.locator(form + ' [type=submit]').click();
    const res = await response;
    check(res.status() === 200, file + ' saves canonical enum values in English');
    check(!errors.length, file + ' edit flow has no script errors');
  }
  await go('surveys/survey-new.php?template=patient_care');
  await p.waitForTimeout(100);
  check((await p.locator('.builder-step[data-step="3"]').getAttribute('class')).includes('is-complete'),
    'valid survey content and default configuration retain the finished icon');
  check((await p.locator('[data-info=title]').inputValue()).includes('patient satisfaction'), 'English template inserts English editable content');
  const title = await p.locator('[data-info=title]').inputValue();
  await p.locator('[data-step="2"]').first().click();
  await p.locator('#form-title').fill('');
  check(!(await p.locator('.builder-step[data-step="3"]').getAttribute('class')).includes('is-complete'),
    'removing required survey content clears the finished icon');
  await p.locator('#form-title').fill('Texto propio');
  await p.evaluate(() => HospitalI18n.apply('es-UY'));
  check((await p.locator('[data-info=title]').inputValue()) === title && (await p.locator('#form-title').inputValue()) === 'Texto propio', 'language switching never rewrites template or draft content');
  // SQL persistence, cross-tab propagation and isolated save failure.
  await go('settings.php');
  const second = await context.newPage();
  await second.goto(base + '/src/views/main-menu.php');
  await second.evaluate(async () => {
    await HospitalI18n.ready;
    await HospitalAuth.ready;
  });
  await p.selectOption('#settings-language', 'en');
  await p.waitForTimeout(200);
  await p.selectOption('#settings-language', 'es-UY');
  await second.waitForFunction(() => HospitalI18n.locale === 'es-UY');
  check(true, 'saved SQL preference propagates to an already-open tab');
  await p.reload();
  await ready();
  await p.locator('#privacy-tab').click();
  check((await p.evaluate(() => HospitalI18n.locale)) === 'es-UY', 'SQL preference persists after refresh');
  await p.route('**/api/v1/settings.php?action=preferences-update', route => route.fulfill({
    status: 503,
    contentType: 'application/json',
    body: JSON.stringify({
      success: false,
      error: {
        code: 'SERVICE_UNAVAILABLE',
        message: 'Service unavailable.'
      },
      meta: {}
    })
  }));
  await p.selectOption('#settings-language', 'en');
  await p.waitForTimeout(250);
  check((await p.evaluate(() => HospitalI18n.locale)) === 'es-UY', 'failed language save restores confirmed preference');
  await p.unroute('**/api/v1/settings.php?action=preferences-update');
  let release;
  let first = true;
  await p.route('**/api/v1/settings.php?action=preferences-update', async route => {
    if (first) {
      first = false;
      await new Promise(resolve => release = resolve);
    }
    await route.continue();
  });
  await p.selectOption('#settings-language', 'en');
  await p.waitForTimeout(40);
  await p.selectOption('#settings-language', 'es-UY');
  await p.selectOption('#settings-language', 'en');
  check((await p.evaluate(() => HospitalI18n.locale)) === 'en', 'rapid selections preview the newest choice immediately');
  release();
  await p.waitForTimeout(600);
  check((await p.evaluate(() => HospitalI18n.locale)) === 'en' && (await p.locator('#settings-language').inputValue()) === 'en', 'queued responses preserve the newest selected language');
  await p.unroute('**/api/v1/settings.php?action=preferences-update');
  const publicContext = await browser.newContext(),
    patient = await publicContext.newPage();
  let patientErrors = [];
  patient.on('pageerror', e => patientErrors.push(e.message));
  await patient.goto(base + '/src/views/surveys/user-view/survey-user.php?lang=en#token=' + r.token);
  await patient.evaluate(() => HospitalI18n.ready);
  await patient.waitForTimeout(250);
  check((await patient.locator('#patient-survey-title').innerText()) === r.survey.form_title, 'public survey preserves original question/form record text');
  await patient.locator('[data-answer-rating]').first().check();
  const tokenBefore = await patient.evaluate(() => location.hash);
  await patient.selectOption('[data-language-select]', 'es-UY');
  check((await patient.locator('[data-answer-rating]:checked').count()) === 1 && (await patient.evaluate(() => location.hash)) === tokenBefore, 'public selector preserves selected answer and fragment credentials');
  await patient.selectOption('[data-language-select]', 'en');
  await patient.locator('#next-question').click();
  await patient.locator('#submit-answers').click();
  await patient.waitForTimeout(200);
  check((await patient.locator('#question-stage').innerText()).includes('Responses submitted'), 'patient submission commits with translated completion interface');
  check(!patientErrors.length, 'public survey switch/review/submit has no script errors');
  await patient.goto(base + '/index.php');
  await patient.evaluate(() => HospitalI18n.ready);
  check((await patient.locator('h1').innerText()) === 'Inicio de Sesion', 'anonymous public pages default to Spanish');
  await patient.goto(base + '/index.php?lang=en');
  await patient.evaluate(() => HospitalI18n.ready);
  await patient.locator('a[href*="register.php"]').click();
  await patient.waitForURL('**/register.php?lang=en');
  await patient.waitForFunction(() => Boolean(window.HospitalI18n));
  await patient.evaluate(() => HospitalI18n.ready);
  check(patient.url().includes('lang=en') && (await patient.locator('h1').innerText()) === 'Registration', 'explicit English links carry language through public navigation');
  await patient.goto(base + '/index.php?lang=invalid');
  await patient.evaluate(() => HospitalI18n.ready);
  check((await patient.evaluate(() => HospitalI18n.locale)) === 'es-UY', 'invalid public language safely defaults to Spanish');
  fs.writeFileSync('tests/.runtime/i18n-deep-results.json', JSON.stringify({
    checks,
    results
  }, null, 2));
  await browser.close();
  console.log('TOTAL', checks);
})().catch(e => {
  console.error(e.stack);
  process.exit(1);
});

