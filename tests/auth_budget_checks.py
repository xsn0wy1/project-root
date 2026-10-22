"""Atomic precharge/refund regressions invoked by the existing release-fix suite."""
import hashlib
import hmac
import json
import os
import secrets
import select
import subprocess
from auth_integration import PHP, ROOT, PASSWORD, sql


def run_budget_checks(admin, account, me, check):
    assert os.environ['HOSPITAL_DB_NAME'].endswith('_test')
    ip = '203.0.113.77'
    client, username, _ = account(admin, 4)
    user_id = me(client)['user']['id']
    source = 'login-failure-source:' + ip + ':' + username
    shared = 'login-failure-ip:' + ip
    current = 'current-password-failure:' + user_id + ':' + ip

    def key(name):
        return hmac.new(os.environ['HOSPITAL_APP_KEY'].encode(), name.encode(), hashlib.sha256).hexdigest()

    def seed(name, attempts, expired=False):
        expiry = 'UTC_TIMESTAMP()-INTERVAL 1 SECOND' if expired else 'UTC_TIMESTAMP()+INTERVAL 15 MINUTE'
        sql("REPLACE INTO auth_rate_limits (bucket_key,attempts,resets_at) VALUES ('" + key(name) + "'," + str(attempts) + ',' + expiry + ')')

    def count(name):
        rows = sql("SELECT attempts FROM auth_rate_limits WHERE bucket_key='" + key(name) + "'")
        return int(rows[0]['attempts']) if rows else 0

    def reset():
        sql('DELETE FROM auth_rate_limits')

    def start(**values):
        process = subprocess.Popen([PHP, 'tests/auth_budget_checks.php'], cwd=ROOT,
                                   stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        process.stdin.write(json.dumps(dict(ip=ip, **values)) + '\n'); process.stdin.flush()
        return process

    def finish(process, release=False):
        try:
            if release:
                process.stdin.write('continue\n'); process.stdin.flush()
            output, errors = process.communicate(timeout=12)
            assert process.returncode == 0 and not errors, errors
            return json.loads(output)
        finally:
            if process.poll() is None:
                process.kill(); process.wait()

    def invoke(**values):
        return finish(start(**values))

    def wait(process):
        assert select.select([process.stderr], [], [], 10)[0], 'Credential barrier timeout'
        return process.stderr.readline().strip() == 'charged'

    def burst(action, requests):
        processes = [start(action=action, hold=True, **values) for values in requests]
        try:
            held = [wait(process) for process in processes]
            return processes, held
        except BaseException:
            for process in processes:
                process.kill(); process.wait()
            raise

    # A source at 9 admits only one more verification, even with available IP capacity.
    reset(); seed(source, 9); seed(shared, 58)
    processes, held = burst('login', [dict(username=username, password='incorrect-password')] * 6)
    try:
        check(sum(held) == 1 and count(source) == 10 and count(shared) == 59,
              'parallel source admission charges both budgets atomically before credential lookup')
    finally:
        outcomes = [finish(p, release=h) for p, h in zip(processes, held)]
    check(sorted(r['status'] for r in outcomes) == [401] + [429] * 5 and
          sum(r['credential_lookup_reached'] for r in outcomes) == 1,
          'blocked login bursts perform no credential lookup or password verification')

    reset(); seed(shared, 59)
    names = ['budget_absent_' + secrets.token_hex(4) for _ in range(5)]
    processes, held = burst('login', [dict(username=name, password='incorrect-password') for name in names])
    try:
        check(sum(held) == 1 and count(shared) == 60 and
              sum(count('login-failure-source:' + ip + ':' + name) for name in names) == 1,
              'shared-IP admission rejects parallel usernames without partial source charges')
    finally:
        outcomes = [finish(p, release=h) for p, h in zip(processes, held)]
    check(sorted(r['status'] for r in outcomes) == [401] + [429] * 4, 'shared-IP cap returns only credential failure or 429')

    reset(); seed(shared, 7); seed(source, 3)
    other = 'budget_other_' + secrets.token_hex(4)
    pending = start(action='login', username=other, password='incorrect-password', hold=True)
    assert wait(pending)
    try:
        result = invoke(action='login', username=username, password=PASSWORD)
        check(result['status'] == 200 and count(shared) == 8 and count(source) == 0 and
              count('login-failure-source:' + ip + ':' + other) == 1,
              'successful login resets only its source and refunds one shared charge while another verification is in flight')
    finally:
        assert finish(pending, release=True)['status'] == 401
    check(count(shared) == 8, 'another request keeps its failure after a successful shared-network login')

    for scenario, initial, expected in [('normal', 2, 2), ('zero', 0, 0), ('new-window', 2, 4), ('rollback', 2, 2)]:
        reset(); bucket = 'budget-refund-' + scenario; seed(bucket, initial)
        result = invoke(action='refund', bucket=bucket, scenario=scenario)
        check(result['status'] == 200 and count(bucket) == expected,
              'refund remains bounded/idempotent across ' + scenario)

    reset(); seed(shared, 60, expired=True); seed(source, 10, expired=True)
    check(invoke(action='login', username=username, password='incorrect-password')['status'] == 401 and
          count(shared) == count(source) == 1, 'expired login windows restart their budgets')

    # Valid bursts can provisionally fill the budget, then release it after success.
    reset(); seed(source, 9); seed(shared, 58)
    processes, held = burst('login', [dict(username=username, password=PASSWORD)] * 3)
    outcomes = [finish(p, release=h) for p, h in zip(processes, held)]
    check(sum(held) == 1 and sorted(r['status'] for r in outcomes) == [200, 429, 429] and
          count(source) == 0 and count(shared) == 58, 'valid parallel bursts are bounded and the successful charge is refunded')

    # Correct credentials rejected by account policy are not password failures.
    for status, code in [('pending', 'ACCOUNT_PENDING'), ('disabled', 'ACCOUNT_DISABLED')]:
        reset(); seed(shared, 4); seed(source, 2)
        sql("UPDATE users SET status='" + status + "' WHERE id=" + user_id)
        result = invoke(action='login', username=username, password=PASSWORD)
        check(result.get('code') == code and count(shared) == 4 and count(source) == 2,
              status + ' authorization rejection releases only its provisional charges')
    sql("UPDATE users SET status='active' WHERE id=" + user_id)

    reset(); seed(current, 4)
    replacement = secrets.token_urlsafe(24)
    body = dict(current_password='incorrect-password', new_password=replacement)
    processes, held = burst('change', [dict(user_id=user_id, body=body)] * 4)
    try:
        check(sum(held) == 1 and count(current) == 5, 'current-password checks are charged before parallel credential verification')
    finally:
        outcomes = [finish(p, release=h) for p, h in zip(processes, held)]
    check(sorted(r['status'] for r in outcomes) == [422, 429, 429, 429] and
          sum(r['credential_lookup_reached'] for r in outcomes) == 1, 'current-password cap blocks extra verification')

    reset(); seed(current, 2)
    check(invoke(action='change', user_id=user_id, body=dict(current_password=PASSWORD, new_password='short'))['status'] == 422 and count(current) == 2,
          'new-password validation failure refunds its provisional attempt')
    check(invoke(action='change', user_id=user_id, auth_version=0,
                 body=dict(current_password=PASSWORD, new_password=replacement))['status'] == 401 and count(current) == 2,
          'expired authorization refunds its provisional password-check attempt')

    def snapshot():
        return dict(user=sql('SELECT password_hash,auth_version,version,last_login_at FROM users WHERE id=' + user_id),
                    reset=sql('SELECT * FROM password_reset_tokens WHERE user_id=' + user_id),
                    sessions=sql('SELECT session_hash,revoked_at FROM user_sessions WHERE user_id=' + user_id + ' ORDER BY session_hash'),
                    audits=sql('SELECT id,action FROM audit_events WHERE resource_id=' + user_id + " AND resource_type='user' ORDER BY id"),
                    notifications=sql('SELECT id,kind FROM user_notifications WHERE user_id=' + user_id + ' ORDER BY id'))

    reset(); seed(shared, 7); seed(source, 3)
    before = snapshot()
    result = invoke(action='login', username=username, password=PASSWORD, refund_fault=True)
    check(result['status'] == 503 and before == snapshot() and count(shared) == 7 and count(source) == 3,
          'login cleanup failure rolls back login/audit/notification writes and refunds only its own charges')

    raw_hash = hashlib.sha256(secrets.token_bytes(32)).hexdigest()
    sql("INSERT INTO password_reset_tokens (user_id,token_hash,auth_version,created_at,expires_at) SELECT id,'" + raw_hash + "',auth_version,UTC_TIMESTAMP(),UTC_TIMESTAMP()+INTERVAL 30 MINUTE FROM users WHERE id=" + user_id)
    seed(current, 2); before = snapshot()
    result = invoke(action='change', user_id=user_id, body=dict(current_password=PASSWORD, new_password=replacement), refund_fault=True)
    check(result['status'] == 503 and before == snapshot() and count(current) == 2,
          'password cleanup failure rolls back password/revisions/reset token/sessions/audit before reporting failure')
    result = invoke(action='change', user_id=user_id, body=dict(current_password=PASSWORD, new_password=replacement))
    updated = snapshot()
    check(result['status'] == 200 and count(current) == 0 and not updated['reset'] and
          int(updated['user'][0]['auth_version']) == int(before['user'][0]['auth_version']) + 1 and
          all(row['revoked_at'] is not None for row in updated['sessions']),
          'successful password change commits once, resets its budget and revokes existing credentials/sessions')
