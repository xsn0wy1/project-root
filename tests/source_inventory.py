"""Comparable application counts and an overlapping eight-line duplication indicator.

Excludes vendor assets, runtime data, documentation and development tests. Binary
first-party artwork is counted in files/bytes, but not in the code-only subtotal.
"""
from collections import Counter
import argparse
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CODE = {'.php', '.js', '.css', '.html', '.sql', '.json', '.example'}


def inventory(root):
    files = [p for area in ['public', 'backend', 'db'] for p in (root / area).rglob('*')
             if p.is_file() and 'vendor' not in p.parts and p.name not in {'.DS_Store', 'local.php', '.gitkeep'}]
    blocks = Counter()
    code = [p for p in files if p.suffix in CODE or p.name == '.htaccess']
    for path in code:
        lines = [' '.join(line.split()) for line in path.read_text().splitlines() if line.strip()]
        blocks.update(hashlib.sha256('\n'.join(lines[i:i+8]).encode()).hexdigest()
                      for i in range(max(0, len(lines)-7)))
    return dict(files=len(files), bytes=sum(p.stat().st_size for p in files),
                code_files=len(code), code_bytes=sum(p.stat().st_size for p in code),
                pages=sum(p.suffix in {'.html', '.php'} for p in (root / 'public').rglob('*')),
                repeated_8_line_groups=sum(n > 1 for n in blocks.values()),
                repeated_8_line_extra_occurrences=sum(n-1 for n in blocks.values() if n > 1))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--reference', type=Path, required=True)
    parser.add_argument('--output', type=Path)
    args = parser.parse_args()
    report = dict(method='Application files under public/backend/db, excluding vendor, private config and generated files. '
                       'Eight-line duplicate windows overlap; they are a comparison indicator, not removable lines.',
                  before=inventory(args.reference), after=inventory(ROOT))
    report['delta'] = {key: report['after'][key]-value for key, value in report['before'].items()}
    content = json.dumps(report, indent=2) + '\n'
    if args.output:
        args.output.write_text(content)
    print(content, end='')
