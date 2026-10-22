"""Read-only simplification inventory; complements the existing test runners.

PHP uses token_get_all without loading application code. Similarity is a lexical
screening aid, not evidence of interchangeable behavior. Runtime coverage comes
only from sanitized --contracts observations, never from a test-count estimate.
"""
import argparse
from collections import Counter
from difflib import SequenceMatcher
import hashlib
from itertools import combinations
import json
import os
from pathlib import Path
import re
import subprocess
from source_inventory import inventory

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('HOSPITAL_TEST_PHP', '/Applications/XAMPP/xamppfiles/bin/php')
CATALOGS = ['ambulances', 'destinations', 'doctors', 'patients', 'personnel', 'routes']
DOMAIN_WORDS = {
    'ambulances': r'ambulances?|ambulancia[s]?',
    'destinations': r'destinations?|destinos?|facilit(?:y|ies)',
    'doctors': r'doctors?|doctores?', 'patients': r'patients?|pacientes?',
    'personnel': r'personnel', 'routes': r'routes?|rutas?',
}
BRANCHES = {'T_IF', 'T_ELSEIF', 'T_FOR', 'T_FOREACH', 'T_WHILE', 'T_DO', 'T_CASE',
            'T_CATCH', 'T_MATCH', 'T_COALESCE', 'T_BOOLEAN_AND', 'T_BOOLEAN_OR',
            'T_LOGICAL_AND', 'T_LOGICAL_OR', 'T_LOGICAL_XOR'}


def php_sources(root):
    paths = sorted(p for area in ['backend', 'public'] for p in (root / area).rglob('*.php')
                   if 'vendor' not in p.parts and p.name != 'local.php')
    code = r'''$result=[];
    foreach(json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR) as $path){
        $line=1;$tokens=[];
        foreach(token_get_all(file_get_contents($path),TOKEN_PARSE) as $token){
            $kind=is_array($token)?token_name($token[0]):'';
            $text=is_array($token)?$token[1]:$token;
            if(!in_array($kind,['T_WHITESPACE','T_COMMENT','T_DOC_COMMENT'],true))
                $tokens[]=[$kind,$text,$line];
            $line+=substr_count($text,"\n");
        }
        $result[$path]=$tokens;
    }echo json_encode($result,JSON_THROW_ON_ERROR);'''
    result = subprocess.run([PHP, '-r', code], input=json.dumps([str(p) for p in paths]),
                            text=True, capture_output=True, check=True)
    return {str(Path(path).relative_to(root)): tokens for path, tokens in json.loads(result.stdout).items()}


def text(tokens):
    return ''.join(token[1] for token in tokens)


def arguments(tokens, start):
    """Split a call's arguments using PHP's real lexical delimiters."""
    parts, current, depth = [], [], 0
    for token in tokens[start + 1:]:
        structural = token[0] == '' or token[0] in {'T_CURLY_OPEN', 'T_DOLLAR_OPEN_CURLY_BRACES'}
        value = token[1] if structural else ''
        if value == ')' and depth == 0:
            return parts + [current]
        if value == ',' and depth == 0:
            parts.append(current); current = []
            continue
        if value in {'(', '[', '{', '${'}:
            depth += 1
        elif value in {')', ']', '}'}:
            depth -= 1
        current.append(token)
    return []


def functions(tokens):
    result = []
    for index, token in enumerate(tokens):
        if token[0] != 'T_FUNCTION':
            continue
        name_index = index + 1 + (tokens[index + 1][1] == '&')
        name = tokens[name_index][1] if tokens[name_index][0] == 'T_STRING' else '(closure)'
        opening = next((j for j in range(name_index, len(tokens)) if tokens[j][1] in {'{', ';'}), None)
        if opening is None or tokens[opening][1] == ';':
            continue
        depth = 1
        for end in range(opening + 1, len(tokens)):
            kind, value, _ = tokens[end]
            if kind not in {'', 'T_CURLY_OPEN', 'T_DOLLAR_OPEN_CURLY_BRACES'}:
                continue
            depth += (value in {'{', '${'}) - (value == '}')
            if depth == 0:
                result.append(dict(name=name, start=index, end=end, line=token[2],
                                   end_line=tokens[end][2], lines=tokens[end][2] - token[2] + 1))
                break
    return result


def scope(spans, index):
    matches = [span for span in spans if span['start'] <= index <= span['end']]
    return min(matches, key=lambda span: span['end'] - span['start'])['name'] if matches else '(file)'


