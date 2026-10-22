"""Compare a private application baseline with current PHP tokens and JavaScript ASTs.

Formatting-only runs accept structural equality, never regex-stripped source.
Intentional changes require exact before/after hashes and a reason per file.
Other application files must retain their bytes unless explicitly declared.
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess
import tempfile

from source_audit import php_sources, ROOT

NODE = os.environ.get('HOSPITAL_TEST_NODE', 'node')


def application_files(root):
    return {str(path.relative_to(root)): path for area in ['public', 'backend', 'db']
            for path in (root / area).rglob('*') if path.is_file()
            and 'vendor' not in path.parts and path.name not in {'.DS_Store', 'local.php', '.gitkeep'}}


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest() if path else None


def php_fingerprints(root):
    return {name: [[kind, text] for kind, text, _line in tokens]
            for name, tokens in php_sources(root).items()}


def js_fingerprints(sources):
    result = subprocess.run([NODE, str(ROOT / 'tests/js_ast.cjs')],
                            input=json.dumps(sources), text=True, capture_output=True)
    if result.returncode:
        raise RuntimeError('JavaScript AST gate failed; parser must be installed and all sources valid.\n' + result.stderr)
    return json.loads(result.stdout)


def compare(baseline, current, expected):
    before, after = application_files(baseline), application_files(current)
    php_before, php_after = php_fingerprints(baseline), php_fingerprints(current)
    js = js_fingerprints({side + ':' + name: path.read_text()
                          for side, files in [('before', before), ('after', after)]
                          for name, path in files.items() if path.suffix == '.js'})
    rows, failures = [], []
    for name in sorted(before.keys() | after.keys()):
        first, last = before.get(name), after.get(name)
        old_hash, new_hash = digest(first), digest(last)
        kind = Path(name).suffix
        equal = old_hash == new_hash
        structural = (first is not None and last is not None and
                      (php_before[name] == php_after[name] if kind == '.php' else
                       js['before:' + name] == js['after:' + name] if kind == '.js' else equal))
        declared = expected.get(name)
        if declared is not None:
            accepted = (old_hash != new_hash and bool(declared.get('reason', '').strip()) and
                        declared.get('before_sha256') == old_hash and declared.get('after_sha256') == new_hash)
            status = 'declared-change' if accepted else 'invalid-declaration'
        else:
            accepted = equal or structural
            status = 'unchanged' if equal else 'formatting-only' if structural else 'unexpected-change'
        rows.append(dict(path=name, kind=kind, status=status, structural_equal=structural,
                         before_sha256=old_hash, after_sha256=new_hash))
        if not accepted:
            failures.append(name)
    failures.extend(sorted(expected.keys() - (before.keys() | after.keys())))
    return dict(passed=not failures, php_files=len(php_after),
                js_files=sum(path.suffix == '.js' for path in after.values()),
                parser='acorn@8.15.0; ECMAScript 2022 script; location metadata excluded',
                failures=failures, files=rows)


def self_test():
    with tempfile.TemporaryDirectory(prefix='hospital-equivalence-') as temporary:
        before, after = [Path(temporary) / side for side in ['before', 'after']]
        for root in [before, after]:
            (root / 'backend').mkdir(parents=True)
            (root / 'public').mkdir()
            (root / 'db').mkdir()
        php = "<?php\n$sql = 'SELECT 1';\necho $sql;\n?>literal HTML"
        for root in [before, after]:
            (root / 'backend/probe.php').write_text(php)
        (after / 'backend/probe.php').write_text(php.replace('$sql =', '/* format */ $sql  ='))
        assert compare(before, after, {})['passed'], 'PHP trivia should compare equal'
        for changed in [php.replace('SELECT 1', 'SELECT 2'), php.replace('literal HTML', 'changed HTML')]:
            (after / 'backend/probe.php').write_text(changed)
            assert not compare(before, after, {})['passed'], 'SQL/HTML must remain significant'
        declaration = {'backend/probe.php': dict(reason='Intentional self-test change',
                       before_sha256=digest(before / 'backend/probe.php'), after_sha256=digest(after / 'backend/probe.php'))}
        assert compare(before, after, declaration)['passed']
        declaration['backend/probe.php']['after_sha256'] = 'wrong'
        assert not compare(before, after, declaration)['passed'], 'Declarations must match exact hashes'
        (after / 'backend/probe.php').write_text(php)
        samples = [('const x=1n;', 'const x = 1n; // format', True),
                   ('function f(){return 1;}', 'function f(){return\n1;}', False),
                   ('"use strict";x();', 'x();', False),
                   ('const r=/a+/gi;', 'const r=/b+/gi;', False),
                   ('const s=`a b`;', 'const s=`a  b`;', False)]
        for original, changed, equal in samples:
            (before / 'public/probe.js').write_text(original)
            (after / 'public/probe.js').write_text(changed)
            assert compare(before, after, {})['passed'] == equal, 'AST comparison lost semantics'
        try:
            js_fingerprints({'invalid.js': 'const = ;'})
        except RuntimeError:
            pass
        else:
            raise AssertionError('Parse failure must reject the batch')
    print('PASS structural gate self-checks: PHP trivia/SQL/HTML, exact declarations, JS whitespace/ASI/directives/regex/templates/BigInt and parse failure.')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--baseline', type=Path)
    parser.add_argument('--current', type=Path, default=ROOT)
    parser.add_argument('--expected-changes', type=Path)
    parser.add_argument('--format-only', action='store_true')
    parser.add_argument('--output', type=Path)
    parser.add_argument('--self-test', action='store_true')
    args = parser.parse_args()
    if args.self_test:
        self_test()
        return 0
    if not args.baseline or not args.baseline.is_dir():
        parser.error('Provide an existing private application baseline.')
    if args.format_only and args.expected_changes:
        parser.error('Formatting-only mode cannot allow intentional changes.')
    expected = json.loads(args.expected_changes.read_text()) if args.expected_changes else {}
    report = compare(args.baseline.resolve(), args.current.resolve(), expected)
    if args.output:
        args.output.write_text(json.dumps(report, indent=2) + '\n')
    print(('PASS' if report['passed'] else 'FAIL') + ': ' + str(report['php_files']) + ' PHP token streams, ' +
          str(report['js_files']) + ' JS ASTs, ' + str(len(expected)) + ' exact intentional-file declarations.')
    for name in report['failures']:
        print('FAIL', name)
    return int(not report['passed'])


if __name__ == '__main__':
    raise SystemExit(main())
