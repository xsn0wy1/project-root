"""Optional API outcome inventory for the existing disposable integration runners.

Records only method/action, role labels, status, error code and JSON key shapes.
Never records URLs, query values, bodies, identifiers, messages or credentials.
"""
import json
import os
from pathlib import Path
import re
from urllib.parse import parse_qs, urlsplit

ROOT = Path(__file__).resolve().parent


def record_contract(client, path, method, status, payload, headers):
    destination = os.environ.get('HOSPITAL_API_CONTRACT_LOG')
    if not destination:
        return
    parsed = urlsplit(path)
    match = re.fullmatch(r'/api/v1/([a-z-]+)\.php', parsed.path)
    if not match:
        return
    actions = parse_qs(parsed.query).get('action', [])
    action = actions[0] if len(actions) == 1 else '(none)'
    if actions and not re.fullmatch(r'[a-z-]+', action):
        action = '(invalid)'
    roles = getattr(client, '_contract_roles', ['anonymous'])
    data = payload.get('data') if isinstance(payload, dict) else None
    error = payload.get('error') if isinstance(payload, dict) else None
    keys = sorted(payload) if isinstance(payload, dict) else []
    row = {
        'domain': match[1], 'action': action, 'method': method, 'status': status,
        'role_context': roles,
        'error_code': error.get('code') if isinstance(error, dict) else None,
        'envelope_keys': keys[:64] + (['(additional keys omitted)'] if len(keys) > 64 else []),
        'data_kind': type(data).__name__,
        'data_keys': sorted(data) if isinstance(data, dict) else [],
        'allow': headers.get('Allow'),
    }
    output = Path(destination).resolve()
    output.relative_to(ROOT / '.runtime')
    fd = os.open(output, os.O_WRONLY | os.O_CREAT | os.O_APPEND, 0o600)
    try:
        os.write(fd, (json.dumps(row, sort_keys=True) + '\n').encode())
    finally:
        os.close(fd)
    if isinstance(data, dict) and isinstance(data.get('user'), dict):
        client._contract_roles = sorted(data['user'].get('roles', [])) or ['staff-without-role']
    elif (match[1] == 'auth' and action == 'logout' and status == 200) or (
            isinstance(error, dict) and error.get('code') in {'UNAUTHENTICATED', 'SESSION_EXPIRED'}):
        client._contract_roles = ['anonymous']