def metrics(root, sources):
    terminators, branches, ternaries = 0, 0, 0
    lengths, spans, windows = [], [], Counter()
    for path, tokens in sources.items():
        lengths.extend(len(line) for line in (root / path).read_text().splitlines())
        spans.extend(dict(path=path, **span) for span in functions(tokens))
        values = [(token[0], token[1]) for token in tokens]
        windows.update(tuple(values[i:i + 16]) for i in range(max(0, len(values) - 15)))
        terminators += sum(token[1] == ';' and token[0] == '' for token in tokens)
        branches += sum(token[0] in BRANCHES for token in tokens)
        # Nullable type declarations also use '?'; exclude their variable/type contexts.
        for i, token in enumerate(tokens[:-2]):
            if token[:2] == ['', '?'] and tokens[i - 1][1] != ':' and tokens[i + 2][0] != 'T_VARIABLE':
                ternaries += 1
    return dict(php_files=len(sources), significant_tokens=sum(len(t) for t in sources.values()),
                semicolon_tokens=terminators, decision_keyword_operator_tokens=branches,
                function_declarations=sum(t[0] in {'T_FUNCTION', 'T_FN'} for ts in sources.values() for t in ts),
                named_function_declarations=sum(span['name'] != '(closure)' for span in spans),
                ternary_candidates=ternaries, lines=len(lengths), lines_over_120=sum(n > 120 for n in lengths),
                maximum_function_lines=max(span['lines'] for span in spans),
                longest_functions=sorted(spans, key=lambda span: span['lines'], reverse=True)[:12],
                repeated_16_token_groups=sum(n > 1 for n in windows.values()),
                repeated_16_token_extra_occurrences=sum(n - 1 for n in windows.values() if n > 1))


def inventories(sources):
    exceptions, inserts, calls, validators, preambles = [], [], [], [], []
    audit_helpers = {'users_audit': 2, 'transfer_audit': 2, 'documents_audit': 2,
                     'patients_audit': 3, 'password_replace_password': 4, 'settings_changed': 2}
    for path, tokens in sources.items():
        spans = functions(tokens)
        for span in spans:
            if re.search(r'(?:_input_|^settings_text$|^(?:id|version)$)', span['name']):
                body = tokens[span['start']:span['end'] + 1]
                validators.append(dict(path=path, function=span['name'], line=span['line'], lines=span['lines'],
                                       token_hash=hashlib.sha256(text(body).encode()).hexdigest()))
        for i, token in enumerate(tokens):
            kind, value, line = token
            where = dict(path=path, line=line, function=scope(spans, i))
            if kind == 'T_THROW' and i + 3 < len(tokens) and tokens[i + 1][0] == 'T_NEW' and tokens[i + 2][1].split('\\')[-1] == 'ApiException':
                args = arguments(tokens, i + 3)
                message = text(args[2]) if len(args) > 2 else '(missing)'
                exceptions.append(dict(where, status=text(args[0]), code=text(args[1]), message=message,
                                       translates_at_throw=bool(re.search(r'I18n::t\(', message))))
            if kind == 'T_CONSTANT_ENCAPSED_STRING' and re.search(r'INSERT\s+INTO\s+audit_events\b', value, re.I):
                inserts.append(dict(where, sql=value))
            if value in audit_helpers and i + 1 < len(tokens) and tokens[i + 1][1] == '(' and tokens[i - 1][0] != 'T_FUNCTION':
                args = arguments(tokens, i + 1); position = audit_helpers[value]
                if len(args) > position:
                    expression = text(args[position])
                    literals = re.findall(r"['\"]([a-z][a-z_.-]+)['\"]", expression)
                    calls.append(dict(where, helper=value, action_expression=expression, action_literals=literals))
        if path.startswith('backend/api/v1/'):
            switch = next((i for i, t in enumerate(tokens) if t[0] == 'T_SWITCH'), len(tokens))
            preambles.append(dict(path=path, tokens=switch, method_rules=re.findall(r'Request::method\([^;]+;', text(tokens)),
                                  preamble_hash=hashlib.sha256(text(tokens[:switch]).encode()).hexdigest()))
    return dict(exceptions=exceptions, exceptions_without_direct_translation=[e for e in exceptions if not e['translates_at_throw']],
                audit_insert_sites=inserts, audit_calls=calls, validators=validators, dispatcher_preambles=preambles)


def normalize(value, domain):
    return re.sub(DOMAIN_WORDS[domain], 'DOMAIN', value, flags=re.I)


def similarity(sources):
    php = {domain: [token[1] for token in sources['backend/php/modules/' + domain + '.php']] for domain in CATALOGS}
    # Keep string/template literals atomic. Regex syntax is only lexically approximated.
    lexer = re.compile(r'''"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|`(?:\\.|[^`\\])*`|//[^\n]*|/\*.*?\*/|[A-Za-z_$][\w$]*|\d+(?:\.\d+)?|===|!==|=>|\?\?|&&|\|\||[^\s]''', re.S)
    js = {domain: [token for token in lexer.findall((ROOT / ('public/src/js/management/' + domain + '.js')).read_text())
                   if not token.startswith(('//', '/*'))] for domain in CATALOGS}
    result = {}
    for language, groups in [('php', php), ('js', js)]:
        rows = []
        for left, right in combinations(CATALOGS, 2):
            a, b = groups[left], groups[right]
            normalized_a, normalized_b = [normalize(v, left) for v in a], [normalize(v, right) for v in b]
            rows.append(dict(left=left, right=right, raw_percent=round(100 * SequenceMatcher(None, a, b, autojunk=False).ratio(), 2),
                             domain_normalized_percent=round(100 * SequenceMatcher(None, normalized_a, normalized_b, autojunk=False).ratio(), 2)))
        result[language] = sorted(rows, key=lambda row: row['domain_normalized_percent'], reverse=True)
    return result


