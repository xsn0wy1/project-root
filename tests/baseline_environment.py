"""Prepare the isolated Stage 1 demonstration using the existing API fixtures.

prepare provisions a fresh database/config once; start/stop manage only recorded
PHP development servers. No original installation or global server config is used.
"""
import json
import os
from pathlib import Path
import secrets
import signal
import socket
import subprocess
import sys
import time
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
RUNTIME = ROOT / 'tests/.runtime/stage1'
PHP = os.environ.get('HOSPITAL_TEST_PHP', '/Applications/XAMPP/xamppfiles/bin/php')
SERVERS = [('site', ROOT, 8091, ''), ('reference', RUNTIME / 'reference', 8092, ''),
           ('mounted', ROOT, 8093, '/hospital')]


def private_json(path, value):
    path.write_text(json.dumps(value, indent=2) + '\n')
    path.chmod(0o600)


def environment():
    env = {k: v for k, v in os.environ.items() if not k.startswith('HOSPITAL_')}
    env.update(json.loads((RUNTIME / 'env.json').read_text()))
    return env


def start():
    env = environment()
    for name, directory, port, mount in SERVERS:
        if name == 'reference' and not directory.exists():
            continue
        pidfile = RUNTIME / (name + '.pid')
        if pidfile.exists():
            command = subprocess.run(['ps', '-p', pidfile.read_text().strip(), '-o', 'command='],
                                     text=True, capture_output=True).stdout
            try:
                os.kill(int(pidfile.read_text()), 0)
                if 'backend/php/dev-router.php' in command and f'127.0.0.1:{port}' in command:
                    continue
                pidfile.unlink()
            except ProcessLookupError:
                pidfile.unlink()
        with socket.socket() as probe:
            probe.bind(('127.0.0.1', port))  # Refuse to replace another server.
        serving = dict(env, HOSPITAL_BASE_PATH=mount,
                       HOSPITAL_APP_URL=f'http://127.0.0.1:{port}{mount}/')
        with (RUNTIME / (name + '.log')).open('a') as log:
            process = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', 'public',
                                        'backend/php/dev-router.php'], cwd=directory,
                                       env=serving, stdout=log, stderr=log, start_new_session=True)
        pidfile.write_text(str(process.pid))
        for attempt in range(60):
            try:
                with urllib.request.urlopen(f'http://127.0.0.1:{port}{mount}/' + ('index.html' if name == 'reference' else 'index.php'), timeout=1):
                    break
            except OSError:
                if process.poll() is not None or attempt == 59:
                    raise RuntimeError(f'{name} server failed; inspect its private runtime log.')
                time.sleep(.1)
    print('Isolated development servers ready: http://127.0.0.1:8091/')


def stop():
    for name, _, port, _ in SERVERS:
        pidfile = RUNTIME / (name + '.pid')
        if pidfile.exists():
            pid = int(pidfile.read_text())
            # A stale PID must never stop an unrelated process.
            command = subprocess.run(['ps', '-p', str(pid), '-o', 'command='],
                                     text=True, capture_output=True).stdout
            if 'backend/php/dev-router.php' in command and f'127.0.0.1:{port}' in command:
                os.kill(pid, signal.SIGTERM)
            pidfile.unlink()
    print('Recorded baseline servers stopped; database and fixtures preserved.')


def prepare():
    RUNTIME.mkdir(parents=True, exist_ok=True)
    if (RUNTIME / 'env.json').exists() or (ROOT / 'backend/php/config/local.php').exists():
        raise RuntimeError('Baseline already configured. Use start; prepare never replaces a configuration.')
    suffix = secrets.token_hex(4)
    env = dict(HOSPITAL_DB_NAME=f'hospital_refactor_{suffix}_test',
               HOSPITAL_DB_USER=f'refactor_{suffix}', HOSPITAL_DB_PASSWORD=secrets.token_hex(24),
               HOSPITAL_DB_SOCKET='/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock',
               HOSPITAL_APP_KEY=secrets.token_hex(32), HOSPITAL_STORAGE_PATH=str(ROOT / 'storage'),
               HOSPITAL_BASE_PATH='', HOSPITAL_SECURE_COOKIES='false', HOSPITAL_BCRYPT_COST='12',
               HOSPITAL_APP_URL='http://127.0.0.1:8091/', HOSPITAL_ROUTE_MAPPING_ENABLED='false',
               HOSPITAL_BREVO_API_KEY='', HOSPITAL_MAIL_FROM_EMAIL='',
               HOSPITAL_AUTH_TEST_URL='http://127.0.0.1:8091', HOSPITAL_TEST_PHP=PHP,
               HOSPITAL_ADMIN_PASSWORD=secrets.token_urlsafe(24),
               HOSPITAL_I18N_TEST_RECORDS=str(RUNTIME / 'records.json'))
    private_json(RUNTIME / 'env.json', env)
    private_json(RUNTIME / 'access.json', dict(url=env['HOSPITAL_APP_URL'],
                 username='system_admin', password=env['HOSPITAL_ADMIN_PASSWORD'],
                 note='Fictional local demonstration only; do not package this file.'))
    for args in [('setup.php',), ('users.php', 'create-admin', 'system_admin',
                  'baseline@hospital.example', '77009999'),
                 ('users.php', 'roles', 'system_admin', 'admin,records_manager,dispatcher'),
                 ('transfers.php', 'seed-demo')]:
        subprocess.run([PHP, 'backend/php/cli/' + args[0], *args[1:]],
                       cwd=ROOT, env=environment(), check=True, stdout=subprocess.DEVNULL)
    start()
    subprocess.run([sys.executable, '-c', 'import i18n_browser_fixtures'],
                   cwd=ROOT / 'tests', env=environment(), check=True)
    print('Fictional populated baseline prepared. Login is in tests/.runtime/stage1/access.json (private).')


if __name__ == '__main__':
    commands = {'prepare': prepare, 'start': start, 'stop': stop}
    if len(sys.argv) != 2 or sys.argv[1] not in commands:
        raise SystemExit('Use baseline_environment.py prepare | start | stop')
    commands[sys.argv[1]]()
