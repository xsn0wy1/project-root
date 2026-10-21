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
const root = path.join(__dirname, '..');
const runtime = path.join(__dirname, '.runtime/stage1');
const output = process.env.BASELINE_OUTPUT_DIR ? path.resolve(process.env.BASELINE_OUTPUT_DIR) : runtime;
const env = JSON.parse(fs.readFileSync(path.join(runtime, 'env.json')));
const selectedFiles = (process.env.BASELINE_FILES || '').split(',').filter(Boolean);
const phpViews = new Set(['ambulances', 'patients', 'doctors', 'personnel', 'destinations', 'routes', 'management', 'accounts']);
const records = JSON.parse(fs.readFileSync(path.join(runtime, 'records.json')));
const pages = fs.readdirSync(path.join(runtime, 'reference/public'), {recursive: true})
    .filter(file => file.endsWith('.html')).sort();
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
    await page.waitForTimeout(250);
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
        if ([0, 1, 2].some(c => Math.abs(left.data[i+c]-right.data[i+c]) > 12)) changed++;
    return Math.round(100000*changed/(left.width*left.height))/1000;
}
async function capturePair(pair, name, captures) {
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
    if (result.changedPixelsPercent > 0.1 && await pair[0].locator('.leaflet-tile-pane').count()) {
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
async function navigate(pair, file, theme, bases) {
    await Promise.all(pair.map(async (page, i) => {
        // Use the map's existing motion preference so Leaflet pans settle identically.
        if (file.endsWith('trace-map.html')) await page.emulateMedia({reducedMotion: 'reduce'});
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
        await page.evaluate(theme => HospitalTheme.preview(theme), theme);
        // Freeze decorative CSS animations, retaining both their selectors and static computed style.
        await page.addStyleTag({content: '*,*::before,*::after{animation-delay:0s!important;animation-duration:0s!important;transition:none!important;caret-color:transparent!important}'});
        await page.waitForTimeout(200);
    }));
}
(async () => {
    fs.mkdirSync(path.join(output, 'screenshots'), {recursive: true});
    const browser = await chromium.launch({channel: 'chrome', headless: true});
    try {
        const bases = ['http://127.0.0.1:8092', 'http://127.0.0.1:8091'];
        const contexts = [];
        for (const base of bases) {
            const staff = await browser.newContext({viewport: {width: 1440, height: 900}, locale: 'es-UY', timezoneId: 'America/Montevideo'});
            const publicContext = await browser.newContext({viewport: {width: 1440, height: 900}, locale: 'es-UY', timezoneId: 'America/Montevideo'});
            for (const context of [staff, publicContext]) await context.route(/\/api\/v1\/settings(?:\/diagnostics\.php|\.php\?action=diagnostics)$/, route => route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify({success: true, data: {recorded: true}})}));
            const login = await staff.newPage(); await login.goto(base + '/', {waitUntil: 'domcontentloaded'}); await ready(login);
            await login.evaluate(async ({password, endpoint}) => HospitalApi.post(endpoint, {username: 'system_admin', password}), {password: env.HOSPITAL_ADMIN_PASSWORD, endpoint: base.endsWith(':8092') ? 'auth/login.php' : 'auth.php?action=login'});
            await login.close(); contexts.push({staff, public: publicContext});
        }
        for (const file of selectedPages) {
            const prefix = file.replaceAll('/', '_').replace('.html', '') + '-';
            const belongs = row => ['desktop-', 'mobile-'].some(size => row.name.startsWith(prefix + size));
            if (!selectedFiles.length && report.comparisons.filter(belongs).length === 4) continue;
            report.comparisons = report.comparisons.filter(row => !belongs(row));
            report.interactions = report.interactions.filter(row => !belongs(row));
            const isPublic = file === 'index.html' || file.includes('register-restore/') || file.endsWith('survey-user.html');
            const pair = await Promise.all(contexts.map(context => context[isPublic ? 'public' : 'staff'].newPage()));
            pair.forEach((page, i) => {
                page.on('pageerror', error => report.errors.push({file, copy: i, message: error.message}));
                page.on('console', message => {if (message.type() === 'error') report.consoleErrors.push({file, copy: i, message: message.text().slice(0, 200)});});
                page.on('response', response => {
                    const url = new URL(response.url());
                    if (response.status() >= 400 && !url.hostname.match(/^(127\.0\.0\.1|localhost)$/)) {
                        report.externalFailures ??= [];
                        report.externalFailures.push({file, copy: i, status: response.status(), url: url.origin + url.pathname});
                    }
                });
            });
            for (const [size, viewport] of [['desktop', {width: 1440, height: 900}], ['mobile', {width: 390, height: 844}]]) {
                await Promise.all(pair.map(page => page.setViewportSize(viewport)));
                for (const theme of ['dark', 'light']) {
                    await navigate(pair, file, theme, bases);
                    const name = file.replaceAll('/', '_').replace('.html', '') + '-' + size + '-' + theme;
                    await capturePair(pair, name, report.comparisons);
                    const toggle = pair[0].locator('.sidebar-action');
                    if (await toggle.count()) {
                        await Promise.all(pair.map(page => page.locator('.sidebar-action').click()));
                        await pair[0].waitForTimeout(200);
                        await capturePair(pair, name + '-expanded', report.interactions);
                        await Promise.all(pair.map(page => page.locator('.sidebar-action').click()));
                        await pair[0].waitForTimeout(200);
                    }
                    if (file.endsWith('ambulances.html')) {
                        await Promise.all(pair.map(page => page.locator('#new-ambulance').click()));
                        await capturePair(pair, name + '-dialog', report.interactions);
                        await Promise.all(pair.map(page => page.locator('#cancel-ambulance').click()));
                    }
                    if (file.endsWith('accounts.html') || file.endsWith('documents.html')) {
                        if (file.endsWith('documents.html'))
                            await Promise.all(pair.map(page => page.locator('#listView').click()));
                        const selector = file.endsWith('accounts.html') ? '#account-state-button' : '#documentTypeButton';
                        if (await pair[0].locator(selector).count()) {
                            await Promise.all(pair.map(page => page.locator(selector).first().click()));
                            await capturePair(pair, name + '-dropdown', report.interactions);
                        }
                    }
                    if (file.endsWith('trace-view.html')) {
                        for (const [button, dialog] of [['#edit-transfer', '#edit-dialog'], ['#update-status', '#status-dialog']]) {
                            await Promise.all(pair.map(async page => {
                                await page.locator(button).click(); await page.locator(dialog + '[open]').waitFor();
                            }));
                            await capturePair(pair, name + dialog, report.interactions);
                            await Promise.all(pair.map(page => page.locator(dialog + ' [data-close-dialog]').first().click()));
                        }
                    }
                    if (file.endsWith('survey-view.html')) {
                        await Promise.all(pair.map(async page => {
                            await page.locator('[data-share-survey]').first().click(); await page.locator('.survey-links-dialog[open]').waitFor();
                        }));
                        await capturePair(pair, name + '-share', report.interactions);
                        await Promise.all(pair.map(page => page.locator('[data-close-links]').click()));
                    }
                    if (file.endsWith('survey-new.html')) {
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
            console.log('Compared ' + file + ': both themes, desktop/mobile.');
        }
        assert.equal(report.errors.length, 0, JSON.stringify(report.errors));
        assert.equal(report.comparisons.length, report.pages * 4);
        for (const row of [...report.comparisons, ...report.interactions])
            assert((row.withoutLiveTilesChangedPixelsPercent ?? row.withoutDropdownsChangedPixelsPercent ?? row.changedPixelsPercent) < 0.1,
                row.name + ' exceeds the interface pixel tolerance');
    } finally {
        fs.writeFileSync(reportPath, JSON.stringify(report, null, 2) + '\n');
        fs.writeFileSync(path.join(output, 'rendered-pages.json'), JSON.stringify(report.renderedPages ?? {}, null, 2) + '\n');
        await browser.close();
    }
    const differences = report.comparisons.filter(row => row.changedPixelsPercent > 1);
    console.log(`${report.comparisons.length} page comparisons and ${report.interactions.length} interaction comparisons; geometry/styles match. ${differences.length} pages exceed 1% pixel difference (inspect report).`);
})().catch(error => {console.error(error.message); process.exitCode = 1;});