def action_matrix(observations):
    entries = []
    records = []
    directories = [observations] if isinstance(observations, Path) else (observations or [])
    for directory in directories:
        for path in sorted(directory.glob('*/contracts.jsonl')):
            suite = path.parent.name.rsplit('-', 1)[0]
            records.extend(dict(json.loads(line), suite=suite) for line in path.read_text().splitlines())
    for path in sorted((ROOT / 'backend/api/v1').glob('*.php')):
        content = path.read_text()
        match = re.search(r'in_array\(\$action,\s*(\[[^\]]*\]),\s*true\)', content)
        actions = re.findall(r"'([^']+)'", match[1]) if match else ['snapshot'] if path.stem == 'tracking' else ['(none)']
        for action in actions:
            seen = [row for row in records if row['domain'] == path.stem and row['action'] == action]
            signatures = {json.dumps({k: v for k, v in row.items() if k != 'suite'}, sort_keys=True) for row in seen}
            entries.append(dict(domain=path.stem, action=action, suites=sorted({row['suite'] for row in seen}),
                                methods=sorted({row['method'] for row in seen}), statuses=sorted({row['status'] for row in seen}),
                                role_contexts=sorted({','.join(row['role_context']) for row in seen}),
                                error_codes=sorted({row['error_code'] for row in seen if row['error_code']}),
                                observed_calls=len(seen), outcome_signatures=[json.loads(value) for value in sorted(signatures)],
                                successful_response_observed=any(200 <= row['status'] < 300 for row in seen),
                                rejection_observed=any(400 <= row['status'] < 500 for row in seen)))
    return dict(method='Actual requests observed through the shared integration Client; direct urllib/binary requests, separate recovery Client and browsers are not instrumented. Role context is the last successful auth payload, not an independent permission assertion.',
                entries=entries, observed_calls=len(records),
                no_shared_client_observation=[row['domain'] + ':' + row['action'] for row in entries if not row['observed_calls']],
                no_success_observed=[row['domain'] + ':' + row['action'] for row in entries if not row['successful_response_observed']],
                no_rejection_observed=[row['domain'] + ':' + row['action'] for row in entries if not row['rejection_observed']])


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--baseline', type=Path, required=True)
    parser.add_argument('--observations', type=Path, nargs='+')
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    baseline = args.baseline.resolve(); baseline.relative_to(ROOT / 'tests/.runtime')
    before, current = php_sources(baseline), php_sources(ROOT)
    changed = [path for path, tokens in current.items() if before.get(path) != tokens]
    token_changes = [path for path in changed if [(t[0], t[1]) for t in before.get(path, [])] != [(t[0], t[1]) for t in current[path]]]
    report = dict(methods={
        'php_tokens': 'PHP 8 token_get_all(TOKEN_PARSE); ignore only whitespace/comments; preserve literal strings, HTML and operator tokens.',
        'similarity': 'SequenceMatcher matching-token ratio with autojunk disabled; domain words normalized in identifiers/literals, all other literals preserved. Screening only; not percentage of safely removable code. JS tokenization is lexical, not an AST.',
        'metrics': 'PHP-only formatting-neutral tokens/declarations/decision operators and 16-token windows. Semicolons include for headers; ternaries are candidates after nullable-type filtering. Function lengths are physical spans including closures.',
        'file_exclusions': 'Same source_inventory definition: public/backend/db only; exclude vendor, local.php, .DS_Store and .gitkeep. Excludes tests, docs, runtime, storage, logs and Git. First-party artwork counts as files/bytes but not code bytes.'},
        source_before=inventory(baseline), source_after=inventory(ROOT),
        php_before=metrics(baseline, before), php_after=metrics(ROOT, current),
        token_equivalence=dict(passed=not token_changes and set(before) == set(current), changed_token_files=token_changes),
        similarity=similarity(current), inventory=inventories(current), api_coverage=action_matrix(args.observations))
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps(dict(output=str(args.output), php_files=len(current), token_equivalence=report['token_equivalence'],
                          api_actions=len(report['api_coverage']['entries']), observed_calls=report['api_coverage']['observed_calls'],
                          exception_sites=len(report['inventory']['exceptions']), audit_insert_sites=len(report['inventory']['audit_insert_sites']))))
    return int(bool(token_changes) or set(before) != set(current))


if __name__ == '__main__':
    raise SystemExit(main())
