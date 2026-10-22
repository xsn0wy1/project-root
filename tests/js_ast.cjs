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

const sources = JSON.parse(fs.readFileSync(0, 'utf8'));
const result = {};
for (const [name, source] of Object.entries(sources)) {
  const tree = acorn.parse(source, {ecmaVersion: 2022, sourceType: 'script'});
  if (!process.argv.includes('--inventory')) {
    result[name] = crypto.createHash('sha256').update(JSON.stringify(normalize(tree))).digest('hex');
    continue;
  }
  const functions = [], ternaries = [], decisionChains = [];
  const line = offset => source.slice(0, offset).split('\n').length;
  function walk(node, label = '', conditional = null) {
    if (!node || typeof node !== 'object') return;
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
    const childLabel = node.type === 'VariableDeclarator' ? node.id?.name :
      node.type === 'Property' || node.type === 'MethodDefinition' ? node.key?.name : label;
    for (const value of Object.values(node)) {
      if (Array.isArray(value)) value.forEach(child => walk(child, childLabel, conditional));
      else if (value?.type) walk(value, childLabel, conditional);
    }
  }
  walk(tree);
  result[name] = {functions, nested_ternaries: ternaries, decision_chains: decisionChains};
}
process.stdout.write(JSON.stringify(result));
