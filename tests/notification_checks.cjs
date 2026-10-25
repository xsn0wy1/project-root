/* Notification scenarios called by the existing theme/browser suite. Fictional fixtures only. */
'use strict';
const fs = require('fs');
const path = require('path');

async function run({browser, runtime, base, records, ready, check}) {
 const output = path.join(runtime, 'notifications');
 fs.mkdirSync(output, {recursive: true});
 let sequence = 0;
 const errors = [];
 const cases = [];
 const sizes = {desktop: {width: 1440, height: 900}, phone: {width: 390, height: 844}};
 const cards = page => page.locator('.hospital-notification');
 async function dismiss(page) {
  while (await cards(page).count()) await cards(page).first().locator('button').click();
 }
 async function signIn(page, locale, theme) {
  await page.evaluate(async ({password, locale, theme}) => {
   await HospitalApi.post('auth.php?action=login', {username: 'system_admin', password});
   const current = await HospitalApi.get('settings.php?action=get');
   await HospitalApi.post('settings.php?action=preferences-update', {
    version: current.settings.version, locale, theme
   });
  }, {password: process.env.HOSPITAL_ADMIN_PASSWORD, locale, theme});
 }
 for (const locale of ['es-UY', 'en']) for (const theme of ['dark', 'light']) {
  const dictionary = JSON.parse(fs.readFileSync(path.join(__dirname, '../db/json', locale === 'en' ? 'en.json' : 'es.json')));
  for (const [size, viewport] of Object.entries(sizes)) {
   const name = locale + '-' + theme + '-' + size;
   const context = await browser.newContext({viewport}), page = await context.newPage();
   page.on('pageerror', error => errors.push(name + ': ' + error.message));
   try {
    await page.clock.install();
    await page.goto(base + '/?lang=' + locale);await ready(page);
    await page.evaluate(theme => HospitalTheme.preview(theme), theme);
    check(await cards(page).count() === 0, name + ': normal auth load is quiet');
    const username = 'missing_notify_' + (++sequence) + '_' + Date.now();
    await page.locator('[name=username]').fill(username);
    await page.locator('[name=password]').fill('Fictional wrong password');
    const rejected = page.waitForResponse(response => response.url().endsWith('auth.php?action=login'));
    await page.locator('#loginForm [type=submit]').click();
    check((await rejected).status() === 401, name + ': wrong login is rejected');
    await page.waitForFunction(() => !document.querySelector('#loginForm [type=submit]').disabled);
    check(await page.locator('#loginMessage').textContent() === dictionary['errors.usuario_o_contrasena_incorrectos'],
     name + ': wrong login retains the translated server message inline');
    check(await page.locator('[name=password]').inputValue() === 'Fictional wrong password' &&
     await page.locator('[name=username]').inputValue() === username && await cards(page).count() === 0,
    name + ': inline login error retains input and has no duplicate popup');
    await page.screenshot({path: path.join(output, name + '-login.png')});

    await signIn(page, locale, theme);
    await page.goto(base + '/src/views/settings.php');await ready(page);
    await page.waitForFunction(() => Boolean(document.getElementById('settings-email').value));
    check(await cards(page).count() === 0, name + ': normal staff load is quiet');
    const endpoint = '**/api/v1/settings.php?action=notifications';
    const serverMessage = dictionary['errors.revisa_los_campos_indicados'];
    await page.route(endpoint, route => route.fulfill({status: 503, contentType: 'application/json',
     body: JSON.stringify({success: false, error: {code: 'FIXTURE', message: serverMessage}})}));
    await page.evaluate(async () => {
     try {await HospitalApi.get('settings.php?action=notifications');}
     catch (error) {HospitalApi.report(error, 'fixture');HospitalApi.report(error, 'fixture');}
    });
    check(await cards(page).count() === 1 && await cards(page).first().getAttribute('role') === 'alert' &&
     await cards(page).first().locator('span').textContent() === serverMessage,
    name + ': explicit error report displays the actual server message once');
    await dismiss(page);await page.unroute(endpoint);
    await page.route(endpoint, route => route.abort('connectionfailed'));
    await page.evaluate(() => HospitalApi.get('settings.php?action=notifications').catch(error => HospitalApi.report(error, 'offline')));
    check(await cards(page).first().locator('span').textContent() ===
     dictionary['api_client.no_se_pudo_conectar_con_el_servidor_revisa_la_conexion_e_intenta_nuevamente'],
    name + ': offline request shows translated connection guidance');
    await page.screenshot({path: path.join(output, name + '-offline.png')});
    await dismiss(page);await page.unroute(endpoint);
    await page.evaluate(async () => {
     const controller = new AbortController();
     const pending = HospitalApi.request('settings.php?action=notifications', {signal: controller.signal});
     controller.abort();
     try {await pending;}catch (error) {HospitalApi.report(error, 'aborted');}
     HospitalApi.report(new HospitalApi.ApiError('Staff 401 fixture', 401, 'UNAUTHORIZED'), 'session');
     HospitalApi.report(new Error('Silent fixture'), 'background', {silent: true});
    });
    check(await cards(page).count() === 0, name + ': aborts, staff 401 and explicit silent reports do not notify');

    await page.evaluate(() => {
     HospitalNotify.show('<b>Plain fixture text</b>', 'info');
     HospitalNotify.show('Success fixture', 'success');
     HospitalNotify.show('Error fixture', 'error');
    });
    const positions = await cards(page).evaluateAll(nodes => nodes.map(node => {
     const box = node.getBoundingClientRect();
     return {x: box.x, right: box.right, y: box.y, bottom: box.bottom, role: node.getAttribute('role')};
    }));
    check(positions.length === 3 && positions[0].role === 'status' && positions[1].role === 'status' && positions[2].role === 'alert',
     name + ': info/success/error have the requested roles');
    check(positions.every(box => box.x >= 0 && box.right <= viewport.width) &&
     positions[0].y >= 0 && positions[0].y < 32 && positions[0].bottom <= positions[1].y && positions[1].bottom <= positions[2].y,
    name + ': the readable stack stays at the top-right inside the viewport');
    check(await cards(page).first().locator('b').count() === 0 &&
     await cards(page).first().locator('button').getAttribute('aria-label') === dictionary['settings.cerrar'],
    name + ': text is plain and the close button is localized');
    await page.screenshot({path: path.join(output, name + '-stack.png')});
    await cards(page).first().locator('button').click();
    check(await cards(page).count() === 2, name + ': close removes only its own popup');
    await page.clock.fastForward(6100);
    check(await cards(page).count() === 0, name + ': remaining popups expire after six seconds');

    const saved = page.waitForResponse(response => response.url().endsWith('settings.php?action=profile-update'));
    await page.locator('#settings-name').fill('Fictional notification ' + Date.now().toString(36) + sequence);
    await page.locator('#settings-email').click();
    check((await saved).status() === 200, name + ': Settings save succeeds');
    await page.waitForSelector('.hospital-notification[data-type=success]');
    check(await cards(page).count() === 1 && await cards(page).first().locator('span').textContent() === dictionary['accounts.cambio_guardado'],
     name + ': Settings gets its missing success confirmation');
    await dismiss(page);

    await page.evaluate(async () => {
     const current = await HospitalApi.get('settings.php?action=get');
     await HospitalApi.post('settings.php?action=preferences-update', {version: current.settings.version, optional_diagnostics: true});
    });
    const diagnostics = '**/api/v1/settings.php?action=diagnostics';
    await page.route(diagnostics, route => route.fulfill({status: 503, contentType: 'application/json',
     body: JSON.stringify({success: false, error: {code: 'FIXTURE', message: serverMessage}})}));
    // Diagnostics read native navigation timing; use a context without the virtual clock.
    const diagnosticContext = await browser.newContext({viewport, storageState: await context.storageState()});
    try {
     const diagnosticPage = await diagnosticContext.newPage();
     await diagnosticPage.route(diagnostics, route => route.fulfill({status: 503, contentType: 'application/json',
      body: JSON.stringify({success: false, error: {code: 'FIXTURE', message: serverMessage}})}));
     const diagnostic = diagnosticPage.waitForResponse(response => response.url().endsWith('settings.php?action=diagnostics'));
     await diagnosticPage.goto(base + '/src/views/main-menu.php');await ready(diagnosticPage);
     check((await diagnostic).status() === 503 && await cards(diagnosticPage).count() === 0,
      name + ': background diagnostics failure produces no popup');
    } finally {await diagnosticContext.close();}
    await page.goto(base + '/src/views/main-menu.php');await ready(page);
    await page.route('**/api/v1/auth.php?action=heartbeat', route => route.fulfill({status: 503, contentType: 'application/json',
     body: JSON.stringify({success: false, error: {code: 'FIXTURE', message: serverMessage}})}));
    const heartbeat = page.waitForResponse(response => response.url().endsWith('auth.php?action=heartbeat'));
    await page.clock.fastForward(60000);check((await heartbeat).status() === 503, name + ': background heartbeat failure exercised');
    check(await cards(page).count() === 0, name + ': failing background diagnostics/heartbeat produce no popup');
    await page.unrouteAll({behavior: 'wait'});

    await page.goto(base + '/src/views/management/ambulances.php');await ready(page);
    await page.waitForFunction(() => !document.getElementById('new-ambulance').disabled);
    check(await cards(page).count() === 0, name + ': normal ambulance load is quiet');
    const plate = 'NTF' + Date.now().toString(36).toUpperCase() + sequence;
    await page.evaluate(plate => HospitalApi.post('ambulances.php?action=create', {
     plate, model: 'Fictional notification fixture', capacity: 4, operational_status: 'available'
    }), plate);
    await page.locator('#new-ambulance').click();
    await page.locator('#ambulance-plate').fill(plate);
    await page.locator('#ambulance-model').fill('Fictional retained model');
    await page.locator('#ambulance-capacity').fill('4');
    const duplicate = page.waitForResponse(response => response.url().endsWith('ambulances.php?action=create'));
    await page.locator('#ambulance-form [type=submit]').click();
    const duplicateResponse = await duplicate, duplicatePayload = await duplicateResponse.json();
    check(duplicateResponse.status() === 409, name + ': real SQL duplicate plate is rejected');
    check(await cards(page).count() === 1 && await cards(page).first().locator('span').textContent() === duplicatePayload.error.message,
     name + ': rejected save displays the exact translated server message');
    check(await page.locator('#ambulance-dialog').isVisible() && await page.locator('#ambulance-plate').inputValue() === plate &&
     await page.locator('#ambulance-model').inputValue() === 'Fictional retained model', name + ': rejected save retains form input');
    await page.screenshot({path: path.join(output, name + '-rejected-save.png')});
    await dismiss(page);
    await page.locator('#ambulance-plate').fill(plate + 'X');
    const created = page.waitForResponse(response => response.url().endsWith('ambulances.php?action=create'));
    await page.locator('#ambulance-form [type=submit]').click();
    check((await created).status() === 201, name + ': corrected save succeeds');
    await page.locator('#ambulance-toast').waitFor({state: 'visible'});
    check(await cards(page).count() === 0, name + ': existing ambulance success toast is kept without duplication');
    cases.push(name);
   } finally {await context.close();}
  }
 }
 check(errors.length === 0, 'notification matrix has no browser script errors: ' + errors.join(', '));
 fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify({cases, errors}, null, 2));
 await extraPages({browser, runtime, base, records, ready, check});
 await backgroundPolling({browser, base, records, ready, check});
}

