/* Read-only frontend inventory. Uses existing Chromium and pinned Acorn test tools.
 * CSSOM counts are candidates, never proof that moving a rule preserves the cascade.
 */
'use strict';
const assert = require('assert/strict');
const fs = require('fs'), path = require('path'), crypto = require('crypto');
const {chromium} = require('playwright');
const ast = require('./js_ast.cjs');
const args = process.argv.slice(2);
const option = (name, fallback) => args.includes(name) ? args[args.indexOf(name) + 1] : fallback;
const root = path.resolve(option('--root', path.join(__dirname, '..')));

function files(directory) {
    if (!fs.existsSync(directory)) return [];
    return fs.readdirSync(directory, {withFileTypes: true}).flatMap(entry => {
        if (['vendor', 'node_modules', '.runtime', '.git'].includes(entry.name) ||
            ['local.php', '.DS_Store', '.gitkeep'].includes(entry.name)) return [];
        const file = path.join(directory, entry.name);
        return entry.isDirectory() ? files(file) : [file];
    }).sort();
}

function selectors(text) {
    const result = [];
    let start = 0, depth = 0, quote = '';
    for (let i = 0; i < text.length; i++) {
        const char = text[i];
        if (char === '\\') {i++; continue;}
        if (quote) {if (char === quote) quote = ''; continue;}
        if (char === '"' || char === "'") quote = char;
        else if (char === '(' || char === '[') depth++;
        else if (char === ')' || char === ']') depth--;
        else if (char === ',' && depth === 0) {result.push(text.slice(start, i).trim()); start = i + 1;}
    }
    result.push(text.slice(start).trim());
    return result;
}

