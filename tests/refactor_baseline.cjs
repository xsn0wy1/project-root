/* Capture the real reference copy and compare the candidate with identical fictional SQL data.
 * Run with bundled Node and NODE_PATH pointing at its bundled node_modules.
 * Runtime snapshots, login and capability tokens stay in ignored tests/.runtime/stage1.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const {chromium} = require('playwright');
const {PNG} = require('pngjs');
const computedStyles = require('./computed_styles.cjs');
const root = path.join(__dirname, '..');
const runtime = process.env.BASELINE_ENV_DIR || path.join(__dirname, '.runtime/stage1');
const reference = process.env.BASELINE_REFERENCE_DIR || path.join(__dirname, '.runtime/stage1/reference');
const referencePhp = fs.existsSync(path.join(reference, 'public/index.php'));
const output = process.env.BASELINE_OUTPUT_DIR ? path.resolve(process.env.BASELINE_OUTPUT_DIR) : runtime;
const env = JSON.parse(fs.readFileSync(path.join(runtime, 'env.json')));
const selectedFiles = (process.env.BASELINE_FILES || '').split(',').filter(Boolean);
const fullStyles = process.env.BASELINE_COMPUTED_STYLES === '1';
const ignoreCustomProperties = process.env.BASELINE_IGNORE_CUSTOM_PROPERTIES === '1';
const exactPixels = process.env.BASELINE_EXACT_PIXELS === '1';
const referenceCss = process.env.BASELINE_REFERENCE_CSS_DIR;
const sizes = {desktop: {width: 1440, height: 900}, mobile: {width: 390, height: 844}, narrow: {width: 320, height: 640}};
const matrix = (process.env.BASELINE_SIZES || (fullStyles ? 'desktop,mobile,narrow' : 'desktop,mobile')).split(',');
const locales = process.env.BASELINE_LOCALES ? process.env.BASELINE_LOCALES.split(',') : fullStyles ? ['es-UY', 'en'] : [null];
const pixelLimit = Number(process.env.BASELINE_PIXEL_LIMIT || (fullStyles ? '0.041' : '0.1'));
assert(matrix.every(size => sizes[size]), 'Unknown baseline viewport');
assert(locales.every(locale => locale === null || ['es-UY', 'en'].includes(locale)), 'Unknown baseline language');
assert(Number.isFinite(pixelLimit) && pixelLimit >= 0, 'Invalid pixel limit');
assert(!fullStyles || (process.env.BASELINE_DROPDOWN_CHANGE !== '1' && process.env.BASELINE_LINK_CHANGE !== '1'), 'Full-style gate does not permit intentional UI masks');
const phpViews = new Set(['ambulances', 'patients', 'doctors', 'personnel', 'destinations', 'routes', 'management', 'accounts']);
const records = JSON.parse(fs.readFileSync(path.join(runtime, 'records.json')));
const pages = fs.readdirSync(path.join(reference, 'public'), {recursive: true})
    .filter(file => file.endsWith(referencePhp ? '.php' : '.html')).sort();
const ids = {'documents-viewer.html': records.document.id, 'documents-edit.html': records.document.id,
    'trace-view.html': records.transfer.id, 'survey-view.html': records.survey.id, 'survey-new.html': records.survey.id};
const selectors = ['.tarjeta', '.app-shell', '.topbar', '.sidebar', '.hero-panel', '.bg-panel',
    '.patient-header', '.patient-survey', '.patient-layout', '.directory-panel', '.metric-card',
    '#edit-dialog', '#status-dialog', '.survey-links-dialog', '#survey-public-qr', '#builder-form-dialog'];
const reportPath = path.join(output, 'visual-report.json');
const selectedPages = pages.filter(file => !selectedFiles.length || selectedFiles.some(name => file.endsWith(name)));
const report = process.argv.includes('--resume') && fs.existsSync(reportPath)
    ? JSON.parse(fs.readFileSync(reportPath))
    : {pages: selectedPages.length, comparisons: [], interactions: [], errors: [], consoleErrors: []};
const pendingApi = new WeakMap();

function currentPath(file) {
    if (['documents','traces','surveys'].some(area => file.startsWith('src/views/' + area + '/'))) return file.replace(/\.html$/, '.php');
    if (['index.html','src/views/main-menu.html','src/views/settings.html'].includes(file) || file.includes('register-restore/')) return file.replace(/\.html$/, '.php');
    return file.startsWith('src/views/management/') && phpViews.has(path.basename(file, '.html'))
        ? file.replace(/\.html$/, '.php') : file;
}

function pageUrl(base, file) {
    const id = ids[path.basename(file).replace(/\.php$/, '.html')];
    return base + '/' + file + (id ? '?id=' + id : '') +
        (/survey-user\.(html|php)$/.test(file) ? '#token=' + records.token : '');
}
async function ready(page) {
    await page.evaluate(async () => {await window.HospitalI18n?.ready; await window.HospitalAuth?.ready;});
    await page.waitForLoadState('networkidle', {timeout: 2000}).catch(() => {});
    await page.evaluate(() => document.fonts.ready);
    if (await page.locator('#documentForm,#newTransferForm').count()) {
        await page.waitForFunction(() => [...document.querySelectorAll('#documentForm,#newTransferForm')]
            .every(form => form.getAttribute('aria-busy') === 'false'));
    }
    await page.waitForTimeout(250);
    if (fullStyles && pendingApi.has(page)) {
        const deadline = Date.now() + 30000;
        while (pendingApi.get(page).size) {
            assert(Date.now() < deadline, 'Pending API requests did not settle: ' +
                [...pendingApi.get(page)].map(request => {
                    const url = new URL(request.url());
                    return url.pathname + '?action=' + url.searchParams.get('action');
                }).join(', '));
            await page.waitForTimeout(50);
        }
        await page.waitForTimeout(100);
    }
    if (fullStyles) await page.evaluate(async () => {
        const backgrounds = new Set();
        for (const node of document.querySelectorAll('*')) if (node.checkVisibility()) for (const pseudo of ['', '::before', '::after']) {
            const value = getComputedStyle(node, pseudo || null).backgroundImage;
            for (const match of value.matchAll(/url\("([^"]+)"\)/g)) backgrounds.add(match[1]);
        }
        const images = [...backgrounds].map(async source => {const image = new Image(); image.src = source; await image.decode();});
        images.push(...[...document.images].filter(image => image.currentSrc && image.checkVisibility() && !image.closest('.leaflet-tile-pane')).map(image => image.decode()));
        let timer;
        try {
            await Promise.race([Promise.all(images), new Promise((_, reject) => {timer = setTimeout(() => reject(new Error('Visible images did not decode')), 30000);})]);
        } finally {clearTimeout(timer);}
    });
}
async function geometry(page) {
    return page.evaluate(selectors => Object.fromEntries(selectors.map(selector => {
        const element = document.querySelector(selector);
        if (!element) return [selector, null];
        const rect = element.getBoundingClientRect(), style = getComputedStyle(element);
        return [selector, {x: rect.x, y: rect.y, w: rect.width, h: rect.height,
            background: style.backgroundColor, color: style.color, font: style.fontFamily,
            blur: style.backdropFilter, radius: style.borderRadius}];
    })), selectors);
}
function compareGeometry(before, after, label) {
    for (const selector of selectors) {
        assert.equal(!!after[selector], !!before[selector], label + ' presence ' + selector);
        if (!before[selector]) continue;
        for (const key of ['x', 'y', 'w', 'h'])
            assert(Math.abs(before[selector][key]-after[selector][key]) < 0.6, label + ' geometry ' + selector + '.' + key);
        for (const key of ['background', 'color', 'font', 'blur', 'radius'])
            assert.equal(after[selector][key], before[selector][key], label + ' style ' + selector + '.' + key);
    }
}
function pixelDifference(a, b, regions = []) {
    const left = PNG.sync.read(a), right = PNG.sync.read(b);
    assert.equal(left.width, right.width); assert.equal(left.height, right.height);
    for (const r of regions) for (let y = Math.max(0, Math.floor(r.y)-2); y < Math.min(left.height, Math.ceil(r.y+r.height)+2); y++)
        for (let x = Math.max(0, Math.floor(r.x)-2); x < Math.min(left.width, Math.ceil(r.x+r.width)+2); x++) {
            const offset = (y*left.width+x)*4;
            for (let c=0; c<3; c++) left.data[offset+c] = right.data[offset+c] = 0;
        }
    let changed = 0;
    for (let i = 0; i < left.data.length; i += 4)
        if ((exactPixels ? [0, 1, 2, 3] : [0, 1, 2]).some(c => Math.abs(left.data[i+c]-right.data[i+c]) > (exactPixels ? 0 : 12))) changed++;
    if (exactPixels) return 100 * changed / (left.width * left.height);
    return Math.round(100000*changed/(left.width*left.height))/1000;
}
async function capturePair(pair, name, captures) {
    if (process.env.BASELINE_TRACE_PROGRESS === '1') console.log('Preparing ' + name);
    if (fullStyles) {
        await Promise.all(pair.map(ready));
        if (process.env.BASELINE_TRACE_PROGRESS === '1') console.log('Reading styles for ' + name);
        const snapshots = await Promise.all(pair.map(page => computedStyles.snapshot(page, {ignoreCustomProperties})));
        const comparison = computedStyles.compare(...snapshots);
        for (let i = 0; i < snapshots.length; i++) computedStyles.save(path.join(output, 'computed-styles', (i ? 'current-' : 'reference-') + name + '.json.gz'), snapshots[i]);
        report.computedStyles ??= [];
        report.computedStyles.push({name, ...comparison});
    }
    const shapes = await Promise.all(pair.map(geometry));
    compareGeometry(shapes[0], shapes[1], name);
    const buffers = await Promise.all(pair.map((page, i) => page.screenshot({
        path: path.join(output, 'screenshots', (i ? 'current-' : 'reference-') + name + '.png'),
        animations: 'disabled'})));
    const regions = process.env.BASELINE_DROPDOWN_CHANGE === '1' ? (await Promise.all(pair.map(page => page.locator('select, input[list], input[data-hospital-list], .hospital-select, .filter-trigger, .filter-menu, .trace-select-trigger').evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect().toJSON()).filter(r => r.width && r.height))))).flat() : [];
    // The authorized page moves change generated link text and QR modules, while their geometry is compared above.
    if (process.env.BASELINE_LINK_CHANGE === '1') regions.push(...(await Promise.all(pair.map(page => page.locator('#survey-public-url, #survey-public-qr svg').evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect().toJSON()).filter(r => r.width && r.height))))).flat());
    const result = {name, changedPixelsPercent: pixelDifference(...buffers), geometry: shapes[0]};
    if (regions.length) {
        result.intentionalDropdownRegions = regions;
        result.withoutDropdownsChangedPixelsPercent = pixelDifference(...buffers, regions);
    }
    if (result.changedPixelsPercent > pixelLimit && await pair[0].locator('.leaflet-tile-pane').count()) {
        // Live provider tiles can fail or fade at different times. Keep full captures,
        // then compare the complete interface/route overlays without those remote pixels.
        await Promise.all(pair.map(page => page.locator('.leaflet-tile-pane').evaluateAll(elements => {
            elements.forEach(element => {element.style.visibility = 'hidden';});
        })));
        const uiBuffers = await Promise.all(pair.map((page, i) => page.screenshot({
            path: path.join(output, 'screenshots', (i ? 'current-' : 'reference-') + name + '-without-live-tiles.png'),
            animations: 'disabled'})));
        result.withoutLiveTilesChangedPixelsPercent = pixelDifference(...uiBuffers, regions);
        await Promise.all(pair.map(page => page.locator('.leaflet-tile-pane').evaluateAll(elements => {
            elements.forEach(element => {element.style.visibility = '';});
        })));
    }
    captures.push(result);
}
async function navigate(pair, file, theme, bases, locale) {
    await Promise.all(pair.map(async (page, i) => {
        if (fullStyles) await page.evaluate(async () => {
            if (window.HospitalAuth?.current) await HospitalApi.post('auth.php?action=heartbeat');
        });
        // Use the map's existing motion preference so Leaflet pans settle identically.
        if (/trace-map\.(html|php)$/.test(file)) await page.emulateMedia({reducedMotion: 'reduce'});
        const target = i ? currentPath(file) : file;
        const response = await page.goto(pageUrl(bases[i], target), {waitUntil: 'domcontentloaded'});
        // Navigating an already-open participant URL can be a same-document hash navigation.
        if (response) assert.equal(response.status(), 200, file + ' response');
        assert.equal(new URL(page.url()).pathname, '/' + target, file + ' unexpected redirect');
        if (i && response) {
            report.renderedPages ??= {};
            report.renderedPages['public/' + target] = await response.text();
        }
        await ready(page);
        await page.evaluate(async ({theme, locale}) => {
            HospitalTheme.preview(theme);
            if (locale) await HospitalI18n.preview(locale);
        }, {theme, locale});
        // Freeze decorative CSS animations, retaining both their selectors and static computed style.
        await page.addStyleTag({content: '*,*::before,*::after{animation-delay:0s!important;animation-duration:0s!important;transition:none!important}'});
        await page.waitForTimeout(200);
    }));
}
async function pilotStates(pair, file, name, locale) {
    const capture = async (suffix, open, close) => {
        await Promise.all(pair.map(open));
        await capturePair(pair, name + suffix, report.interactions);
        await Promise.all(pair.map(close));
    };
    const settings = /settings\.php$/.test(file);
    const profile = settings ? '.user-control' : '.profile-button';
    await capture('-profile', page => page.locator(profile).click(), page => page.locator(profile).click());
    if (await pair[0].locator('.search-action').count())
        await capture('-search', async page => {
            await page.locator('.search-action').click();
            await page.locator('.search-input').fill(locale === 'en' ? 'patients' : 'pacientes');
            await page.waitForFunction(() => document.querySelectorAll('#search-results-list .search-result').length > 0);
        }, page => page.locator('.search-action').click());
    if (!settings) {
        await capture('-card-hover', page => page.locator('.feature-card').first().hover(), page => page.mouse.move(0, 0));
        return;
    }
    await capture('-center', page => page.locator('#settings-center-button').click(), page => page.keyboard.press('Escape'));
    await capture('-field-focus', page => page.locator('#settings-name').focus(), page => page.locator('#settings-name').blur());
    await capture('-field-hover', page => page.locator('#settings-name').hover(), page => page.mouse.move(0, 0));
    await capture('-specialty-hover', page => page.locator('.specialty-add').hover(), page => page.mouse.move(0, 0));
    await capture('-specialty', page => page.locator('.specialty-add').click(), page => page.keyboard.press('Escape'));
    // This confirmation has no opener when the fictional account has no specialty.
    await capture('-specialty-remove', page => page.locator('#specialty-remove-dialog').evaluate(dialog => dialog.showModal()), page => page.keyboard.press('Escape'));
    await capture('-sessions', async page => {
        await page.locator(profile).click(); await page.locator('[data-sessions]').click();
        await page.waitForFunction(() => document.querySelector('#session-list').getAttribute('aria-busy') === 'false');
    }, page => page.keyboard.press('Escape'));
    await capture('-notifications', async page => {
        await page.locator(profile).click(); await page.locator('[data-security-notifications]').click();
    }, page => page.keyboard.press('Escape'));
    await Promise.all(pair.map(page => page.locator('#privacy-tab').click()));
    await capturePair(pair, name + '-privacy', report.interactions);
    await capture('-password', page => page.locator('.reset-password').click(), page => page.keyboard.press('Escape'));
}
(async () => {
    fs.mkdirSync(path.join(output, 'screenshots'), {recursive: true});
    if (fullStyles) fs.mkdirSync(path.join(output, 'computed-styles'), {recursive: true});
    const browser = await chromium.launch({channel: 'chrome', headless: true});
    try {
        const currentBase = process.env.BASELINE_CURRENT_URL || 'http://127.0.0.1:8091';
        const proxy = process.env.BASELINE_REFERENCE_PROXY_URL;
        const addedBaseSheet = process.env.BASELINE_ADDED_BASE_SHEET === '1';
        assert(!addedBaseSheet || (fullStyles && proxy), 'An empty base-sheet counterpart requires the strict same-origin gate');
        const bases = [proxy || referenceCss ? currentBase : process.env.BASELINE_REFERENCE_URL || 'http://127.0.0.1:8092', currentBase];
        const contexts = [];
        const requestedMatrix = {sizes: matrix, locales, themes: ['dark', 'light'], pixelLimit, fullStyles, addedBaseSheet,
            ignoreCustomProperties, exactPixels, pilotStates: process.env.BASELINE_PILOT_STATES === '1'};
        if (process.argv.includes('--resume') && report.matrix) assert.deepEqual(report.matrix, requestedMatrix, 'Resume matrix changed');
        report.matrix = requestedMatrix;
        report.browser = browser.version();
        report.externalTileFixture = fullStyles;
        report.referenceBaseSheetPlaceholder = addedBaseSheet;
        report.sessionDataFixture = process.env.BASELINE_PILOT_STATES === '1';
        for (const [copy, base] of bases.entries()) {
            const staff = await browser.newContext({viewport: {width: 1440, height: 900}, locale: 'es-UY', timezoneId: 'America/Montevideo'});
            const publicContext = await browser.newContext({viewport: {width: 1440, height: 900}, locale: 'es-UY', timezoneId: 'America/Montevideo'});
            if (referenceCss && copy === 0) for (const context of [staff, publicContext]) {
                // CSS-only changes use the same live HTML/API/assets in both contexts.
                await context.route(url => url.origin === new URL(base).origin && url.pathname.endsWith('.css'), async route => {
                    const relative = new URL(route.request().url()).pathname.slice(1);
                    const file = path.resolve(referenceCss, relative);
                    assert(file.startsWith(path.resolve(referenceCss) + path.sep), 'Reference stylesheet path');
                    if (!fs.existsSync(file)) return route.continue();
                    await route.fulfill({status: 200, contentType: 'text/css', body: fs.readFileSync(file)});
                });
            }
            if (proxy && copy === 0) for (const context of [staff, publicContext]) {
                // Present both copies at one browser origin, so real QR/link data stays equal.
                await context.route(base + '/**', async route => {
                    const request = route.request();
                    if (addedBaseSheet && new URL(request.url()).pathname === '/src/css/base.css') {
                        await route.fulfill({status: 200, contentType: 'text/css', body: '/* Empty reference counterpart for the authorized new base sheet. */'});
                        return;
                    }
                    const response = await route.fetch({url: request.url().replace(base, proxy), maxRedirects: 0});
                    if (addedBaseSheet && request.isNavigationRequest() && response.status() === 200 &&
                        response.headers()['content-type']?.includes('text/html')) {
                        const html = await response.text();
                        assert(!html.includes('css/base.css'), 'Reference already has the new base sheet');
                        assert(html.includes('<link rel="stylesheet"'), 'Reference must have a stylesheet insertion point');
                        // Compare every computed property, including the new non-rendered link.
                        // Only the candidate receives real base-sheet rules; the reference stays empty.
                        const body = html.replace('<link rel="stylesheet"',
                            '<link rel="stylesheet" href="' + base + '/src/css/base.css">\n  <link rel="stylesheet"');
                        await route.fulfill({response, body});
                    } else await route.fulfill({response});
                });
            }
            if (fullStyles) for (const context of [staff, publicContext]) {
                const tile = new PNG({width: 1, height: 1}); tile.data.fill(0);
                await context.route(url => /(^|\.)(tile\.openstreetmap\.org|basemaps\.cartocdn\.com)$/.test(url.hostname),
                    route => route.fulfill({status: 200, contentType: 'image/png', body: PNG.sync.write(tile)}));
            }
            if (fullStyles) for (const context of [staff, publicContext]) {
                // Fix only wall time; timers and ordinary UI logic keep running.
                await context.addInitScript(() => {
                    const NativeDate = Date, fixed = NativeDate.parse('2026-10-22T15:00:00Z');
                    window.Date = class extends NativeDate {
                        constructor(...args) {super(...(args.length ? args : [fixed]));}
                        static now() {return fixed;}
                    };
                });
            }
            if (process.env.BASELINE_LOCAL_FONTS === '1') {
                const fonts = path.join(root, 'public/assets/vendor/fonts');
                const sources = JSON.parse(fs.readFileSync(path.join(fonts, 'sources.json')));
                const profileKey = url => new URL(url).searchParams.getAll('family').sort().join('|');
                const profiles = new Map(Object.entries(sources.profiles).map(([name,url]) => [profileKey(url),name]));
                const binaries = new Map(Object.entries(sources.fonts).map(([name,info]) => [info.source,name]));
                for (const context of [staff,publicContext]) {
                    // Reference CSS keeps its original profiles/URLs; mirror the exact downloaded binaries for bounded offline comparison.
                    await context.route('https://fonts.googleapis.com/**', route => {
                        const name = profiles.get(profileKey(route.request().url()));
                        assert(name, 'Reference font profile was mirrored');
                        let css = fs.readFileSync(path.join(fonts,name+'.css'),'utf8');
                        for (const [file,info] of Object.entries(sources.fonts)) css = css.replaceAll(file,info.source);
                        return route.fulfill({status:200,contentType:'text/css',body:css});
                    });
                    await context.route('https://fonts.gstatic.com/**', route => {
                        const name = binaries.get(route.request().url()); assert(name, 'Reference font binary was mirrored');
                        return route.fulfill({status:200,contentType:'font/woff2',body:fs.readFileSync(path.join(fonts,name)),headers:{'Access-Control-Allow-Origin':'*'}});
                    });
                }
            }
            for (const context of [staff, publicContext]) await context.route(/\/api\/v1\/settings(?:\/diagnostics\.php|\.php\?action=diagnostics)$/, route => route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify({success: true, data: {recorded: true}})}));
            if (process.env.BASELINE_PILOT_STATES === '1') for (const context of [staff, publicContext]) {
                // Independent logins have different "current session" rows. Use equal fictional display data.
                await context.route(/\/api\/v1\/auth\.php\?action=sessions$/, route => route.fulfill({
                    status: 200, contentType: 'application/json', body: JSON.stringify({success: true, data: [
                        {id: '00000000-0000-4000-8000-000000000001', is_current: true, user_agent: 'Chrome', last_seen_at: '2026-10-22T14:59:00Z'},
                        {id: '00000000-0000-4000-8000-000000000002', is_current: false, user_agent: 'Firefox', last_seen_at: '2026-10-22T14:40:00Z'}
                    ]})
                }));
            }
            const login = await staff.newPage(); await login.goto(base + '/', {waitUntil: 'domcontentloaded'}); await ready(login);
            await login.evaluate(async ({password, endpoint}) => HospitalApi.post(endpoint, {username: 'system_admin', password}), {password: env.HOSPITAL_ADMIN_PASSWORD, endpoint: base === bases[0] && !referencePhp ? 'auth/login.php' : 'auth.php?action=login'});
            await login.close(); contexts.push({staff, public: publicContext});
        }
        for (const file of selectedPages) {
            const prefix = file.replaceAll('/', '_').replace('.html', '') + '-';
            const belongs = row => matrix.some(size => row.name.startsWith(prefix + size + '-'));
            const savedStyles = (report.computedStyles || []).filter(belongs);
            const savedPixels = [...report.comparisons, ...report.interactions].filter(belongs);
            const externalFailures = (report.externalFailures || []).some(row => row.file === file);
            if (process.argv.includes('--resume') && report.comparisons.filter(belongs).length === matrix.length * locales.length * 2 &&
                !externalFailures && savedPixels.every(row => (row.withoutLiveTilesChangedPixelsPercent ?? row.withoutDropdownsChangedPixelsPercent ?? row.changedPixelsPercent) <= pixelLimit) &&
                (!fullStyles || (savedStyles.length && savedStyles.every(row => row.differences === 0)))) continue;
            report.comparisons = report.comparisons.filter(row => !belongs(row));
            report.interactions = report.interactions.filter(row => !belongs(row));
            if (report.computedStyles) report.computedStyles = report.computedStyles.filter(row => !belongs(row));
            report.consoleErrors = report.consoleErrors.filter(row => row.file !== file);
            if (report.externalFailures) report.externalFailures = report.externalFailures.filter(row => row.file !== file);
            const isPublic = /^index\.(html|php)$/.test(file) || file.includes('register-restore/') || /survey-user\.(html|php)$/.test(file);
            const pair = await Promise.all(contexts.map(context => context[isPublic ? 'public' : 'staff'].newPage()));
            pair.forEach((page, i) => {
                if (fullStyles) {
                    const pending = new Set();
                    pendingApi.set(page, pending);
                    page.on('request', request => {if (new URL(request.url()).origin === new URL(bases[i]).origin && request.url().includes('/api/v1/')) pending.add(request);});
                    page.on('requestfinished', request => pending.delete(request));
                    page.on('requestfailed', request => pending.delete(request));
                }
                page.on('pageerror', error => report.errors.push({file, copy: i, message: error.message}));
                page.on('console', message => {if (message.type() === 'error') report.consoleErrors.push({file, copy: i, message: message.text().slice(0, 200)});});
                page.on('response', response => {
                    const url = new URL(response.url());
                    if (url.origin === new URL(bases[i]).origin && response.status() >= 400 &&
                        /\/(src|assets)\//.test(url.pathname) && /\.(css|js|mjs|wasm|svg|png|jpe?g|ttf|woff2?)$/.test(url.pathname)) {
                        report.assetErrors ??= [];
                        report.assetErrors.push({file, copy: i, status: response.status(), path: url.pathname});
                    }
                    if (response.status() >= 400 && !url.hostname.match(/^(127\.0\.0\.1|localhost)$/)) {
                        report.externalFailures ??= [];
                        report.externalFailures.push({file, copy: i, status: response.status(), url: url.origin + url.pathname});
                    }
                });
            });
            for (const size of matrix) {
                const viewport = sizes[size];
                await Promise.all(pair.map(page => page.setViewportSize(viewport)));
                for (const locale of locales) for (const theme of ['dark', 'light']) {
                    await navigate(pair, file, theme, bases, locale);
                    const name = file.replaceAll('/', '_').replace('.html', '') + '-' + size + '-' + theme + (locale ? '-' + locale : '');
                    await capturePair(pair, name, report.comparisons);
                    const toggle = pair[0].locator('.sidebar-action');
                    if (await toggle.count()) {
                        await Promise.all(pair.map(page => page.locator('.sidebar-action').click()));
                        await pair[0].waitForTimeout(200);
                        await capturePair(pair, name + '-expanded', report.interactions);
                        await Promise.all(pair.map(page => page.locator('.sidebar-action').click()));
                        await pair[0].waitForTimeout(200);
                    }
                    if (process.env.BASELINE_PILOT_STATES === '1' && /(?:main-menu|settings)\.php$/.test(file))
                        await pilotStates(pair, file, name, locale);
                    if (/ambulances\.(html|php)$/.test(file)) {
                        await Promise.all(pair.map(page => page.locator('#new-ambulance').click()));
                        await capturePair(pair, name + '-dialog', report.interactions);
                        await Promise.all(pair.map(page => page.locator('#cancel-ambulance').click()));
                    }
                    if (/(accounts|documents)\.(html|php)$/.test(file)) {
                        if (/documents\.(html|php)$/.test(file))
                            await Promise.all(pair.map(page => page.locator('#listView').click()));
                        const selector = /accounts\.(html|php)$/.test(file) ? '#account-state-button' : '#documentTypeButton';
                        if (await pair[0].locator(selector).count()) {
                            await Promise.all(pair.map(page => page.locator(selector).first().click()));
                            await capturePair(pair, name + '-dropdown', report.interactions);
                        }
                    }
                    if (/trace-view\.(html|php)$/.test(file)) {
                        for (const [button, dialog] of [['#edit-transfer', '#edit-dialog'], ['#update-status', '#status-dialog']]) {
                            await Promise.all(pair.map(async page => {
                                await page.locator(button).click(); await page.locator(dialog + '[open]').waitFor();
                            }));
                            await capturePair(pair, name + dialog, report.interactions);
                            await Promise.all(pair.map(page => page.locator(dialog + ' [data-close-dialog]').first().click()));
                        }
                    }
                    if (/survey-view\.(html|php)$/.test(file)) {
                        await Promise.all(pair.map(async page => {
                            await page.locator('[data-share-survey]').first().click(); await page.locator('.survey-links-dialog[open]').waitFor();
                        }));
                        await capturePair(pair, name + '-share', report.interactions);
                        await Promise.all(pair.map(page => page.locator('[data-close-links]').click()));
                    }
                    if (/survey-new\.(html|php)$/.test(file)) {
                        await Promise.all(pair.map(async page => {
                            await page.locator('#read-form').click(); await page.locator('#builder-form-dialog[open]').waitFor();
                        }));
                        await capturePair(pair, name + '-preview', report.interactions);
                        await Promise.all(pair.map(page => page.locator('#builder-form-dialog [data-close-form]').first().click()));
                    }
                }
            }
            await Promise.all(pair.map(page => page.close()));
            fs.writeFileSync(reportPath, JSON.stringify(report, null, 2) + '\n');
            console.log('Compared ' + file + ': both themes; ' + matrix.join('/') + '; ' + locales.filter(Boolean).join('/') + '.');
        }
        assert.equal(report.errors.length, 0, JSON.stringify(report.errors));
        assert.equal(report.assetErrors?.length || 0, 0, 'Missing local assets');
        if (fullStyles) {
            if (process.env.BASELINE_PILOT_STATES === '1') assert.equal(report.consoleErrors.length, 0, 'Pilot console errors');
            const errors = new Map();
            for (const row of report.consoleErrors) {
                const key = row.file + ':' + row.message.replace(/\brequest_id: [a-f0-9]{24}\b/g, 'request_id: [id]');
                errors.set(key, (errors.get(key) || 0) + (row.copy ? 1 : -1));
            }
            assert([...errors.values()].every(count => count <= 0), 'New console errors in candidate');
        }
        assert.equal(report.comparisons.length, report.pages * matrix.length * locales.length * 2);
        if (fullStyles) {
            assert.equal(report.computedStyles.length, report.comparisons.length + report.interactions.length);
            assert.equal(report.computedStyles.reduce((sum, row) => sum + row.differences, 0), 0, 'Computed-style differences; inspect private snapshots/report');
        }
        for (const row of [...report.comparisons, ...report.interactions])
            assert((row.withoutLiveTilesChangedPixelsPercent ?? row.withoutDropdownsChangedPixelsPercent ?? row.changedPixelsPercent) <= pixelLimit,
                row.name + ' exceeds the interface pixel tolerance');
    } finally {
        fs.writeFileSync(reportPath, JSON.stringify(report, null, 2) + '\n');
        fs.writeFileSync(path.join(output, 'rendered-pages.json'), JSON.stringify(report.renderedPages ?? {}, null, 2) + '\n');
        await browser.close();
    }
    const maximum = Math.max(...[...report.comparisons, ...report.interactions].map(row =>
        row.withoutLiveTilesChangedPixelsPercent ?? row.withoutDropdownsChangedPixelsPercent ?? row.changedPixelsPercent));
    console.log(`${report.comparisons.length} page comparisons and ${report.interactions.length} interaction comparisons; maximum pixel difference ${maximum}% (limit ${pixelLimit}%).`);
    if (fullStyles) console.log('Computed-style differences: ' + report.computedStyles.reduce((sum, row) => sum + row.differences, 0));
})().catch(error => {console.error(error.message); process.exitCode = 1;});
