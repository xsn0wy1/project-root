/* Development-only AST fingerprints. Missing/wrong parsers and invalid JS fail closed. */
'use strict';
const fs = require('fs'), crypto = require('crypto');
const acorn = require('./node_modules/acorn');
if (require('./node_modules/acorn/package.json').version !== '8.15.0') throw new Error('Install the locked tests/ parser.');

function normalize(value) {
  if (typeof value === 'bigint') return {$bigint: String(value)};
  if (value instanceof RegExp) return {$regexp: value.source, flags: value.flags};
  if (Array.isArray(value)) return value.map(normalize);
  if (value && typeof value === 'object') {
    return Object.fromEntries(Object.entries(value)
      .filter(([key]) => !['start', 'end', 'loc', 'range'].includes(key))
      .map(([key, child]) => [key, normalize(child)]));
  }
  return value;
}

function fingerprint(source) {
  const tree = acorn.parse(source, {ecmaVersion: 2022, sourceType: 'script'});
  return crypto.createHash('sha256').update(JSON.stringify(normalize(tree))).digest('hex');
}

function inventory(source) {
  const tree = acorn.parse(source, {ecmaVersion: 2022, sourceType: 'script'});
  const functions = [], ternaries = [], decisionChains = [], bindings = [];
  let getters = 0, translationGetters = 0, htmlReferences = 0;
  const line = offset => source.slice(0, offset).split('\n').length;
  function walk(node, label = '', conditional = null) {
    if (!node || typeof node !== 'object') return;
    if (['Property', 'MethodDefinition'].includes(node.type) && node.kind === 'get') {
      getters++;
      const callee = node.value?.body?.body?.[0]?.argument?.callee;
      if (callee?.name === 't' || callee?.property?.name === 't') translationGetters++;
    }
    if (node.type === 'VariableDeclarator' && node.id?.type === 'Identifier') bindings.push({name: node.id.name,
      type: node.init?.type || 'uninitialized', line: line(node.start)});
    if (node.type === 'MemberExpression' && ['innerHTML', 'setHTML'].includes(node.property?.name)) htmlReferences++;
    if (/^(FunctionExpression|FunctionDeclaration|ArrowFunctionExpression)$/.test(node.type)) {
      const first = line(node.start), last = line(node.end);
      functions.push({name: node.id?.name || label || '(closure)', line: first, end_line: last, lines: last - first + 1});
      conditional = null;
    }
    if (node.type === 'ConditionalExpression') {
      if (['test', 'consequent', 'alternate'].some(key => node[key]?.type === 'ConditionalExpression')) {
        decisionChains.push({line: line(node.start), source: source.slice(node.start, node.end)});
      }
      if (conditional) ternaries.push({line: line(node.start), start: node.start, end: node.end,
        parent_start: conditional.start, parent_end: conditional.end, source: source.slice(conditional.start, conditional.end)});
      conditional = node;
    }
    for (const [key, value] of Object.entries(node)) {
      let childLabel = '';
      if (node.type === 'VariableDeclarator' && key === 'init') childLabel = node.id?.name;
      if (['Property', 'MethodDefinition'].includes(node.type) && key === 'value') childLabel = node.key?.name;
      if (node.type === 'AssignmentExpression' && key === 'right') childLabel = node.left?.name || node.left?.property?.name;
      if (Array.isArray(value)) value.forEach(child => walk(child, childLabel, conditional));
      else if (value?.type) walk(value, childLabel, conditional);
    }
  }
  walk(tree);
  return {functions, nested_ternaries: ternaries, decision_chains: decisionChains, getters,
    translation_getters: translationGetters, html_references: htmlReferences, bindings};
}

module.exports = {fingerprint, inventory};
if (require.main === module) {
  const sources = JSON.parse(fs.readFileSync(0, 'utf8'));
  const result = Object.fromEntries(Object.entries(sources).map(([name, source]) =>
    [name, process.argv.includes('--inventory') ? inventory(source) : fingerprint(source)]));
  process.stdout.write(JSON.stringify(result));
}
