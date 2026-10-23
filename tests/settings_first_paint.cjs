'use strict';
const fs = require('fs'), path = require('path'), assert = require('assert/strict');

/* Reuses the existing theme suite's authenticated context and disposable fixture. */
async function capture(context, base, output, {name = 'settings', assertStable = true, cacheTheme = 'dark'} = {}) {
  output = path.resolve(output);
  assert(output.startsWith(path.join(__dirname, '.runtime/')), 'First-paint evidence stays private.');
  fs.mkdirSync(output, {recursive: true});
  const page = await context.newPage(), cdp = await context.newCDPSession(page);
  try {
    await page.addInitScript(({cacheKey, cacheTheme}) => {
      localStorage.setItem(cacheKey, cacheTheme);
      const result = window.__settingsPaint = {changes: [], transitions: [], first: null};
      function read() {
        return [...document.querySelectorAll('[data-preference]')].map(button => ({
          preference: button.dataset.preference, on: button.classList.contains('is-on'),
          checked: button.getAttribute('aria-checked')
        }));
      }
      function sample() {
        const state = read(), painted = performance.getEntriesByName('first-contentful-paint').length > 0;
        if (painted && state.length === 4) {
          if (!result.first) result.first = state;
          if (result.last && JSON.stringify(state) !== JSON.stringify(result.last))
            result.changes.push({time: performance.now(), before: result.last, after: state});
          result.last = state;
        }
        if (!result.stop) requestAnimationFrame(sample);
      }
      document.addEventListener('transitionrun', event => {
        if (event.target.matches('.toggle-knob, .theme-highlight') &&
            performance.getEntriesByName('first-contentful-paint').length)
          result.transitions.push({time: performance.now(), property: event.propertyName,
            preference: event.target.closest('[data-preference]')?.dataset.preference || 'theme'});
      }, true);
      requestAnimationFrame(sample);
    }, {cacheKey: 'hospital-theme:'+new URL(base+'/').pathname, cacheTheme});
    await cdp.send('Emulation.setCPUThrottlingRate', {rate: 6});
    let firstRequest = true;
    await page.route('**/api/v1/auth.php?action=me', async route => {
      if (firstRequest) {
        firstRequest = false;
        // Guarantee that the API confirmation occurs after the initial controls have painted.
        await page.waitForFunction(() => window.__settingsPaint?.first?.length === 4);
        await page.screenshot({path: path.join(output, name+'-first.png')});
        await page.waitForTimeout(150);
      }
      await route.continue();
    });
    const response = await page.goto(base+'/src/views/settings.php', {waitUntil: 'domcontentloaded'});
    assert.equal(response.status(), 200);
    await page.evaluate(async () => {await HospitalI18n.ready; await HospitalAuth.ready;});
    await page.waitForFunction(() => Boolean(document.getElementById('settings-email').value));
    await page.waitForTimeout(700);
    await page.locator('#privacy-tab').click();
    await page.waitForTimeout(150);
    const result = await page.evaluate(() => {
      __settingsPaint.stop = true;
      return {...__settingsPaint, activeAnimations: [...document.querySelectorAll('.toggle-knob, .theme-highlight')]
        .flatMap(node => node.getAnimations()).length, theme: document.documentElement.dataset.theme,
        saved: Object.fromEntries(__settingsPaint.first.map(button =>
          [button.preference, HospitalAuth.current.settings[button.preference]])),
        savedTheme: HospitalAuth.current.settings.theme};
    });
    result.cpuRate = 6;
    await page.screenshot({path: path.join(output, name+'-settled.png')});
    fs.writeFileSync(path.join(output, name+'.json'), JSON.stringify(result, null, 2), {mode: 0o600});
    assert.equal(result.first.length, 4);
    if (assertStable) {
      assert.deepEqual(result.changes, [], 'No preference changes after first paint.');
      assert.deepEqual(result.transitions, [], 'No knob or theme-highlight transition starts after first paint.');
      assert.equal(result.activeAnimations, 0);
      for (const button of result.first) {
        assert.equal(button.on, result.saved[button.preference]);
        assert.equal(button.checked, String(button.on));
      }
      assert.equal(result.theme, result.savedTheme);
    }
    return result;
  } finally {await page.close();}
}

module.exports = {capture};