async function extraPages({browser, runtime, base, records, ready, check}) {
 for (const locale of ['es-UY', 'en']) {
  const dictionary = JSON.parse(fs.readFileSync(path.join(__dirname, '../db/json', locale === 'en' ? 'en.json' : 'es.json')));
  const context = await browser.newContext(), page = await context.newPage();
  try {
   for (const [file, form, message] of [
    ['src/views/register-restore/register.php', '#registerForm', '#registerMessage'],
    ['src/views/register-restore/restore-password.php', '#recovery-request-form', '#recovery-status']
   ]) {
    await page.goto(base + '/' + file + '?lang=' + locale);await ready(page);
    const text = dictionary['errors.revisa_los_campos_indicados'];
    let action = 'register';
    if (form === '#recovery-request-form') action = 'reset-request';
    await page.route('**/api/v1/auth.php?action=' + action, route => route.fulfill({status: 422, contentType: 'application/json',
     body: JSON.stringify({success: false, error: {code: 'VALIDATION_FAILED', message: text}})}));
    if (form === '#registerForm') {
     await page.locator('[name=username]').fill('fictional_notify');
     await page.locator('[name=password]').fill('Fictional notification password');
     await page.locator('[name=email]').fill('notification@hospital.example');
     await page.locator('[name=national_id]').fill('88001122');
    } else {await page.locator('[name=email]').fill('notification@hospital.example');}
    await page.locator(form + ' [type=submit]').click();
    await page.waitForFunction(({message, text}) => document.querySelector(message).textContent === text, {message, text});
    check(await page.locator(message).isVisible() && await page.locator('.hospital-notification').count() === 0,
     locale + ': ' + form + ' preserves the server message inline without duplicate popup');
    check(await page.locator('[name=email]').inputValue() === 'notification@hospital.example', locale + ': failed auth form retains input');
    await page.unrouteAll({behavior: 'wait'});
   }
   for (const theme of ['dark', 'light']) {
    await page.addInitScript(theme => localStorage.setItem('hospital-theme:/', theme), theme);
    await page.goto(base + '/src/views/error.php?status=503&lang=' + locale);
    await page.evaluate(() => HospitalNotify.show('Error-page notification fixture', 'info'));
    check(await page.locator('.hospital-notification[role=status]').isVisible() &&
     await page.locator('.hospital-notification button').getAttribute('aria-label') === dictionary['settings.cerrar'],
    locale + '/' + theme + ': standalone error page loads the component without a dictionary request');
   }
   await page.goto(base + '/src/views/surveys/user-view/survey-user.php#token=' + records.token);await ready(page);
   check(await page.evaluate(() => typeof HospitalNotify.show === 'function') && await page.locator('.hospital-notification').count() === 0,
    locale + ': public survey loads the same component quietly');
  } finally {await context.close();}
 }
}

