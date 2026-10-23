'use strict';

/* First-paint checks shared by the existing i18n suite and local before/after captures. */
const assert = require('assert/strict');
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const es = JSON.parse(fs.readFileSync(path.join(root, 'db/json/es.json')));
const en = JSON.parse(fs.readFileSync(path.join(root, 'db/json/en.json')));
const copy = Object.fromEntries(Object.keys(es).filter(key =>
  typeof es[key] === 'string' && typeof en[key] === 'string' && es[key] !== en[key]
).map(key => [key, [es[key], en[key]]]));

async function capture(context, base, options = {}) {
  const {theme = 'dark', viewport = {width: 1440, height: 900}, assertNoFlash = true, name = 'english'} = options;
  const output = path.resolve(options.output || process.env.HOSPITAL_I18N_PAINT_OUTPUT || 'tests/.runtime/i18n-first-paint');
  assert(output.startsWith(path.join(root, 'tests/.runtime/')), 'Capture evidence must remain private.');
  fs.mkdirSync(output, {recursive: true});
  const page = await context.newPage();
  const cdp = await context.newCDPSession(page);
  const frames = [];
  try {
    await page.setViewportSize(viewport);
    await page.addInitScript(({copy, theme, cacheKey}) => {
      localStorage.setItem(cacheKey, theme);
      const result = window.__i18nPaint = {samples: [], englishAt: null};
      const normalize = text => text.replace(/\s+/g, ' ').trim();
      function sample() {
        const visible = [];
        if (document.body) for (const element of document.querySelectorAll('[data-i18n]')) {
          const pair = copy[element.dataset.i18n];
          if (!pair || getComputedStyle(element).visibility !== 'visible') continue;
          let hidden = false;
          for (let parent = element; parent; parent = parent.parentElement) {
            const style = getComputedStyle(parent);
            if (style.display === 'none' || Number(style.opacity) === 0) {hidden = true; break;}
          }
          if (hidden) continue;
          const range = document.createRange();
          range.selectNodeContents(element);
          if (![...range.getClientRects()].some(rect => rect.width > 0 && rect.height > 0 &&
            rect.bottom > 0 && rect.right > 0 && rect.top < innerHeight && rect.left < innerWidth)) continue;
          let params = {};
          try {params = JSON.parse(element.dataset.i18nParams || '{}');} catch (_) {}
          const interpolate = value => normalize(value.replace(/\{([a-zA-Z][a-zA-Z0-9_]*)\}/g,
            (match, key) => Object.hasOwn(params, key) ? String(params[key] ?? '') : match));
          visible.push({key: element.dataset.i18n, text: normalize(element.textContent),
            spanish: interpolate(pair[0]), english: interpolate(pair[1])});
        }
        const untranslated = visible.filter(item => item.text !== item.english);
        const time = performance.now();
        result.samples.push({time, visible: visible.length, untranslated});
        if (result.englishAt === null && visible.length && !untranslated.length) result.englishAt = time;
        if (!result.stop) requestAnimationFrame(sample);
      }
      requestAnimationFrame(sample);
    }, {copy, theme, cacheKey: 'hospital-theme:'+new URL(base+'/').pathname});
    await cdp.send('Emulation.setCPUThrottlingRate', {rate: 6});
    await cdp.send('Network.enable');
    await cdp.send('Network.setCacheDisabled', {cacheDisabled: true});
    cdp.on('Page.screencastFrame', frame => {
      frames.push({data: frame.data, timestamp: frame.metadata.timestamp});
      cdp.send('Page.screencastFrameAck', {sessionId: frame.sessionId}).catch(() => {});
    });
    await cdp.send('Page.startScreencast', {format: 'png', everyNthFrame: 1});
    const response = await page.goto(base+'/src/views/main-menu.php', {waitUntil: 'domcontentloaded'});
    assert.equal(response.status(), 200);
    assert.match(await response.text(), /<html[^>]*lang="en"/);
    await page.evaluate(async () => {await HospitalI18n.ready; await HospitalAuth.ready;});
    await page.waitForFunction(() => window.__i18nPaint.englishAt !== null);
    await page.waitForTimeout(100);
    await cdp.send('Page.stopScreencast');
    const result = await page.evaluate(() => {
      __i18nPaint.stop = true;
      return {...__i18nPaint, timeOrigin: performance.timeOrigin,
        paint: performance.getEntriesByType('paint').map(({name, startTime}) => ({name, startTime})),
        locale: HospitalI18n.locale, lang: document.documentElement.lang};
    });
    result.cpuRate = 6;
    result.viewport = viewport;
    result.theme = theme;
    result.frames = frames.filter(frame => frame.timestamp*1000 >= result.timeOrigin).map((frame, index) => {
      const time = frame.timestamp*1000-result.timeOrigin;
      const file = `${name}-${index}-${Math.round(time)}ms.png`;
      fs.writeFileSync(path.join(output, file), Buffer.from(frame.data, 'base64'), {mode: 0o600});
      return {time, file};
    });
    const contentful = result.paint.find(paint => paint.name === 'first-contentful-paint')?.startTime || 0;
    result.englishPaintAt = result.frames.find(frame => frame.time >= Math.max(contentful, result.englishAt))?.time;
    fs.writeFileSync(path.join(output, name+'.json'), JSON.stringify(result, null, 2), {mode: 0o600});
    assert.equal(result.locale, 'en');
    assert(result.frames.length > 0, 'CPU-throttled filmstrip was captured.');
    assert(result.englishPaintAt > 0, 'The filmstrip contains a paint after English becomes visible.');
    if (assertNoFlash) assert.deepEqual(result.samples.filter(sample => sample.untranslated.length), [],
      'English account exposes no visible Spanish data-i18n copy in any initial paint frame.');
    return result;
  } finally {
    await page.close();
  }
}

module.exports = {capture};