function declarationValues(source) {
    const values = [];
    let start = 0, depth = 0, parens = 0, quote = '', comment = false;
    for (let i = 0; i < source.length; i++) {
        const char = source[i], next = source[i + 1];
        if (comment) {if (char === '*' && next === '/') {comment = false; i++;} continue;}
        if (char === '\\') {i++; continue;}
        if (quote) {if (char === quote) quote = ''; continue;}
        if (char === '/' && next === '*') {comment = true; i++; continue;}
        if (char === '"' || char === "'") {quote = char; continue;}
        if (char === '(') parens++;
        if (char === ')') parens--;
        if (parens) continue;
        if (char === '{') {depth++; start = i + 1;}
        if (char === ';' || char === '}') {
            if (depth) {
                const declaration = source.slice(start, i).replace(/\/\*[\s\S]*?\*\//g, '').trim();
                const match = declaration.match(/^(?:--[\w-]+|[a-z-]+)\s*:\s*([\s\S]*)$/i);
                if (match) values.push(match[1]);
            }
            if (char === '}') depth--;
            start = i + 1;
        }
    }
    return values;
}

async function cssInventory(page, sources) {
    sources = sources.map(row => ({...row, values: declarationValues(row.source)}));
    return page.evaluate(sources => sources.map(({file, source, values}) => {
        const sheet = new CSSStyleSheet();
        sheet.replaceSync(source);
        const rules = [];
        let groupingRules = 0;
        function visit(list, context = []) {
            for (const rule of list) {
                if (rule.type === CSSRule.STYLE_RULE || rule.type === CSSRule.KEYFRAME_RULE) {
                    rules.push({selector: rule.selectorText || rule.keyText, context,
                        keyframe: rule.type === CSSRule.KEYFRAME_RULE,
                        declaration_text: rule.style.cssText,
                        motion_shorthands: ['animation', 'transition'].map(name =>
                            [name, rule.style.getPropertyValue(name), rule.style.getPropertyPriority(name)]).filter(([, value]) => value),
                        declarations: [...rule.style].map(name => [name, rule.style.getPropertyValue(name), rule.style.getPropertyPriority(name)])});
                } else if (rule.cssRules) {
                    groupingRules++;
                    visit(rule.cssRules, [...context, rule.cssText.slice(0, rule.cssText.indexOf('{')).trim()]);
                }
            }
        }
        visit(sheet.cssRules);
        // Count color literals and var() calls in source, preserving exact numeric spellings.
        const stripped = values.join('\n').replace(/url\([^)]*\)|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'/g, '');
        const explicit = stripped.match(/#[\da-f]{3,8}\b|\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\s*\(/gi) || [];
        const named = (stripped.match(/(?<![\w-])[a-z]+\b/gi) || []).filter(word =>
            !['inherit', 'initial', 'unset', 'revert', 'revert-layer', 'currentcolor'].includes(word.toLowerCase()) && CSS.supports('color', word));
        return {file, rules, grouping_rules: groupingRules, hardcoded_colors: explicit.length + named.length,
            explicit_colors: explicit.length, named_colors: named.length,
            var_uses: (stripped.match(/\bvar\s*\(/g) || []).length};
    }), sources);
}

function duplicates(rows, removePageScope = false, crossSheet = false) {
    const groups = new Map();
    for (const {file, rules} of rows) for (const rule of rules) {
        if (rule.keyframe) continue;
        for (let selector of selectors(rule.selector)) {
            if (removePageScope) selector = selector.replace(/html\[data-theme="light"\]\[data-theme-styles~="[^"]+"\]/g, '<light-page>');
            const key = JSON.stringify([rule.context, selector, rule.declaration_text, rule.declarations]);
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(file);
        }
    }
    const repeated = [...groups.values()].filter(items => crossSheet ? new Set(items).size > 1 : items.length > 1);
    return {groups: repeated.length, extra_occurrences: repeated.reduce((sum, items) => sum + items.length - 1, 0)};
}

function motionRows(rows) {
    return rows.flatMap(({file, rules}) => rules.flatMap(rule => {
        if (rule.keyframe) return [[file, rule.context, rule.selector, rule.declaration_text]];
        const properties = rule.declarations.filter(([name]) =>
            /^(?:-webkit-)?(?:animation|transition)(?:-|$)/.test(name));
        return properties.length || rule.motion_shorthands.length
            ? [[file, rule.context, rule.selector, rule.motion_shorthands, properties]] : [];
    }));
}

async function measure(page, sourceRoot) {
    const app = ['public', 'backend', 'db'].flatMap(area => files(path.join(sourceRoot, area)));
    const relative = file => path.relative(sourceRoot, file).split(path.sep).join('/');
    const cssFiles = app.filter(file => file.endsWith('.css'));
    const jsFiles = app.filter(file => file.endsWith('.js'));
    const css = await cssInventory(page, cssFiles.map(file => ({file: relative(file), source: fs.readFileSync(file, 'utf8')})));
    const motion = motionRows(css);
    const shared = new Set(['base.css', 'theme.css', 'responsive.css', 'dropdowns.css', 'auth-interactions.css']);
    const pageSheets = css.filter(row => !shared.has(path.basename(row.file)));
    const js = jsFiles.map(file => ({file: relative(file), source: fs.readFileSync(file, 'utf8')}));
    const analysis = js.map(row => ({file: row.file, ...ast.inventory(row.source)}));
    const functions = analysis.flatMap(row => row.functions.map(fn => ({file: row.file, ...fn})));
    const helperNames = ['setBusy', 'report', 'icon', 'toast', 'escape', 'escapeHtml', 'renderPagination', 'upsert', 'fromApi'];
    const helperDefinitions = Object.fromEntries(helperNames.map(name => [name, functions.filter(fn => fn.name === name).length]));
    const helperBindings = Object.fromEntries(helperNames.map(name => [name, analysis.flatMap(row => row.bindings).filter(binding => binding.name === name).length]));
    const lines = js.flatMap(row => row.source.split(/\r?\n/).filter(line => line.trim()));
    const templates = app.filter(file => ['.php', '.html', '.js'].includes(path.extname(file))).map(file => fs.readFileSync(file, 'utf8'));
    const views = app.filter(file => ['.php', '.html'].includes(path.extname(file))).map(file => fs.readFileSync(file, 'utf8'));
    const inline = templates.flatMap(text => text.match(/\bstyle\s*=\s*["'][^"']*["']/gi) || []);
    const wrappers = templates.reduce((sum, text) => sum + (text.match(/<i18n-text\b[^>]*\bstyle\s*=/gi) || []).length, 0);
    const viewWrappers = views.reduce((sum, text) => sum + (text.match(/<i18n-text\b[^>]*\bstyle\s*=/gi) || []).length, 0);
    const cacheBusters = new Set(app.filter(file => ['.php', '.html', '.js', '.css'].includes(path.extname(file)))
        .flatMap(file => [...fs.readFileSync(file, 'utf8').matchAll(/\?v=([\w.-]+)/g)].map(match => match[1])));
    const codeFiles = app.filter(file => ['.php', '.js', '.css', '.html', '.sql', '.json', '.example'].includes(path.extname(file)) || path.basename(file) === '.htaccess');
    const windows = new Map();
    for (const file of codeFiles) {
        const content = fs.readFileSync(file, 'utf8').split(/\r?\n/).filter(line => line.trim()).map(line => line.trim().replace(/\s+/g, ' '));
        for (let i = 0; i + 8 <= content.length; i++) {
            const key = crypto.createHash('sha256').update(content.slice(i, i + 8).join('\n')).digest('hex');
            windows.set(key, (windows.get(key) || 0) + 1);
        }
    }
    const repeated = [...windows.values()].filter(count => count > 1);
    const theme = cssFiles.find(file => path.basename(file) === 'theme.css');
    return {
        application: {files: app.length, bytes: app.reduce((sum, file) => sum + fs.statSync(file).size, 0),
            code_files: codeFiles.length, code_bytes: codeFiles.reduce((sum, file) => sum + fs.statSync(file).size, 0),
            repeated_8_line_groups: repeated.length, repeated_8_line_extra_occurrences: repeated.reduce((sum, count) => sum + count - 1, 0)},
        css: {files: cssFiles.length, bytes: cssFiles.reduce((sum, file) => sum + fs.statSync(file).size, 0),
            rules: css.reduce((sum, row) => sum + row.rules.filter(rule => !rule.keyframe).length, 0),
            grouping_rules: css.reduce((sum, row) => sum + row.grouping_rules, 0),
            keyframe_rules: css.reduce((sum, row) => sum + row.rules.filter(rule => rule.keyframe).length, 0),
            motion_rules: motion.length,
            motion_signature: crypto.createHash('sha256').update(JSON.stringify(motion)).digest('hex'),
            theme_bytes: fs.statSync(theme).size, theme_rules: css.find(row => row.file === relative(theme)).rules.length,
            theme_grouping_rules: css.find(row => row.file === relative(theme)).grouping_rules,
            hardcoded_colors: css.reduce((sum, row) => sum + row.hardcoded_colors, 0),
            explicit_colors: css.reduce((sum, row) => sum + row.explicit_colors, 0),
            named_colors: css.reduce((sum, row) => sum + row.named_colors, 0),
            var_uses: css.reduce((sum, row) => sum + row.var_uses, 0),
            cross_sheet_duplicates: duplicates(css, false, true), page_sheet_duplicates: duplicates(pageSheets, false, true),
            theme_unscoped_duplicates: duplicates(css.filter(row => row.file === relative(theme)), true),
            per_file: css.map(row => ({file: row.file, bytes: fs.statSync(path.join(sourceRoot, row.file)).size,
                rules: row.rules.filter(rule => !rule.keyframe).length, hardcoded_colors: row.hardcoded_colors, var_uses: row.var_uses}))},
        js: {files: js.length, bytes: jsFiles.reduce((sum, file) => sum + fs.statSync(file).size, 0),
            nonblank_lines: lines.length, lines_over_140: lines.filter(line => line.length > 140).length,
            lines_over_250: lines.filter(line => line.length > 250).length, helper_definitions: helperDefinitions,
            helper_total: Object.values(helperDefinitions).reduce((sum, count) => sum + count, 0), helper_bindings: helperBindings,
            getters: analysis.reduce((sum, row) => sum + row.getters, 0),
            translation_getters: analysis.reduce((sum, row) => sum + row.translation_getters, 0),
            html_references: analysis.reduce((sum, row) => sum + row.html_references, 0),
            nested_ternaries: analysis.reduce((sum, row) => sum + row.nested_ternaries.length, 0),
            nested_decision_chains: analysis.reduce((sum, row) => sum + row.decision_chains.length, 0),
            functions_over_60: functions.filter(fn => fn.lines > 60), function_count: functions.length},
        views: {inline_style_attributes: inline.length, i18n_inline_style_wrappers: viewWrappers,
            i18n_style_templates: wrappers - viewWrappers, cache_buster_strings: cacheBusters.size,
            asset_version_constants: new Set(views.flatMap(text =>
                [...text.matchAll(/\bconst ASSET_VERSION\s*=\s*['"]([^'"]+)['"]/g)].map(match => match[1]))).size,
            view_cache_buster_strings: new Set(views.flatMap(text => [...text.matchAll(/\?v=([\w.-]+)/g)].map(match => match[1]))).size}
    };
}

(async () => {
    const browser = await chromium.launch({channel: 'chrome', headless: true});
    try {
        const page = await browser.newPage();
        if (args.includes('--self-test')) {
            assert.deepEqual(selectors(':is(.a,.b), [title="a,b"], .c'), [':is(.a,.b)', '[title="a,b"]', '.c']);
            assert.deepEqual(declarationValues('.red{/*x*/color:red;content:"a;}";background:url("x;}.png")}'), ['red', '"a;}"', 'url("x;}.png")']);
            const fixture = await cssInventory(page, [{file: 'a.css', source: '.x{color:rgba(1,2,3,.375);width:var(--x)}@media(max-width:10px){.x{color:red}}'},
                {file: 'b.css', source: '.x{color:rgba(1,2,3,.375);width:var(--x)}.x{color:rgba(1,2,3,.38);width:var(--x)}'}]);
            assert.equal(fixture[0].rules.length, 2);
            assert.equal(fixture[0].hardcoded_colors, 2);
            assert.equal(fixture[0].var_uses, 1);
            assert.equal(duplicates(fixture, false, true).groups, 1, 'media context and near-equal colors stay distinct');
            const variables = await cssInventory(page, [
                {file: 'a.css', source: '.x{border:1px solid var(--a)}'},
                {file: 'b.css', source: '.x{border:1px solid var(--b)}'}]);
            assert.equal(duplicates(variables, false, true).groups, 0, 'shorthand variables remain distinct');
            const moving = await cssInventory(page, [
                {file: 'a.css', source: '.x{color:red;transition:var(--a)}'},
                {file: 'a.css', source: '.x{color:blue;transition:var(--a)}'},
                {file: 'a.css', source: '.x{color:red;transition:var(--b)}'}]);
            assert.deepEqual(motionRows([moving[0]]), motionRows([moving[1]]), 'ordinary colors do not alter motion evidence');
            assert.notDeepEqual(motionRows([moving[0]]), motionRows([moving[2]]), 'motion shorthand variables remain significant');
            const js = ast.inventory('const icon = () => [].map(() => 1); const x={get label(){return window.HospitalI18n.t("key")}}; const y=a?b?c:d:e;');
            assert.equal(js.getters, 1); assert.equal(js.decision_chains.length, 1);
            assert.equal(js.translation_getters, 1);
            assert.equal(js.functions.filter(fn => fn.name === 'icon').length, 1, 'callbacks are not helper definitions');
            console.log('PASS metrics: nested selectors/media, exact color distinctions, var(), getter/helper and nested-decision counts.');
            return;
        }
        const report = {version: 1, method: 'First-party public/backend/db only; vendor, generated data and private config excluded. CSSOM style rules (keyframes separate), ordered declarations and matching at-rule contexts; split selector lists. Color literals include hex/functions and CSS named colors including transparent; currentColor is excluded. JS uses Acorn 8.15.0; long spans include closures. Inline attributes and cache-busters are source occurrences, including templates. Duplicate windows overlap and are not removable-line counts.',
            current: await measure(page, root)};
        const baseline = option('--baseline-root', null);
        if (baseline) {
            const baselineRoot = path.resolve(baseline);
            report.baseline = await measure(page, baselineRoot);
            report.motion_declarations_equal = report.current.css.motion_signature === report.baseline.css.motion_signature;
            report.delta = {};
            for (const area of ['application', 'css', 'js', 'views']) {
                report.delta[area] = Object.fromEntries(Object.entries(report.current[area])
                    .filter(([, value]) => typeof value === 'number')
                    .map(([name, value]) => [name, value - report.baseline[area][name]]));
            }
            const countLines = source => source.split(/\r?\n/).filter(line => line.trim()).length;
            let formattingLines = 0, intentionalLines = 0;
            const jsNames = new Set([root, baselineRoot].flatMap(sourceRoot => files(path.join(sourceRoot, 'public'))
                .filter(file => file.endsWith('.js')).map(file => path.relative(sourceRoot, file))));
            for (const name of jsNames) {
                const file = path.join(root, name), oldFile = path.join(baselineRoot, name);
                if (!fs.existsSync(file)) {intentionalLines -= countLines(fs.readFileSync(oldFile, 'utf8')); continue;}
                const after = fs.readFileSync(file, 'utf8');
                if (!fs.existsSync(oldFile)) {intentionalLines += countLines(after); continue;}
                const before = fs.readFileSync(oldFile, 'utf8'), delta = countLines(after) - countLines(before);
                if (ast.fingerprint(before) === ast.fingerprint(after)) formattingLines += delta;
                else intentionalLines += delta;
            }
            report.js_line_attribution = {formatting_only_nonblank_line_delta: formattingLines,
                changed_ast_nonblank_line_delta: intentionalLines,
                method: 'AST-equal files are formatting-only; changed-AST file deltas can include formatting and behavior together.'};
        }
        const output = option('--output', null);
        if (output) fs.writeFileSync(output, JSON.stringify(report, null, 2) + '\n');
        const summary = report.current;
        console.log(JSON.stringify({css: {...summary.css, per_file: undefined}, js: {...summary.js,
            functions_over_60: summary.js.functions_over_60.length}, views: summary.views, application: summary.application}, null, 2));
    } finally {await browser.close();}
})().catch(error => {console.error(error.stack); process.exitCode = 1;});
