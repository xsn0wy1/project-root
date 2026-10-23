"""Verify a clean source installation and optionally run the existing browser journeys.

All configuration, SQL accounts, uploads and evidence are disposable and private.
No normal configuration, original checkout or global Apache setting is changed.
"""
import argparse
import hashlib
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
import urllib.error
import urllib.request
from verify_project import ROOT, PHP, clean_environment


def run(output, browser):
    output = output.resolve()
    output.relative_to(ROOT/'tests/.runtime')
    output.mkdir(parents=True, exist_ok=False)
    source = output/'source'
    for area in ['public', 'backend', 'db']:
        shutil.copytree(ROOT/area, source/area, ignore=shutil.ignore_patterns('local.php', '.DS_Store', '__pycache__'))
    shutil.copy2(ROOT/'README.md', source/'README.md')
    suffix = secrets.token_hex(4)
    env = clean_environment()
    # Reuse the existing UI runner's focused options for a bounded rerun.
    ui_options = {key: value for key, value in os.environ.items() if key in [
        'HOSPITAL_UI_FILES', 'HOSPITAL_UI_SIZES', 'HOSPITAL_UI_JOURNEYS', 'HOSPITAL_UI_LAYOUT_ONLY']}
    env.update(HOSPITAL_DB_NAME='hospital_install_'+suffix+'_test', HOSPITAL_DB_USER='install_'+suffix,
               HOSPITAL_DB_SOCKET=os.environ.get('HOSPITAL_DB_SOCKET', '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock'),
               HOSPITAL_BASE_PATH='', HOSPITAL_APP_URL='http://127.0.0.1:8114/',
               HOSPITAL_AUTH_TEST_URL='http://127.0.0.1:8114', HOSPITAL_SECURE_COOKIES='false',
               HOSPITAL_BCRYPT_COST='10', HOSPITAL_ADMIN_PASSWORD=secrets.token_urlsafe(24),
               HOSPITAL_ROUTE_MAPPING_ENABLED='false', HOSPITAL_BREVO_API_KEY='', HOSPITAL_MAIL_FROM_EMAIL='')
    servers = []
    report = {'checks': 0, 'browser': {}, 'cleaned': False, 'status': 'failed'}

    def check(value, label):
        assert value, label
        report['checks'] += 1
        print('PASS '+label, flush=True)

    def cli(*args):
        with (output/'commands.log').open('a') as log:
            subprocess.run([PHP, 'backend/php/cli/'+args[0], *args[1:]], cwd=source,
                           env=env, stdout=log, stderr=log, check=True, timeout=60)

    def php(code):
        result = subprocess.run([PHP, '-r', "require 'backend/php/autoload.php';"+code],
                                cwd=source, env=env, capture_output=True, text=True, check=True)
        return json.loads(result.stdout)

    def serve(port, mount, expected=200):
        with socket.socket() as probe:
            probe.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            probe.bind(('127.0.0.1', port))
        with (output/f'server-{port}.log').open('w') as log:
            process = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', 'public', 'backend/php/dev-router.php'],
                                       cwd=source, env={**env, 'HOSPITAL_BASE_PATH': mount,
                                       'HOSPITAL_APP_URL': f'http://127.0.0.1:{port}{mount}/'},
                                       stdout=log, stderr=log)
        servers.append(process)
        for _ in range(60):
            try:
                urllib.request.urlopen(f'http://127.0.0.1:{port}{mount}/', timeout=1).close()
                return
            except urllib.error.HTTPError as error:
                if error.code == expected:
                    return error.read().decode()
                if process.poll() is not None:
                    raise RuntimeError('Fresh-install server stopped.')
                time.sleep(.05)
            except OSError:
                if process.poll() is not None:
                    raise RuntimeError('Fresh-install server stopped.')
                time.sleep(.05)
        raise RuntimeError('Fresh-install server timed out.')

    try:
        local = source/'backend/php/config/local.php'
        check(not local.exists() and not (source/'storage').exists(), 'clean copy excludes existing configuration and uploads')
        unavailable = serve(8114, '', expected=503)
        check('Algo salió mal' in unavailable and 'error-card' in unavailable, 'unconfigured page renders the safe standalone service error')
        for path in ['/src/css/login-register/log-reg.css', '/src/api-connection/theme.js', '/assets/Icons/alert-icon.svg']:
            with urllib.request.urlopen('http://127.0.0.1:8114'+path, timeout=5) as response:
                check(response.status == 200 and response.read() == (source/'public'/path.lstrip('/')).read_bytes(),
                      'unconfigured error-card asset remains available: '+path)
        try:
            urllib.request.urlopen('http://127.0.0.1:8114/api/v1/auth.php?action=me', timeout=5)
            raise AssertionError('Unconfigured API unexpectedly succeeded.')
        except urllib.error.HTTPError as error:
            payload = json.loads(error.read())
            check(error.code == 503 and payload['success'] is False and payload['error']['code'] == 'SERVICE_UNAVAILABLE'
                  and 'README.md' in payload['error']['message'], 'unconfigured API retains its original JSON envelope/message')
        unconfigured = servers.pop()
        unconfigured.terminate()
        unconfigured.wait(timeout=10)
        cli('setup.php')
        check(local.exists() and local.stat().st_mode & 0o777 == 0o600, 'setup creates private configuration with owner-only permissions')
        config = php('echo json_encode(Hospital\\Config::load());')
        check(config['route_mapping_enabled'] is False and config['geocoder_url'] == config['router_url'] == '', 'fresh defaults never send addresses to public lookup services')
        check(len(config['app_key']) == 64 and len(config['db']['password']) == 48, 'setup generates an application key and database password')
        storage = Path(config['storage_path']).resolve()
        check(storage == source/'storage' and not storage.is_relative_to(source/'public'), 'generated storage is private to the fresh installation')
        check(config['db']['name'] == env['HOSPITAL_DB_NAME'] and config['db']['user'] == env['HOSPITAL_DB_USER'], 'setup selects only its dedicated database and account')
        before = hashlib.sha256(local.read_bytes()).hexdigest()
        cli('setup.php'); cli('migrate.php'); cli('migrate.php')
        check(hashlib.sha256(local.read_bytes()).hexdigest() == before, 'repeating setup/migration preserves configuration and key')
        counts = php("$db=Hospital\\Database::connect(Hospital\\Config::load());$out=[];foreach(['users','staff','stored_files','documents','ambulances','transfers','surveys'] as $table)$out[$table]=(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();$out['migrations']=(int)$db->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();echo json_encode($out);")
        check(all(value == 0 for name, value in counts.items() if name != 'migrations'), 'fresh setup fabricates no accounts, records, uploads or surveys')
        check(counts['migrations'] == 1+len(list((source/'db/sql/migrations').glob('*.sql'))), 'every current migration is recorded once')
        for area in ['users', 'documents', 'quarantine', 'mail']:
            check((storage/area).is_dir(), 'setup prepares private '+area+' storage')
        cli('users.php', 'create-admin', 'system_admin', 'install@hospital.example', '77009999')
        cli('preflight.php')
        preflight = subprocess.run([PHP, 'backend/php/cli/preflight.php'], cwd=source, env=env,
                                   capture_output=True, text=True, check=True)
        check(json.loads(preflight.stdout)['healthy'], 'fresh-install preflight is healthy')
        (output/'preflight.json').write_text(preflight.stdout)
        serve(8114, ''); serve(8115, '/hospital')
        os.environ.update(env)
        from auth_integration import Client
        for base in ['http://127.0.0.1:8114', 'http://127.0.0.1:8115/hospital']:
            anon = Client(base)
            check(anon.call('/index.php')[0] == 200, 'fresh public login renders at '+base)
            for path, content_type in [('/src/css/base.css', 'text/css; charset=utf-8'),
                                       ('/src/api-connection/theme.js', 'application/javascript; charset=utf-8'),
                                       ('/assets/Icons/eye.svg', 'image/svg+xml')]:
                with urllib.request.urlopen(base+path+'?v=router-check', timeout=5) as response:
                    check(response.status == 200 and response.read() == (source/'public'/path.lstrip('/')).read_bytes(),
                          'static resource retains exact bytes at '+base+path)
                    check(response.headers['Content-Type'] == content_type and response.headers['X-Content-Type-Options'] == 'nosniff',
                          'static resource retains MIME and nosniff headers: '+path)
                    if path.endswith(('.css', '.js')):
                        check(response.headers['Cache-Control'] == 'no-cache', 'development asset retains revalidation: '+path)
            request = urllib.request.Request(base+'/src/css/base.css', method='HEAD')
            with urllib.request.urlopen(request, timeout=5) as response:
                check(response.status == 200 and response.read() == b'' and response.headers['Content-Type'] == 'text/css; charset=utf-8',
                      'static HEAD retains headers and omits the body at '+base)
            check(anon.call('/src/css/missing.css')[0] == 404, 'missing static asset remains unavailable at '+base)
            status, payload, _ = anon.call('/api/v1/missing.php')
            check(status == 404 and payload['success'] is False and payload['error']['code'] == 'NOT_FOUND',
                  'missing API endpoint retains its JSON envelope at '+base)
            for path in ['/backend/php/config/local.php', '/db/sql/schema.sql', '/storage/documents/',
                         '/tests/.runtime/', '/../backend/php/config/local.php']:
                check(anon.call(path)[0] == 404, 'private path stays unavailable: '+path)
            check(anon.call('/api/v1/page.php?view=main-menu.html')[0] == 404 and anon.call('/src/views/main-menu.html')[0] == 404, 'retired HTML routes stay removed')
            for page in (source/'public/src/views').rglob('*.php'):
                relative = page.relative_to(source/'public').as_posix()
                if 'register-restore/' in relative or relative.endswith(('survey-user.php', '/error.php')):
                    continue
                status, _, headers = anon.call('/'+relative)
                check(status == 303 and 'index.php' in headers['Location'], 'anonymous fresh-install staff page is guarded: '+relative)
            staff = Client(base)
            check(staff.login('system_admin', env['HOSPITAL_ADMIN_PASSWORD'])[0] == 200, 'created administrator can sign in at '+base)
            current = staff.call('/api/v1/auth.php?action=me')[1]['data']
            check(current['settings']['locale'] == 'es-UY' and current['settings']['theme'] == 'dark', 'fresh account retains Spanish and dark defaults')
            check(current['user']['roles'] == ['admin'], 'administrator does not silently gain clinical/dispatcher permissions')
            check(staff.call('/src/views/main-menu.php')[0] == 200, 'authenticated fresh-install menu renders')
        if browser:
            cli('users.php', 'roles', 'system_admin', 'admin,records_manager,dispatcher')
            cli('transfers.php', 'seed-demo')
            env.update(HOSPITAL_APP_KEY=config['app_key'], HOSPITAL_DB_PASSWORD=config['db']['password'],
                       HOSPITAL_STORAGE_PATH=str(storage), HOSPITAL_I18N_TEST_RECORDS=str(output/'records.json'))
            private = output/'env.json'
            private.write_text(json.dumps({key: value for key, value in env.items() if key.startswith('HOSPITAL_')}))
            private.chmod(0o600)
            with (output/'fixtures.log').open('w') as log:
                subprocess.run([sys.executable, '-c', "import sys;sys.path.insert(0,'tests');import i18n_browser_fixtures"],
                               cwd=ROOT, env=env, stdout=log, stderr=log, check=True)
            node = os.environ.get('HOSPITAL_TEST_NODE', str(Path.home()/'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node'))
            env.setdefault('NODE_PATH', str(Path.home()/'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'))
            env.update(HOSPITAL_UI_TEST_DIR=str(output), HOSPITAL_UI_OUTPUT=str(output/'ui'),
                       HOSPITAL_WORKFLOW_TEST_DIR=str(output), HOSPITAL_WORKFLOW_MOUNT_URL='http://127.0.0.1:8115/hospital')
            env.update(ui_options)
            for name, script in [('ui', 'ui_browser.cjs'), ('workflows', 'transfers_surveys_browser.cjs')]:
                with (output/(name+'.log')).open('w') as log:
                    result = subprocess.run([node, 'tests/'+script], cwd=ROOT, env=env,
                                            stdout=log, stderr=log, timeout=900)
                check(result.returncode == 0, 'fresh-install '+name+' browser journeys pass')
                report['browser'][name] = 'passed'
        report['status'] = 'passed'
    finally:
        for process in servers:
            process.terminate()
            try:
                process.wait(timeout=10)
            except subprocess.TimeoutExpired:
                process.kill(); process.wait()
        cleanup = r"""require 'backend/php/autoload.php';$c=Hospital\Config::load();$name=$c['db']['name'];$user=$c['db']['user'];
        if(!preg_match('/^hospital_install_[a-f0-9]{8}_test$/',$name)||!preg_match('/^install_[a-f0-9]{8}$/',$user))exit(2);
        $c['db']['user']=getenv('HOSPITAL_SETUP_DB_USER')?:'root';$c['db']['password']=getenv('HOSPITAL_SETUP_DB_PASSWORD')?:'';
        $db=Hospital\Database::connect($c,false);$db->exec('DROP DATABASE IF EXISTS '.chr(96).$name.chr(96));
        foreach(['localhost','127.0.0.1'] as $host)$db->exec('DROP USER IF EXISTS '.$db->quote($user).'@'.$db->quote($host));"""
        subprocess.run([PHP, '-r', cleanup], cwd=ROOT,
                       env={**env, 'HOSPITAL_APP_KEY': secrets.token_hex(32)}, check=True, capture_output=True)
        shutil.rmtree(source/'storage', ignore_errors=True)
        (source/'backend/php/config/local.php').unlink(missing_ok=True)
        report['cleaned'] = True
        (output/'report.json').write_text(json.dumps(report, indent=2)+'\n')
    print('Completed '+str(report['checks'])+' fresh-install checks; disposable resources cleaned.')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=ROOT/'tests/.runtime/install'/secrets.token_hex(4))
    parser.add_argument('--browser', action='store_true')
    args = parser.parse_args()
    run(args.output, args.browser)
