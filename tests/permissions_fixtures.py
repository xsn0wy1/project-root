"""Add four fictional, single-role accounts to an existing disposable browser environment.

Existing accounts/roles are retained. Approval uses the users CLI, and normal
API saves create owned surveys and an assigned-crew transfer for scoped pages.
"""
import json
import os
from pathlib import Path
import secrets
import subprocess
from datetime import datetime, timedelta, timezone

ROOT = Path(__file__).resolve().parents[1]
RUNTIME = Path(os.environ['HOSPITAL_THEME_TEST_DIR']).resolve()
RUNTIME.relative_to(ROOT / 'tests/.runtime')
environment = dict(os.environ)
environment.update(json.loads((RUNTIME / 'env.json').read_text()))
assert environment['HOSPITAL_DB_NAME'].startswith('hospital_theme_')
os.environ.update(environment)

from auth_integration import Client, ADMIN_PASSWORD, PHP, sql
from surveys_integration import record

root = Client()
assert root.login('system_admin', ADMIN_PASSWORD)[0] == 200
records = json.loads((RUNTIME / 'records.json').read_text())
catalog = root.call('/api/v1/transfers.php?action=catalogs')[1]['data']
driver = next(person for person in catalog['crew'] if person['role_code'] == 'driver')
companion = next(person for person in catalog['crew'] if person['role_code'] == 'copilot')
crew_ids = {
    'records_manager': sql('SELECT national_id FROM staff WHERE id=' + driver['id'])[0]['national_id'],
    'staff': sql('SELECT national_id FROM staff WHERE id=' + companion['id'])[0]['national_id']
}
route = next(route for route in catalog['routes'] if route['geometry'])
accounts = []
clients = {}
for index, role in enumerate(['admin', 'dispatcher', 'records_manager', 'staff']):
    username = 'presentacion.' + role
    password = secrets.token_urlsafe(24)
    national_id = crew_ids.get(role, str(99008001 + index))
    client = Client()
    client.csrf()
    status, payload, _headers = client.call('/api/v1/auth.php?action=register', 'POST', {
        'username': username, 'password': password,
        'email': username + '@hospital.example', 'national_id': national_id
    })
    assert status == 201, payload
    subprocess.run([PHP, 'backend/php/cli/users.php', 'approve', username, role],
                   cwd=ROOT, env=environment, check=True, stdout=subprocess.DEVNULL)
    assert client.login(username, password)[0] == 200
    me = client.call('/api/v1/auth.php?action=me')[1]['data']
    assert me['user']['roles'] == [role]
    body = record()
    body.update(title='Encuesta de atención ' + role, form_title='Experiencia de atención')
    status, response, _headers = client.call('/api/v1/surveys.php?action=create', 'POST', body)
    assert status == 201, response
    survey = response['data']
    accounts.append(dict(role=role, roles=[role], username=username, password=password,
                         id=me['user']['id'], document_id=records['document']['id'],
                         survey_id=survey['id'], survey_title=survey['title']))
    clients[role] = client

departure = (datetime.now(timezone(timedelta(hours=-3))) + timedelta(days=3)).replace(microsecond=0).isoformat()
body = dict(route_template_id=route['id'], route_version=route['version'],
            ambulance_id=catalog['ambulances'][-1]['id'], driver_id=driver['id'],
            companion_id=companion['id'], patient_id=None, scheduled_at=departure,
            cargo_type='equipment', priority='normal', reason='insumos', notes='Coordinación ficticia.',
            special_equipment=False, with_family=False, interdepartmental=False, critical_patient=False,
            create_token=root.call('/api/v1/transfers.php?action=create-token')[1]['data']['create_token'])
status, response, _headers = root.call('/api/v1/transfers.php?action=create', 'POST', body)
assert status == 201, response
transfer = response['data']
for account in accounts:
    account['transfer_id'] = transfer['id']

# Exercise shared document mutations using another uploader's document, then restore metadata.
shared = records['document']['id']
document = clients['staff'].call('/api/v1/documents.php?action=get&id=' + shared)[1]['data']
original = {key: document[key] for key in ['title', 'description', 'document_date']}
original['category_id'] = document['category']['id']
original['patient_id'] = None
if document.get('patient'):
    original['patient_id'] = document['patient']['id']
body = dict(original, id=shared, version=document['version'])
status, result, _headers = clients['staff'].call('/api/v1/documents.php?action=update', 'POST', body)
assert status == 200, result
status, result, _headers = clients['staff'].call('/api/v1/documents.php?action=archive', 'POST',
                                             dict(id=shared, version=result['data']['version']))
assert status == 200, result
status, result, _headers = clients['dispatcher'].call('/api/v1/documents.php?action=restore', 'POST',
                                                  dict(id=shared, version=result['data']['version']))
assert status == 200, result
for role, client in clients.items():
    assert client.call('/api/v1/documents.php?action=get&id=' + shared)[0] == 200
    if role not in ['admin', 'dispatcher']:
        assert client.call('/api/v1/transfers.php?action=create-token')[0] == 403
    else:
        assert client.call('/api/v1/transfers.php?action=catalogs')[0] == 200
assert clients['staff'].call('/api/v1/surveys.php?action=get&id=' + accounts[1]['survey_id'])[0] == 404

fixture = RUNTIME.parent / 'role-fixture.json'
fixture.write_text(json.dumps(dict(base=environment['HOSPITAL_AUTH_TEST_URL'], accounts=accounts,
                                  transfer_id=transfer['id'], document_title=records['document']['title'])))
fixture.chmod(0o600)
print('Four single-role accounts approved with users CLI; shared documents and preserved scopes checked.')
