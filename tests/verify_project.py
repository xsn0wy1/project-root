"""Run existing integration checks, each with its own disposable SQL database.

This orchestrates the existing tests. Private credentials/logs stay under ignored
tests/.runtime; normal configuration and demonstration data remain untouched.
"""
import argparse
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess
import sys
import time
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('HOSPITAL_TEST_PHP', '/Applications/XAMPP/xamppfiles/bin/php')
MODULES = ['auth', 'settings', 'ambulances', 'patients', 'doctors', 'personnel',
           'destinations', 'routes', 'documents', 'transfers', 'surveys',
           'audit_corrections', 'release_fixes', 'migrations', 'i18n', 'theme']


def clean_environment():
    return {key: value for key, value in os.environ.items()
            if not key.startswith('HOSPITAL_') or key.startswith('HOSPITAL_SETUP_')}


def cleanup(env):
    code = r"""require 'backend/php/autoload.php';$c=Hospital\Config::load();
    $name=$c['db']['name'];$user=$c['db']['user'];
    if(!preg_match('/^hospital_verify_[a-f0-9]{8}_test$/',$name)||!preg_match('/^verify_[a-f0-9]{8}$/',$user))exit(2);
    $c['db']['user']=getenv('HOSPITAL_SETUP_DB_USER')?:'root';$c['db']['password']=getenv('HOSPITAL_SETUP_DB_PASSWORD')?:'';
    $db=Hospital\Database::connect($c,false);$db->exec('DROP DATABASE IF EXISTS '.chr(96).$name.chr(96));
    foreach(['localhost','127.0.0.1'] as $host)$db->exec('DROP USER IF EXISTS '.$db->quote($user).'@'.$db->quote($host));"""
    subprocess.run([PHP, '-r', code], cwd=ROOT, env=env, check=True, capture_output=True)


def run_module(module, directory, port):
    directory.mkdir(parents=True)
    suffix = secrets.token_hex(4)
    env = clean_environment()
    env.update(HOSPITAL_DB_NAME='hospital_verify_'+suffix+'_test',
               HOSPITAL_DB_USER='verify_'+suffix, HOSPITAL_DB_PASSWORD=secrets.token_hex(24),
               HOSPITAL_APP_KEY=secrets.token_hex(32), HOSPITAL_ADMIN_PASSWORD=secrets.token_urlsafe(24),
               HOSPITAL_DB_SOCKET=os.environ.get('HOSPITAL_DB_SOCKET', '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock'),
               HOSPITAL_STORAGE_PATH=str(directory/'storage'), HOSPITAL_BASE_PATH='',
               HOSPITAL_APP_URL=f'http://127.0.0.1:{port}/', HOSPITAL_AUTH_TEST_URL=f'http://127.0.0.1:{port}',
               HOSPITAL_BCRYPT_COST='10', HOSPITAL_SECURE_COOKIES='false',
               HOSPITAL_BREVO_API_KEY='', HOSPITAL_MAIL_FROM_EMAIL='',
               HOSPITAL_ROUTE_MAPPING_ENABLED='true' if module in ['routes', 'transfers', 'audit_corrections'] else 'false', HOSPITAL_TEST_PHP=PHP)
    for domain in ['AUDIT', 'FLEET', 'DOCTOR', 'PERSONNEL', 'DESTINATION', 'ROUTE', 'SURVEY', 'THEME']:
        env['HOSPITAL_'+domain+'_TEST_DIR'] = str(directory)
    private = directory/'env.json'
    private.write_text(json.dumps({key: value for key, value in env.items() if key.startswith('HOSPITAL_')}))
    private.chmod(0o600)
    process = None
    started = time.monotonic()
    result = {'module': module, 'status': 'failed', 'checks': 0, 'cleaned': False}
    try:
        with (directory/'setup.log').open('w') as log:
            for args in [('setup.php', '--no-config'), ('users.php', 'create-admin', 'system_admin',
                         'verification@hospital.example', '77009999')]:
                subprocess.run([PHP, 'backend/php/cli/'+args[0], *args[1:]], cwd=ROOT, env=env,
                               stdout=log, stderr=log, check=True, timeout=60)
            if module in ['audit_corrections', 'release_fixes']:
                subprocess.run([PHP, 'backend/php/cli/users.php', 'roles', 'system_admin',
                                'admin,records_manager,dispatcher'], cwd=ROOT, env=env,
                               stdout=log, stderr=log, check=True)
            if module == 'audit_corrections':
                subprocess.run([PHP, 'backend/php/cli/transfers.php', 'seed-demo'], cwd=ROOT, env=env,
                               stdout=log, stderr=log, check=True)
        with socket.socket() as probe:
            probe.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            probe.bind(('127.0.0.1', port))
        with (directory/'server.log').open('w') as log:
            process = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', 'public', 'backend/php/dev-router.php'],
                                       cwd=ROOT, env=env, stdout=log, stderr=log)
        for _ in range(60):
            try:
                urllib.request.urlopen(env['HOSPITAL_AUTH_TEST_URL']+'/', timeout=1).close()
                break
            except OSError:
                if process.poll() is not None:
                    raise RuntimeError('Disposable server stopped.')
                time.sleep(.05)
        else:
            raise RuntimeError('Disposable server did not start.')
        with (directory/'checks.log').open('w') as log:
            checked = subprocess.run([sys.executable, 'tests/'+module+'_integration.py'],
                                     cwd=ROOT, env=env, stdout=log, stderr=log, timeout=300)
        output = (directory/'checks.log').read_text()
        result.update(status='passed' if checked.returncode == 0 else 'failed',
                      checks=len(re.findall(r'^PASS\b', output, re.M)), exit_code=checked.returncode)
    except Exception as error:
        result['error'] = type(error).__name__+': '+str(error)
    finally:
        if process:
            process.terminate()
            try:
                process.wait(timeout=10)
            except subprocess.TimeoutExpired:
                process.kill(); process.wait()
        cleanup(env)
        shutil.rmtree(directory/'storage', ignore_errors=True)
        result.update(cleaned=True, seconds=round(time.monotonic()-started, 2))
        (directory/'result.json').write_text(json.dumps(result, indent=2)+'\n')
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--modules', default=','.join(MODULES))
    parser.add_argument('--output', type=Path)
    parser.add_argument('--port', type=int, default=8113)
    args = parser.parse_args()
    modules = args.modules.split(',')
    if not modules or set(modules)-set(MODULES):
        parser.error('Choose existing module names: '+','.join(MODULES))
    output = (args.output or ROOT/'tests/.runtime/verification'/secrets.token_hex(4)).resolve()
    output.relative_to(ROOT/'tests/.runtime')
    output.mkdir(parents=True, exist_ok=True)
    results = []
    for module in modules:
        print('Checking '+module+' with a separate disposable database...', flush=True)
        result = run_module(module, output/(module+'-'+secrets.token_hex(3)), args.port)
        results.append(result)
        (output/'report.json').write_text(json.dumps(results, indent=2)+'\n')
        print(module+': '+result['status']+'; '+str(result['checks'])+' checks; fixture cleaned.', flush=True)
    print('Completed '+str(len(results))+' modules; '+str(sum(row['checks'] for row in results))+' checks.', flush=True)
    return int(any(row['status'] != 'passed' for row in results))


if __name__ == '__main__':
    raise SystemExit(main())
