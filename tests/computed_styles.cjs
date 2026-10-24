/* Development-only full computed-style gate for refactor_baseline.cjs.
 * Keep raw snapshots private: generated content can contain fixture data.
 */
'use strict';
const assert = require('assert/strict');
const crypto = require('crypto');
const fs = require('fs');
const zlib = require('zlib');

const hash = value => crypto.createHash('sha256').update(JSON.stringify(value)).digest('hex');

async function snapshot(page, {ignoreCustomProperties = false} = {}) {
    const payload = await page.evaluate(async ignoreCustomProperties => {
        const paths = new Map(), entries = [], styles = [], known = new Map();
        for (const element of document.querySelectorAll('*')) {
            const parent = element.parentElement;
            const index = parent ? [...parent.children].indexOf(element) : 0;
            const key = (parent ? paths.get(parent) + '/' : '') + element.localName + ':' + index;
            paths.set(element, key);
            for (const pseudo of ['', '::before', '::after']) {
                const computed = getComputedStyle(element, pseudo || null);
                if (pseudo && ['none', 'normal'].includes(computed.content)) continue;
                const properties = [...computed].sort()
                    .filter(name => !/^(?:-webkit-)?(?:animation|transition)(?:-|$)/.test(name))
                    .filter(name => !ignoreCustomProperties || !name.startsWith('--'))
                    .map(name => [name, computed.getPropertyValue(name)]);
                const serialized = JSON.stringify(properties);
                if (!known.has(serialized)) {
                    known.set(serialized, styles.length);
                    styles.push(properties);
                }
                entries.push({key: key + pseudo, style: known.get(serialized)});
            }
        }
        const captured = {viewport: {width: innerWidth, height: innerHeight},
            theme: document.documentElement.dataset.theme, locale: document.documentElement.lang,
            elementCount: paths.size, entries, styles};
        // Full-property JSON is large; compress before crossing the browser protocol.
        const stream = new Blob([JSON.stringify(captured)]).stream().pipeThrough(new CompressionStream('gzip'));
        const bytes = new Uint8Array(await new Response(stream).arrayBuffer());
        let binary = '';
        for (let i = 0; i < bytes.length; i += 8192) binary += String.fromCharCode(...bytes.subarray(i, i + 8192));
        return btoa(binary);
    }, ignoreCustomProperties);
    const captured = JSON.parse(zlib.gunzipSync(Buffer.from(payload, 'base64')));
    const styles = {}, elements = [], hashes = captured.styles.map(properties => {
        const fingerprint = hash(properties);
        styles[fingerprint] = properties;
        return fingerprint;
    });
    let properties = 0;
    for (const entry of captured.entries) {
        const fingerprint = hashes[entry.style];
        elements.push({key: entry.key, sha256: fingerprint});
        properties += captured.styles[entry.style].length;
    }
    return {version: 1, viewport: captured.viewport, theme: captured.theme, locale: captured.locale,
        elementCount: captured.elementCount, propertyCount: properties, elements, styles};
}

function compare(before, after) {
    assert.deepEqual(after.viewport, before.viewport, 'Snapshot viewport');
    assert.equal(after.theme, before.theme, 'Snapshot theme');
    assert.equal(after.locale, before.locale, 'Snapshot language');
    const first = new Map(before.elements.map(row => [row.key, row.sha256]));
    const last = new Map(after.elements.map(row => [row.key, row.sha256]));
    const differences = [];
    for (const key of new Set([...first.keys(), ...last.keys()])) {
        const oldHash = first.get(key), newHash = last.get(key);
        if (oldHash === newHash) continue;
        const oldStyle = new Map(before.styles[oldHash] || []), newStyle = new Map(after.styles[newHash] || []);
        differences.push({element: key, kind: !oldHash ? 'added-element' : !newHash ? 'removed-element' : 'style',
            properties: [...new Set([...oldStyle.keys(), ...newStyle.keys()])]
                .filter(name => oldStyle.get(name) !== newStyle.get(name))
                .map(name => ({name, before: oldStyle.get(name), after: newStyle.get(name)}))});
    }
    return {beforeElements: before.elementCount, afterElements: after.elementCount,
        comparedProperties: before.propertyCount, differences: differences.length, details: differences};
}

function save(file, captured) {
    fs.writeFileSync(file, zlib.gzipSync(JSON.stringify(captured)), {mode: 0o600});
}

async function selfTest() {
    const {chromium} = require('playwright');
    const browser = await chromium.launch({channel: 'chrome', headless: true});
    try {
        const page = await browser.newPage();
        await page.setContent('<style>p{color:rgb(20,30,40)}p::before{content:"probe";color:red}</style><p>Fixture</p><p hidden>Hidden</p>');
        const original = await snapshot(page);
        assert.equal(compare(original, await snapshot(page)).differences, 0);
        await page.locator('style').evaluate(node => {node.textContent += 'p{color:rgb(21,30,40)}';});
        assert(compare(original, await snapshot(page)).differences > 0, 'one-channel color changes must fail');
        const baseline = await snapshot(page);
        await page.locator('p').first().evaluate(node => {node.style.transition = 'color 9s'; node.style.animation = 'none 8s';});
        assert.equal(compare(baseline, await snapshot(page)).differences, 0, 'only motion properties are ignored');
        await page.locator('style').evaluate(node => {node.textContent += 'p::before{color:blue}p[hidden]{padding:3px}';});
        const changed = compare(baseline, await snapshot(page));
        assert(changed.details.some(row => row.element.endsWith('::before')), 'pseudo styles must fail');
        assert(changed.details.some(row => row.element.endsWith('p:1')), 'hidden elements must fail');
        const last = await snapshot(page);
        const withoutCustom = await snapshot(page, {ignoreCustomProperties: true});
        await page.locator('p').first().evaluate(node => {node.style.setProperty('--probe', '1');});
        assert(compare(last, await snapshot(page)).differences > 0, 'custom properties remain significant');
        assert.equal(compare(withoutCustom, await snapshot(page, {ignoreCustomProperties: true})).differences, 0,
            'the authorized palette gate may ignore custom properties');
        await page.locator('p').first().evaluate(node => {node.style.transition = 'none'; node.style.color = 'rgb(21,30,41)';});
        assert(compare(withoutCustom, await snapshot(page, {ignoreCustomProperties: true})).differences > 0,
            'the palette gate still detects ordinary computed properties');
        await page.locator('p').last().evaluate(node => node.remove());
        assert(compare(last, await snapshot(page)).details.some(row => row.kind === 'removed-element'));
        console.log('PASS computed-style gate: equal replay, exact colors, motion exclusion, pseudo/hidden styles, strict/opt-in custom properties and removed elements.');
    } finally {await browser.close();}
}

module.exports = {snapshot, compare, save};
if (require.main === module) selfTest().catch(error => {console.error(error.message); process.exitCode = 1;});
