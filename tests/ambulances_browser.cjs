/* Focused fleet UI journey, reusing the existing disposable integration fixtures. */
'use strict';
const fs = require('fs'), path = require('path'), assert = require('assert/strict');
const {chromium} = require('playwright');
const runtime = process.env.HOSPITAL_FLEET_TEST_DIR || path.join(__dirname, '.runtime/stage2/functional');
const env = JSON.parse(fs.readFileSync(path.join(runtime, 'env.json')));
const actor = JSON.parse(fs.readFileSync(path.join(runtime, 'browser.json')));
assert(env.HOSPITAL_DB_NAME.endsWith('_test'));
const base = env.HOSPITAL_AUTH_TEST_URL;
const url = base + '/src/views/management/ambulances.php';
const api = action => '/api/v1/ambulances.php?action=' + action;
const stamp = String(Date.now()).slice(-7), plate = 'UI' + stamp;
const proof = {checks: [], scriptErrors: [], nativeDialogs: []};
function check(value, label) {assert(value, label); proof.checks.push(label); console.log('PASS ' + label);}
async function ready(page) {
    await page.evaluate(async () => {await HospitalI18n.ready; await HospitalAuth.ready;});
    await page.waitForFunction(() => !document.querySelector('#new-ambulance').disabled);
}
async function save(page, action, status = 200) {
    const response = page.waitForResponse(r => r.url().endsWith(api(action)) && r.request().method() === 'POST');
    await page.locator('#ambulance-form button[type=submit]').click();
    const result = await response; assert.equal(result.status(), status);
    return (await result.json()).data;
}
async function fill(page, plate, model) {
    await page.locator('#ambulance-plate').fill(plate);
    await page.locator('#ambulance-model').fill(model);
    await page.locator('#ambulance-capacity').fill('6');
    await page.locator('#ambulance-status').selectOption('OPERATIVA');
}
(async () => {
    const browser = await chromium.launch({channel: 'chrome', headless: true});
    try {
        const context = await browser.newContext({viewport: {width: 1440, height: 900}});
        const page = await context.newPage();
        page.on('pageerror', error => proof.scriptErrors.push(error.message));
        page.on('dialog', async dialog => {proof.nativeDialogs.push(dialog.type()); await dialog.dismiss();});
        await page.goto(base + '/'); await page.evaluate(() => HospitalI18n.ready);
        await page.evaluate(actor => HospitalApi.post('auth.php?action=login', actor), {username: actor.username, password: actor.password});
        await page.goto(url); await ready(page);
        // Add only fictional vehicles in the disposable schema to exercise the second page.
        await page.evaluate(async () => {
            const existing = await HospitalApi.get('ambulances.php?action=list');
            for (let i = 1; i <= 3; i++) if (!existing.some(a => a.plate === 'UITEST' + i))
                await HospitalApi.post('ambulances.php?action=create',
                    {plate: 'UITEST' + i, model: 'Vehículo ficticio ' + i, capacity: 4, operational_status: 'available'});
        });
        await page.reload(); await ready(page);
        await page.locator('#ambulance-pagination [data-page="2"]').first().click();
        check(await page.locator('#ambulance-pagination [aria-current=page]').getAttribute('data-page') === '2', 'pagination reaches the second fleet page');
        await page.locator('#new-ambulance').click();
        await fill(page, plate, 'Unidad ficticia Águila ' + stamp);
        const created = await save(page, 'create', 201);
        await page.locator('#ambulance-dialog').waitFor({state: 'hidden'});
        check(created.plate === plate, 'create closes the dialog only after persisted success');
        await page.reload(); await ready(page);
        await page.locator('#ambulance-search').fill('aguila ' + stamp);
        check(await page.locator('.ambulance-row').count() === 1, 'search retains accent-insensitive matching after reload');
        await page.locator('#ambulance-search').fill('UI ' + stamp.slice(0, 3) + ' ' + stamp.slice(3));
        check(await page.locator('.ambulance-row').count() === 1, 'compact plate search is retained');
        await page.locator('[data-select-id="' + created.id + '"]').click();
        await page.waitForFunction(() => document.querySelector('#detail-panel').getAttribute('aria-hidden') === 'false');
        check(await page.locator('#directory-panel').evaluate(e => e.inert), 'selection opens details and makes the directory inert');
        await page.locator('#close-detail').click();
        check(await page.locator('[data-select-id="' + created.id + '"]').evaluate(e => e === document.activeElement), 'closing details restores row focus');
        await page.locator('[data-select-id="' + created.id + '"]').click();
        await page.locator('#edit-from-detail').click();
        await page.locator('#ambulance-model').fill('Unidad ficticia editada');
        await page.locator('#ambulance-status').selectOption('MANTENIMIENTO');
        await save(page, 'update'); await page.locator('#ambulance-dialog').waitFor({state: 'hidden'});
        await page.reload(); await ready(page);
        await page.locator('#ambulance-search').fill(plate);
        await page.locator('[data-filter=MANTENIMIENTO]').click();
        check((await page.locator('.ambulance-row').innerText()).includes('Unidad ficticia editada'), 'edit and status filter persist after reload');
        // Real optimistic conflict: another save advances SQL after the editor was opened.
        await page.locator('[data-select-id="' + created.id + '"]').click();
        await page.locator('#edit-from-detail').click();
        await page.evaluate(async id => {
            const a = await HospitalApi.get('ambulances.php?action=get&id=' + id);
            await HospitalApi.post('ambulances.php?action=update', {id, version: a.version, plate: a.plate,
                model: 'Cambio concurrente ficticio', capacity: a.capacity, operational_status: a.operational_status});
        }, created.id);
        await page.locator('#ambulance-model').fill('Borrador conservado');
        await save(page, 'update', 409);
        check(await page.locator('#ambulance-dialog').evaluate(e => e.open) && await page.locator('#ambulance-model').inputValue() === 'Borrador conservado', 'a real version conflict retains the open editor and input');
        await page.locator('#cancel-ambulance').click();
        await page.reload(); await ready(page);
        await page.locator('#new-ambulance').click(); await fill(page, 'FAIL001', 'Entrada conservada');
        const failure = '**/api/v1/ambulances.php?action=create';
        await page.route(failure, route => route.fulfill({status: 503, contentType: 'application/json',
            body: JSON.stringify({success: false, error: {code: 'SERVICE_UNAVAILABLE', message: 'Fallo ficticio'}})}));
        await save(page, 'create', 503);
        check(await page.locator('#ambulance-model').inputValue() === 'Entrada conservada' && !(await page.locator('#ambulance-model').isDisabled()), 'a failed request retains values and releases busy controls');
        check(await page.locator('#form-error').evaluate(e => e.hidden) && await page.locator('#ambulance-toast').evaluate(e => e.hidden), 'save errors remain console-only');
        await page.screenshot({path: path.join(runtime, 'retained-input.png')});
        await page.unroute(failure); await page.locator('#cancel-ambulance').click();
        await page.locator('#ambulance-search').fill(plate);
        await page.locator('[data-select-id="' + created.id + '"]').click();
        await page.locator('#delete-from-detail').click();
        let response = page.waitForResponse(r => r.url().endsWith(api('archive')));
        await page.locator('#confirm-delete').click(); assert.equal((await response).status(), 200);
        await page.locator('#delete-dialog').waitFor({state: 'hidden'});
        check(await page.locator('#ambulance-empty').isVisible(), 'archiving removes the unit from active search');
        await page.reload(); await ready(page);
        await page.locator('#ambulance-search').fill(plate); await page.locator('[data-filter=FUERA_SERVICIO]').click();
        await page.locator('[data-select-id="' + created.id + '"]').click();
        check(await page.locator('#edit-from-detail').isHidden(), 'archived details preserve the restore-only state');
        await page.locator('#delete-from-detail').click();
        response = page.waitForResponse(r => r.url().endsWith(api('restore')));
        await page.locator('#confirm-delete').click(); assert.equal((await response).status(), 200);
        await page.locator('#delete-dialog').waitFor({state: 'hidden'});
        await page.reload(); await ready(page);
        await page.locator('#ambulance-search').fill(plate); await page.locator('[data-filter=MANTENIMIENTO]').click();
        check((await page.locator('.ambulance-row').innerText()).includes('Cambio concurrente ficticio'), 'restore retains saved metadata and prior operational status');
        await page.evaluate(() => HospitalI18n.apply('en'));
        check((await page.locator('#new-ambulance').innerText()).includes('New ambulance'), 'shared helpers retain live English bindings');
        await page.setViewportSize({width: 390, height: 844});
        await page.evaluate(() => HospitalTheme.preview('light'));
        await page.locator('#new-ambulance').click();
        await page.screenshot({path: path.join(runtime, 'mobile-light-dialog.png')});
        check(await page.locator('#ambulance-dialog').isVisible(), 'mobile light editor opens with the existing layout');
        const anonymous = await browser.newContext();
        const denied = await anonymous.request.get(url, {maxRedirects: 0});
        check(denied.status() === 303 && denied.headers().location.includes('index.php'), 'PHP views redirect anonymous requests before rendering');
        const admin = await browser.newPage(); await admin.goto(base + '/');
        await admin.evaluate(() => HospitalI18n.ready);
        await admin.evaluate(password => HospitalApi.post('auth.php?action=login', {username: 'system_admin', password}), env.HOSPITAL_ADMIN_PASSWORD);
        await admin.goto(url);
        check(new URL(admin.url()).pathname.endsWith('/management.php'), 'admin without dispatcher is redirected by the PHP page guard');
        check(proof.nativeDialogs.length === 0 && proof.scriptErrors.length === 0, 'no native alerts/validation popups or uncaught script errors');
    } finally {
        fs.writeFileSync(path.join(runtime, 'browser-report.json'), JSON.stringify(proof, null, 2) + '\n');
        await browser.close();
    }
})().catch(error => {console.error(error.stack); process.exitCode = 1;});