async function backgroundPolling({browser, base, records, ready, check}) {
 const context = await browser.newContext(), page = await context.newPage();
 const errors = [];
 page.on('pageerror', error => errors.push(error.message));
 try {
  await page.clock.install();
  await context.route('https://**', route => route.fulfill({status: 200, contentType: 'image/png',
   body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j8XsAAAAASUVORK5CYII=', 'base64')}));
  await page.goto(base + '/');await ready(page);
  await page.evaluate(password => HospitalApi.post('auth.php?action=login', {username: 'system_admin', password}), process.env.HOSPITAL_ADMIN_PASSWORD);
  const polls = [
   ['src/views/traces/trace.php', '**/api/v1/transfers.php?action=list*', 15000],
   ['src/views/traces/trace-view.php?id=' + records.transfer.id, '**/api/v1/tracking.php?action=snapshot*', 3000],
   ['src/views/traces/trace-map.php', '**/api/v1/tracking.php?action=snapshot*', 6000],
   ['src/views/surveys/survey-view.php?id=' + records.survey.id, '**/api/v1/surveys.php?action=get*', 15000]
  ];
  for (const [file, endpoint, delay] of polls) {
   await page.goto(base + '/' + file);await ready(page);await page.waitForTimeout(250);
   await page.evaluate(() => document.activeElement.blur());
   check(await page.locator('.hospital-notification').count() === 0, file + ': normal data load creates no popup');
   await page.route(endpoint, route => route.fulfill({status: 503, contentType: 'application/json',
    body: JSON.stringify({success: false, error: {code: 'BACKGROUND_FIXTURE', message: 'Background failure fixture'}})}));
   const failure = page.waitForResponse(response => response.status() === 503 && response.url().includes('/api/v1/'));
   await page.clock.fastForward(delay);await failure;await page.waitForTimeout(100);
   check(await page.locator('.hospital-notification').count() === 0, file + ': actual failing poll stays silent');
   await page.unroute(endpoint);
  }
  for (const domain of ['ambulances', 'doctors', 'personnel', 'destinations']) {
   await page.goto(base + '/src/views/management/' + domain + '.php');await ready(page);await page.waitForTimeout(250);
   const endpoint = '**/api/v1/' + domain + '.php?action=list*';
   await page.route(endpoint, route => route.fulfill({status: 503, contentType: 'application/json',
    body: JSON.stringify({success: false, error: {code: 'BACKGROUND_FIXTURE', message: 'Focus refresh fixture'}})}));
   const failure = page.waitForResponse(response => response.status() === 503 && response.url().includes(domain + '.php'));
   await page.evaluate(() => window.dispatchEvent(new Event('focus')));await failure;await page.waitForTimeout(100);
   check(await page.locator('.hospital-notification').count() === 0, domain + ': automatic focus refresh stays silent');
   await page.unroute(endpoint);
  }
  check(errors.length === 0, 'background checks have no script errors: ' + errors.join(', '));
 } finally {await context.close();}
}

module.exports = {run, backgroundPolling};
