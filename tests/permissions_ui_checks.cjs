/* Focused visual checks for the four intentionally changed controls/styles. */
'use strict';
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const {chromium} = require('playwright');

async function run() {
  const runtime = process.env.HOSPITAL_THEME_TEST_DIR;
  const environment = JSON.parse(fs.readFileSync(path.join(runtime, 'env.json')));
  const records = JSON.parse(fs.readFileSync(path.join(runtime, 'records.json')));
  const base = environment.HOSPITAL_AUTH_TEST_URL;
  const output = path.join(path.dirname(runtime), 'visuals');
  fs.mkdirSync(output, {recursive: true});
  const browser = await chromium.launch({channel: 'chrome', headless: true});
  const proof = {cases: [], errors: []};
  async function ready(page) {
    await page.evaluate(async () => {
      await window.HospitalI18n?.ready;
      await window.HospitalAuth?.ready;
      await document.fonts.ready;
    });
  }
  try {
    for (const theme of ['dark', 'light']) {
      for (const [size, viewport] of Object.entries({desktop: {width: 1440, height: 900}, phone: {width: 390, height: 844}})) {
        const name = theme + '-' + size;
        const context = await browser.newContext({viewport});
        const page = await context.newPage();
        page.on('pageerror', error => proof.errors.push(error.message));
        let original;
        try {
          await page.goto(base + '/'); await ready(page);
          await page.evaluate(password => HospitalApi.post('auth.php?action=login', {username: 'system_admin', password}), environment.HOSPITAL_ADMIN_PASSWORD);
          original = await page.evaluate(() => HospitalApi.get('settings.php?action=get'));
          await page.evaluate(async theme => {
            const current = await HospitalApi.get('settings.php?action=get');
            await HospitalApi.post('settings.php?action=preferences-update', {version: current.settings.version, locale: 'es-UY', theme});
          }, theme);
          await page.goto(base + '/src/views/documents/documents.php', {waitUntil: 'domcontentloaded'}); await ready(page);
          const normalSmoothing = await page.locator('body').evaluate(node => getComputedStyle(node).webkitFontSmoothing);
          await page.goto(base + '/src/views/main-menu.php', {waitUntil: 'domcontentloaded'}); await ready(page);
          assert.equal(await page.locator('.sidebar [data-i18n="main_menu.gestion"]').textContent(), 'Gestión');
          assert.equal(await page.locator('[data-i18n="trace_map.gestion"]').count(), 0);
          await page.locator('.sidebar-action').click();
          await page.waitForTimeout(850);
          await page.screenshot({path: path.join(output, name + '-sidebar.png')});

          await page.goto(base + '/src/views/traces/trace-map.php', {waitUntil: 'domcontentloaded'}); await ready(page);
          assert.equal(await page.locator('body').evaluate(node => getComputedStyle(node).webkitFontSmoothing), normalSmoothing);
          assert.equal(await page.locator('.sidebar [data-i18n="main_menu.gestion"]').textContent(), 'Gestión');
          await page.locator('.sidebar-action').click();
          await page.waitForTimeout(850);
          await page.screenshot({path: path.join(output, name + '-map.png')});

          await page.goto(base + '/src/views/surveys/survey-new.php', {waitUntil: 'domcontentloaded'}); await ready(page);
          await page.waitForFunction(() => !document.getElementById('save-survey').disabled);
          await page.locator('#internal-title').fill('Encuesta ficticia de atención');
          await page.locator('.builder-step[data-step="2"]').click();
          await page.locator('#form-title').fill('Tu experiencia de atención');
          await page.locator('.question-type-button[data-add-type=rating]').click();
          await page.locator('#question-title').fill('¿Cómo calificás la atención?');
          await page.locator('.builder-step[data-step="3"]').click();
          const icon = page.locator('#publish-survey img');
          assert(await icon.isVisible());
          assert((await icon.getAttribute('src')).endsWith('/check-icon.svg'));
          const iconBox = await icon.boundingBox();
          assert.equal(iconBox.width, 17); assert.equal(iconBox.height, 17);
          await page.screenshot({path: path.join(output, name + '-publish.png'), fullPage: true});

          const participant = await browser.newContext({viewport});
          try {
            const publicPage = await participant.newPage();
            publicPage.on('pageerror', error => proof.errors.push(error.message));
            await publicPage.goto(base + '/src/views/surveys/user-view/survey-user.php#token=' + records.token);
            await ready(publicPage);
            await publicPage.evaluate(theme => HospitalTheme.preview(theme), theme);
            const select = publicPage.locator('.language-control select');
            assert(await select.isVisible());
            assert.equal(await select.getAttribute('data-hospital-button'), null);
            assert.equal(await publicPage.locator('.language-control .hospital-select-trigger').count(), 0);
            assert.equal(await select.locator('option:checked').textContent(), 'Español');
            const layout = await select.evaluate(node => {
              const box = node.getBoundingClientRect();
              const globe = node.parentElement.querySelector('svg').getBoundingClientRect();
              return {textLeft: box.left + parseFloat(getComputedStyle(node).paddingLeft), globeRight: globe.right};
            });
            assert(layout.textLeft >= layout.globeRight + 5, 'Language text overlaps the globe');
            await select.selectOption('en');
            await publicPage.waitForFunction(() => document.documentElement.lang === 'en');
            await select.selectOption('es-UY');
            await publicPage.waitForFunction(() => document.documentElement.lang === 'es-UY');
            await publicPage.screenshot({path: path.join(output, name + '-language.png'), fullPage: true});
            proof.cases.push({theme, size, nativeLanguage: true, singleIcon: true, checkmark: true, smoothing: normalSmoothing, management: 'Gestión'});
          } finally { await participant.close(); }
        } finally {
          if (original) await page.evaluate(async preferences => {
            const current = await HospitalApi.get('settings.php?action=get');
            await HospitalApi.post('settings.php?action=preferences-update', {version: current.settings.version, locale: preferences.locale, theme: preferences.theme});
          }, original.settings);
          await context.close();
        }
      }
    }
    assert.deepEqual(proof.errors, []);
    proof.passed = true;
  } finally {
    fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify(proof, null, 2));
    await browser.close();
  }
  console.log('Four UI fixes checked in dark/light, desktop/phone; language switching and icon spacing pass.');
}

run().catch(error => {console.error(error.message); process.exitCode = 1;});
