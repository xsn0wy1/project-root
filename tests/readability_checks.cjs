/* Focused checks for the actual refactored decisions, using the existing Acorn tool. */
'use strict';
const fs = require('fs'), path = require('path'), vm = require('vm'), assert = require('assert/strict');
const acorn = require('./node_modules/acorn');
const root = path.join(__dirname, '..');
let checks = 0;
const t = (key, values = {}) => key + (Object.keys(values).length ? ' ' + JSON.stringify(values) : '');
const window = {HospitalI18n: {t}};
function load(file, name, context = {}) {
  const source = fs.readFileSync(path.join(root, 'public/src', file), 'utf8');
  const tree = acorn.parse(source, {ecmaVersion: 2022, sourceType: 'script'});
  const found = [];
  function walk(node) {
    if (!node || typeof node !== 'object') return;
    if (node.type === 'FunctionDeclaration' && node.id.name === name) found.push(node);
    if (node.type === 'VariableDeclarator' && node.id.name === name && /FunctionExpression$/.test(node.init?.type)) found.push(node.init);
    for (const value of Object.values(node)) {
      if (Array.isArray(value)) value.forEach(walk);
      else if (value?.type) walk(value);
    }
  }
  walk(tree);
  assert.equal(found.length, 1, file + ' has one ' + name);
  return vm.runInNewContext('(' + source.slice(found[0].start, found[0].end) + ')', {window, ...context});
}
function equal(actual, expected, label) {
  assert.deepEqual(actual, expected, label);
  checks++;
}
const view = 'js/surveys/survey-view.js';
const count = load(view, 'count');
for (const [value, expected] of [[null, null], [undefined, null], ['', null], [false, 0], [0, 0],
  ['0', 0], [' ', 0], [true, 1], [[], 0], ['-0', -0], [-1, null], [0.5, null], ['abc', null]]) {
  equal(count(value), expected, 'response counts preserve coercion for ' + String(value));
}
const percentage = load(view, 'answerPercentage');
for (const [total, answered, expected] of [[null, 0, null], [0, null, null], [0, 0, 0], [2, 4, 50],
  [false, 4, 0], ['', 4, 0], [undefined, 2, NaN]]) equal(percentage(total, answered), expected, 'percentage boundary');
for (const field of ['opens', 'closes']) {
  for (const enabled of [true, 1, false, null, undefined, 0, '']) {
    let dateReads = 0;
    const config = {[field + '_enabled']: enabled};
    Object.defineProperty(config, field + '_at', {get() {dateReads++; return 'fixture';}});
    const fn = load(view, field === 'opens' ? 'openingLabel' : 'closingLabel', {config: () => config, dateText: value => 'date:' + value});
    const disabled = field === 'opens' ? 'survey_view.al_publicar' : 'survey_view.sin_fecha_de_cierre';
    equal(fn(), enabled ? 'date:fixture' : enabled === false ? disabled : 'survey_view.sin_definir', field + ' tri-state');
    equal(dateReads, enabled ? 1 : 0, field + ' reads the date only when enabled');
  }
}
const selection = 'views/surveys/user-view/survey-user.js';
for (const multiple of [true, false]) {
  const fn = load(selection, 'answerSelectionCount', {current: () => ({type: multiple ? 'multiple_choice' : 'single_choice'})});
  for (const value of [undefined, null, false, 0, '', [], ['a', 'b']]) {
    const expected = multiple ? (Array.isArray(value) ? value.length : 0) : value === undefined ? 0 : 1;
    equal(fn(value), expected, 'selection count keeps undefined distinct from other empty values');
  }
}
const checked = load(selection, 'optionChecked');
for (const [multiple, value, expected] of [[true, ['a'], 'checked'], [true, [], ''], [true, 'a', ''],
  [false, 'a', 'checked'], [false, ['a'], ''], [false, undefined, ''], [false, null, '']]) {
  equal(checked({value: 'a'}, multiple, value), expected, 'choice checked state');
}
const marker = load('js/traces/trace-map-shared.js', 'markerStatus');
for (const status of ['scheduled', 'arrived', 'completed', 'cancelled']) {
  equal(marker({status, is_delayed: true}), status, 'lifecycle takes precedence over delay');
}
equal(marker({status: 'in_progress', is_delayed: true}), 'delayed', 'active delayed marker');
equal(marker({status: 'in_progress', is_delayed: false}), 'in_progress', 'active normal marker');
const choices = [{dataset: {value: 'a'}}, {dataset: {value: 'b'}}];
const initial = load('js/dropdowns.js', 'initialChoice');
const untouched = {get value() {throw new Error('Home/End must not inspect current value');}};
equal(initial({key: 'Home'}, choices, untouched), choices[0], 'Home starts at first choice');
equal(initial({key: 'End'}, choices, untouched), choices[1], 'End starts at last choice');
for (const [key, value, expected] of [['ArrowDown', 'missing', 0], ['ArrowUp', 'missing', 1], ['ArrowDown', 'b', 1]]) {
  equal(initial({key}, choices, {value}), choices[expected], 'initial dropdown keyboard choice');
}
for (const [activeIndex, direction, expected] of [[-1, 1, 0], [-1, -1, 2], [0, -1, 2], [2, 1, 0]]) {
  equal(load('api-connection/universal-search.js', 'nextSearchIndex', {activeIndex})(direction, {length: 3}), expected, 'search keyboard wrap');
}
const size = load('js/documents/document-api.js', 'size');
for (const [bytes, key, value] of [[0, 'b', 0], [false, 'b', false], [1023, 'b', 1023],
  [1024, 'kb', '1.0'], [1048576, 'mb', '1.0']]) {
  equal(size(bytes), t('document_api.value_' + key, {value0: value}), 'document size boundary');
}
const date = load('js/documents/document-api.js', 'date');
for (const value of [undefined, null, false, 0, '']) equal(date(value), '', 'empty date remains empty');
for (const [state, data, file, expected] of [
  [{pages: 0}, null, null, ''], [{pages: 0}, {archived_at: 'fixture'}, {extension: 'docx'}, 'documents_viewer.documento_archivado'],
  [{pages: 0}, {}, {extension: 'docx'}, 'documents_viewer.archivo_docx_descarga_el_archivo_para_abrirlo_en_tu_dispositivo']
]) equal(load('js/documents/documents-viewer.js', 'viewerStatus', {state, data, file})(), expected, 'shared viewer status');
equal(load('js/documents/documents-viewer.js', 'viewerStatus', {state: {pages: 2, page: 1, zoom: 100}, data: {archived_at: 'fixture'}})(),
  t('documents_viewer.pagina_value_de_value_zoom_value', {value0: 1, value1: 2, value2: 100}), 'page status keeps first priority');
console.log('PASS readability decisions: ' + checks + ' boundary, lazy-read, status and keyboard checks.');
