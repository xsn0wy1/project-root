/* Read-only staff-page sweep using the existing Playwright dependency and private demo fixtures. */
'use strict';
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const {chromium} = require('playwright');

async function run() {
  const fixturePath = process.env.HOSPITAL_DEMO_BROWSER_FIXTURE;
  assert(fixturePath, 'Provide a private HOSPITAL_DEMO_BROWSER_FIXTURE JSON file.');
  const fixture = JSON.parse(fs.readFileSync(fixturePath));
  const smoke = process.env.HOSPITAL_DEMO_BROWSER_SMOKE === '1';
  const locales = smoke ? ['es-UY'] : ['es-UY', 'en'];
  const themes = smoke ? ['dark'] : ['dark', 'light'];
  const sizes = {desktop: {width: 1440, height: 900}};
  if (!smoke) sizes.phone = {width: 390, height: 844};
  const output = path.dirname(fixturePath);
  const views = path.join(__dirname, '../public/src/views');
  const files = fs.readdirSync(views, {recursive: true}).filter(file =>
    file.endsWith('.php') && !file.startsWith('register-restore/') &&
    !file.includes('user-view/') && file !== 'error.php').sort();
  const permissions = {
    'management/accounts.php': 'admin',
    'management/patients.php': 'records_manager',
    'management/doctors.php': 'records_manager',
    'management/ambulances.php': 'dispatcher',
    'management/personnel.php': 'dispatcher',
    'management/destinations.php': 'dispatcher',
    'management/routes.php': 'dispatcher',
    'traces/trace-new.php': 'dispatcher'
  };
  const proof = {cases: [], scriptErrors: [], apiErrors: [], assetErrors: [], ignoredDiagnostics429: 0};
  const browser = await chromium.launch({channel: 'chrome', headless: true});
  try {
    for (const account of fixture.accounts) {
      const context = await browser.newContext();
      const page = await context.newPage();
      let staff = false;
      let currentCase = account.role;
      const pending = new Set();
      page.on('pageerror', error => proof.scriptErrors.push(currentCase + ': ' + error.message));
      page.on('request', request => {
        const url = new URL(request.url());
        const diagnostics = url.pathname.endsWith('/settings.php') && url.searchParams.get('action') === 'diagnostics';
        if (!diagnostics && request.url().startsWith(fixture.base + '/api/v1/') &&
            ['fetch', 'xhr'].includes(request.resourceType())) pending.add(request);
      });
      page.on('requestfinished', request => pending.delete(request));
      page.on('requestfailed', request => pending.delete(request));
      page.on('response', response => {
        if (!staff || response.status() < 400) return;
        const url = new URL(response.url());
        if (response.status() === 429 && url.pathname.endsWith('/settings.php') &&
            url.searchParams.get('action') === 'diagnostics') {
          proof.ignoredDiagnostics429++;
          return;
        }
        if (response.url().startsWith(fixture.base + '/api/v1/')) {
          proof.apiErrors.push(currentCase + ': ' + response.status() + ' ' + url.pathname + url.search);
        } else if (response.url().startsWith(fixture.base) &&
                   ['script', 'stylesheet', 'font', 'image'].includes(response.request().resourceType())) {
          proof.assetErrors.push(currentCase + ': ' + response.status() + ' ' + new URL(response.url()).pathname);
        }
      });
      await page.goto(fixture.base + '/');
      await page.evaluate(async () => { await window.HospitalI18n.ready; });
      await page.evaluate(async credentials => {
        await HospitalApi.post('auth.php?action=login', credentials);
      }, {username: account.username, password: account.password});
      const original = await page.evaluate(() => HospitalApi.get('settings.php?action=get'));
      fs.writeFileSync(path.join(output, account.role + '-original-preferences.json'),
        JSON.stringify({locale: original.settings.locale, theme: original.settings.theme}), {mode: 0o600});
      staff = true;
      try {
        for (const locale of locales) {
          for (const theme of themes) {
            await page.evaluate(async preferences => {
              const current = await HospitalApi.get('settings.php?action=get');
              await HospitalApi.post('settings.php?action=preferences-update', {
                version: current.settings.version, ...preferences
              });
            }, {locale, theme});
            for (const [size, viewport] of Object.entries(sizes)) {
              await page.setViewportSize(viewport);
              for (const file of files) {
                currentCase = [account.role, locale, theme, size, file].join(' ');
                const required = permissions[file];
                const allowed = !required || account.roles.includes('admin') || account.roles.includes(required);
                const query = new URLSearchParams();
                if (file.endsWith('documents-viewer.php') || file.endsWith('documents-edit.php')) query.set('id', account.document_id);
                if (file.endsWith('trace-view.php')) query.set('id', account.transfer_id || fixture.transfer_id);
                if (file.endsWith('survey-view.php')) query.set('id', account.survey_id);
                const url = fixture.base + '/src/views/' + file + (query.size ? '?' + query : '');
                pending.clear();
                const response = await page.goto(url);
                assert.equal(response.status(), allowed ? 200 : 403, currentCase);
                assert.equal(new URL(page.url()).pathname, new URL(url).pathname, currentCase + ': unexpected redirect');
                await page.evaluate(async () => {
                  await window.HospitalI18n?.ready;
                  await window.HospitalAuth?.ready;
                  await document.fonts.ready;
                });
                const deadline = Date.now() + 2000;
                while (pending.size && Date.now() < deadline) await page.waitForTimeout(40);
                await page.waitForTimeout(100);
                assert.equal(await page.locator('html').getAttribute('data-theme'), theme, currentCase + ': saved theme');
                const htmlLanguage = await page.locator('html').getAttribute('lang');
                assert(locale === 'en' ? htmlLanguage === 'en' : ['es', 'es-UY'].includes(htmlLanguage), currentCase + ': saved language');
                if (allowed) {
                  assert.equal(await page.locator('.app-shell').count(), 1, currentCase + ': staff shell');
                  assert.equal(await page.locator('.hospital-notification[role="alert"]').count(), 0, currentCase + ': unexpected popup');
                  if (file.endsWith('documents.php')) assert(await page.locator('body').innerText().then(text => text.includes(fixture.document_title || 'Informe')), currentCase + ': seeded documents');
                  if (file.endsWith('survey.php')) assert(await page.locator('body').innerText().then(text => text.includes(account.survey_title)), currentCase + ': owned survey');
                  const targets = await page.locator('a[href]').evaluateAll(links => links.filter(link =>
                    link.checkVisibility({checkVisibilityCSS: true}) && !link.closest('[hidden]'))
                    .map(link => new URL(link.href).pathname));
                  for (const target of targets) {
                    const targetFile = target.split('/src/views/')[1];
                    const targetRole = permissions[targetFile];
                    assert(!targetRole || account.roles.includes('admin') || account.roles.includes(targetRole),
                      currentCase + ': visible link targets a forbidden page: ' + target);
                  }
                  const dispatch = account.roles.includes('admin') || account.roles.includes('dispatcher');
                  if (file === 'traces/trace.php' || file === 'traces/trace-view.php') {
                    const html = await response.text();
                    const tags = html.match(/<button\b[^>]*data-required-role="dispatcher"[^>]*>/g) || [];
                    assert.equal(tags.length, file === 'traces/trace.php' ? 1 : 3, currentCase + ': server-rendered dispatch controls');
                    for (const tag of tags) assert.equal(/\shidden(?:\s|>)/.test(tag), !dispatch,
                      currentCase + ': permission state is correct at first paint');
                  }
                  if (file === 'traces/trace.php') assert.equal(await page.locator('.primary-action').isVisible(), dispatch,
                    currentCase + ': New Transfer visibility');
                  if (file === 'traces/trace-view.php') {
                    for (const control of ['edit-transfer', 'cancel-transfer', 'update-status']) {
                      assert.equal(await page.locator('#' + control).isVisible(), dispatch,
                        currentCase + ': transfer action visibility: ' + control);
                    }
                  }
                }
                const screenshot = (account.role === 'admin' && locale === 'es-UY' && theme === 'dark' && size === 'desktop') ||
                  (size === 'phone' && locale === 'es-UY' && ['traces/trace-map.php', 'surveys/survey-view.php'].includes(file));
                if (screenshot) {
                  const name = [account.role, locale, theme, size, file.replaceAll('/', '_')].join('-') + '.png';
                  await page.screenshot({path: path.join(output, name), fullPage: true});
                }
                proof.cases.push({role: account.role, locale, theme, size, page: file, status: response.status()});
                fs.writeFileSync(path.join(output, 'browser-progress.json'),
                  JSON.stringify({currentCase, checked: proof.cases.length, pendingApiRequests: pending.size}));
              }
            }
          }
        }
      } finally {
        currentCase = account.role + ': restore preferences';
        await page.evaluate(async preferences => {
          const current = await HospitalApi.get('settings.php?action=get');
          await HospitalApi.post('settings.php?action=preferences-update', {
            version: current.settings.version, locale: preferences.locale, theme: preferences.theme
          });
        }, original.settings);
        await page.evaluate(() => HospitalApi.post('auth.php?action=logout', {}));
        await context.close();
      }
      console.log(account.role + ': every staff page checked' + (smoke ? '.' : ' in both themes/languages, desktop/phone.'));
    }
    assert.deepEqual(proof.scriptErrors, [], 'Browser runtime errors');
    assert.deepEqual(proof.apiErrors, [], 'Unexpected API errors');
    assert.deepEqual(proof.assetErrors, [], 'Local assets failed');
    proof.passed = true;
  } finally {
    fs.writeFileSync(path.join(output, 'browser-checks.json'), JSON.stringify(proof, null, 2));
    await browser.close();
  }
  console.log(proof.cases.length + ' role/page/theme/language/viewport checks passed.');
}

run().catch(error => {console.error(error.message); process.exitCode = 1;});
