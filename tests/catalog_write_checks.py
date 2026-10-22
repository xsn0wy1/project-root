"""Extra permission and atomic-write cases for the existing catalog suites."""
import json
import os
import secrets
from auth_integration import Client, PASSWORD, ROOT, SUFFIX, check, manage_account, sql
from documents_integration import admin_sql


def verify_catalog_writes(domain, dispatcher, admin, record):
    assert os.environ['HOSPITAL_DB_NAME'].endswith('_test')
    assert domain in {'destinations', 'personnel'}
    destination = domain == 'destinations'
    resource = 'facility' if destination else 'personnel'
    action_prefix = 'destination' if destination else 'personnel'
    conflict = 'DESTINATION_CONFLICT' if destination else 'PERSONNEL_CONFLICT'
    message_key = ('ya_existe_ese_centro_con_la_misma_direccion_y_localidad_revisa_tambien_los_inactivos'
                   if destination else 'ya_existe_un_registro_con_ese_documento_o_licencia_revisa_tambien_los_archivados')
    message = json.loads((ROOT / 'db/json/es.json').read_text())['errors.' + message_key]
    tables = ['facilities'] if destination else ['staff', 'personnel_profiles', 'staff_roles']

    def snapshot():
        state = {table: sql('SELECT * FROM ' + table + ' ORDER BY 1,2') for table in tables}
        state['audit'] = sql("SELECT * FROM audit_events WHERE resource_type='" + resource + "' ORDER BY id")
        return state

    def request(client, action, body=None, csrf=True):
        return client.call('/api/v1/' + domain + '.php?action=' + action,
                           'POST' if body is not None else 'GET', body, csrf=csrf)

    def expect(result, status, code, label):
        check(result[0] == status and result[1]['error']['code'] == code, domain + ': ' + label)

    roles = [('anonymous', Client()), ('admin', admin)]
    roles[0][1].csrf()
    for index, role in enumerate(['staff', 'records_manager']):
        client = Client(); client.csrf()
        name = 'guard_' + domain[0] + '_' + SUFFIX + '_' + str(index)
        status, payload, _ = client.call('/api/v1/auth.php?action=register', 'POST', dict(
            username=name, email=name + '@hospital.example', national_id=str(96000000 + secrets.randbelow(2000000)),
            password=PASSWORD))
        assert status == 201, payload
        assert manage_account(admin, 'approve', {'user_id': payload['data']['user_id'], 'role': role})[0] == 200
        assert client.login(name, PASSWORD)[0] == 200
        roles.append((role, client))

    before = snapshot()
    for role, client in roles:
        expected, code = (401, 'UNAUTHENTICATED') if role == 'anonymous' else (403, 'FORBIDDEN')
        for action, body in [('list', None), ('get&id=999999999', None), ('create', record),
                             ('update', {**record, 'id': '999999999', 'version': 1}),
                             ('archive', {'id': '999999999', 'version': 1}),
                             ('restore', {'id': '999999999', 'version': 1})]:
            expect(request(client, action, body), expected, code, role + ' cannot ' + action.split('&')[0])
    for action in ['create', 'update', 'archive', 'restore']:
        body = record if action == 'create' else {**(record if action == 'update' else {}), 'id': '999999999', 'version': 1}
        expect(request(dispatcher, action, body, csrf=False), 403, 'CSRF_INVALID', action + ' requires CSRF')
        result = request(dispatcher, action)
        expect(result, 405, 'METHOD_NOT_ALLOWED', action + ' rejects GET')
        check(result[2]['Allow'] == 'POST', domain + ': ' + action + ' advertises POST')
        expect(request(dispatcher, action, {**body, 'actor_user_id': '1'}), 422, 'UNEXPECTED_FIELDS', action + ' rejects injected fields')
    for action in ['list', 'get&id=999999999']:
        result = request(dispatcher, action, {})
        expect(result, 405, 'METHOD_NOT_ALLOWED', action.split('&')[0] + ' rejects POST')
        check(result[2]['Allow'] == 'GET', domain + ': read advertises GET')
    expect(request(dispatcher, 'get&id=0'), 422, 'VALIDATION_FAILED', 'invalid IDs are rejected')
    expect(request(dispatcher, 'list&unexpected=1'), 422, 'UNEXPECTED_FIELDS', 'unexpected read filters are rejected')
    expect(request(dispatcher, 'create', {**record, 'availability': 'unknown'}), 422, 'VALIDATION_FAILED', 'invalid availability is rejected')
    check(snapshot() == before, domain + ': rejected requests change no catalog, identity, role or audit rows')

    trigger = 'catalog_guard_fault'

    def fault(action, body, expected_status=503, expected_code='SERVICE_UNAVAILABLE', duplicate=False):
        state = snapshot()
        if duplicate:
            table = 'facilities' if destination else 'personnel_profiles'
            statement = ("CREATE TRIGGER " + trigger + " BEFORE UPDATE ON " + table +
                         " FOR EACH ROW SIGNAL SQLSTATE '23000' SET MYSQL_ERRNO=1062, MESSAGE_TEXT='Disposable duplicate fault'")
        else:
            statement = ("CREATE TRIGGER " + trigger + " BEFORE INSERT ON audit_events FOR EACH ROW BEGIN IF NEW.resource_type='" +
                         resource + "' AND NEW.action='" + action_prefix + '.' + action +
                         "d' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Disposable audit fault'; END IF; END")
        admin_sql(statement)
        try:
            result = request(dispatcher, action, body)
            expect(result, expected_status, expected_code, action + ' reports the injected SQL failure')
            if duplicate:
                check(result[1]['error']['message'] == message, domain + ': duplicate error keeps its domain-specific Spanish message')
            check(snapshot() == state, domain + ': failed ' + action + ' rolls back all data, revisions and audit rows')
        finally:
            admin_sql('DROP TRIGGER ' + trigger)

    # The audit insert follows all domain writes, so failing it exercises the full rollback.
    fault('create', record)
    status, payload, _ = request(dispatcher, 'create', record)
    check(status == 201, domain + ': create retry succeeds after rollback')
    item = payload['data']; identity = item['id']
    for action in ['get&id=', 'update', 'archive', 'restore']:
        body = None if action.startswith('get') else {**(record if action == 'update' else {}), 'id': '999999999', 'version': 1}
        expect(request(dispatcher, action + '999999999' if body is None else action, body),
               404, 'DESTINATION_NOT_FOUND' if destination else 'PERSONNEL_NOT_FOUND', 'missing record returns 404 for ' + action.split('&')[0])
    for action in ['update', 'archive', 'restore']:
        body = {**(record if action == 'update' else {}), 'id': identity, 'version': None}
        expect(request(dispatcher, action, body), 422, 'VERSION_REQUIRED', action + ' requires a revision')
        expect(request(dispatcher, action, {**body, 'version': item['version'] + 10}), 409, 'VERSION_CONFLICT', action + ' rejects stale revisions')
    if destination:
        sql('UPDATE facilities SET latitude=-34.9,longitude=-56.1 WHERE id=' + identity)
        changes = {'name': 'Centro modificado de prueba', 'address': 'Calle de prueba 200'}
    else:
        sql("INSERT INTO staff_roles (staff_id,role_code) VALUES (" + identity + ",'doctor')")
        changes = {'full_name': 'Personal modificado de prueba', 'responsibility': 'copilot'}
    edit = {**record, **changes, 'id': identity, 'version': item['version']}
    fault('update', edit, 409, conflict, duplicate=True)
    fault('update', edit)
    status, payload, _ = request(dispatcher, 'update', edit)
    check(status == 200, domain + ': update retry succeeds after rollback')
    item = payload['data']
    if destination:
        check(item['latitude'] is None and item['longitude'] is None, 'destinations: successful moved address clears old coordinates')
    else:
        check(sql("SELECT role_code FROM staff_roles WHERE staff_id=" + identity + " ORDER BY role_code") ==
              [{'role_code': 'copilot'}, {'role_code': 'doctor'}], 'personnel: successful role change preserves the independent doctor role')
    for action, state in [('archive', 'archived'), ('restore', 'active')]:
        body = {'id': identity, 'version': item['version']}
        fault(action, body)
        status, payload, _ = request(dispatcher, action, body)
        check(status == 200 and payload['data']['status'] == state, domain + ': ' + action + ' retry preserves the lifecycle')
        item = payload['data']
    events = sql("SELECT action,actor_user_id,resource_id,request_id FROM audit_events WHERE resource_type='" + resource +
                 "' AND resource_id=" + identity + ' ORDER BY id')
    actor = request(dispatcher, 'list')[0]  # Exercise another request after rollback/lock release.
    user_id = dispatcher.call('/api/v1/auth.php?action=me')[1]['data']['user']['id']
    check(actor == 200 and [event['action'] for event in events] ==
          [action_prefix + suffix for suffix in ['.created', '.updated', '.archived', '.restored']] and
          all(str(event['actor_user_id']) == user_id and str(event['resource_id']) == identity and event['request_id'] for event in events),
          domain + ': only committed writes produce one audit event with the original actor and resource')
