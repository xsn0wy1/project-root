"""Page/JSON exception contracts using the existing disposable theme fixture and PHP server."""
import json
import grp
import pwd
import sys
import os
from pathlib import Path
import socket
import subprocess
import time
import urllib.error
import urllib.request
from http.cookiejar import CookieJar

ROOT = Path(__file__).resolve().parents[1]
RUNTIME = Path(os.environ.get('HOSPITAL_THEME_TEST_DIR', str(ROOT/'tests/.runtime/theme'))).resolve()
RUNTIME.relative_to(ROOT/'tests/.runtime')
ENV = dict(os.environ, **json.loads((RUNTIME/'env.json').read_text()))
assert ENV['HOSPITAL_DB_NAME'].endswith('_test')
PHP = ENV.get('HOSPITAL_TEST_PHP', '/Applications/XAMPP/xamppfiles/bin/php')
OUTPUT = RUNTIME/'error-contracts'
OUTPUT.mkdir(exist_ok=True)
BASELINE = Path(os.environ.get('HOSPITAL_ERROR_BASELINE', str(ROOT))).resolve()
PROBE = r'''<?php
$root = getenv('HOSPITAL_ERROR_SOURCE');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('#/(?:api/v1|src/views)/error-probe-([1-5][0-9]{2}|runtime|lost-db|partial)\.php$#', $path, $match)) {
    require $root . '/backend/php/bootstrap.php';
    $GLOBALS['request_id'] = '0123456789abcdef01234567';
    if ($match[1] === 'runtime') throw new RuntimeException('Fictional private failure detail');
    if ($match[1] === 'partial') {
        echo str_repeat('<p>Fictional partial output</p>', 500);
        throw new RuntimeException('Fictional private failure detail');
    }
    if ($match[1] === 'lost-db') {
        $db->exec('KILL ' . (int) $db->query('SELECT CONNECTION_ID()')->fetchColumn());
        $db->query('SELECT 1');
    }
    throw new Hospital\Http\ApiException((int) $match[1], 'SERVICE_UNAVAILABLE', 'Fictional exception fixture', ['fixture' => 'unchanged']);
}
require $root . '/backend/php/dev-router.php';
'''
(OUTPUT/'router.php').write_text(PROBE)
checks = 0
servers = []
report = {'baseline_compared': BASELINE != ROOT, 'apache': [], 'normal_html_pairs': [], 'api_byte_identical': [], 'page_errors': [], 'logs_preserved': [], 'checks': 0}


def check(value, label):
    global checks
    assert value, label
    checks += 1
    print('PASS '+label, flush=True)


def start(source, port, mount):
    with socket.socket() as probe:
        probe.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        probe.bind(('127.0.0.1', port))
    env = dict(ENV, HOSPITAL_ERROR_SOURCE=str(source), HOSPITAL_BASE_PATH=mount,
               HOSPITAL_APP_URL=f'http://127.0.0.1:{port}{mount}/')
    log = OUTPUT/f'server-{port}.log'
    stream = log.open('w')
    server = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', str(source/'public'), str(OUTPUT/'router.php')],
                              cwd=source, env=env, stdout=stream, stderr=stream)
    servers.append((server, stream))
    base = f'http://127.0.0.1:{port}{mount}'
    for attempt in range(60):
        try:
            urllib.request.urlopen(base+'/index.php', timeout=1).close()
            return base, log
        except OSError:
            if server.poll() is not None:
                raise RuntimeError('Contract probe server stopped.')
            time.sleep(.05)
    raise RuntimeError('Contract probe server did not start.')


def call(opener, url, locale):
    request = urllib.request.Request(url, headers={'X-Hospital-Locale': locale})
    try:
        response = opener.open(request, timeout=10)
    except urllib.error.HTTPError as error:
        response = error
    # Date/Host describe the two test transports; application headers/body remain exact.
    return response.status, {k.lower(): v for k, v in response.headers.items() if k.lower() not in ['date', 'host']}, response.read()




def normal_html(before, after, mount):
    os.environ.update(ENV)
    from auth_integration import Client
    current = Client(after)
    assert current.login('system_admin', ENV['HOSPITAL_ADMIN_PASSWORD'])[0] == 200
    original = current.copy_session()
    original.base = before
    records = json.loads((RUNTIME/'records.json').read_text())
    ids = {'documents-viewer.php': records['document']['id'], 'documents-edit.php': records['document']['id'],
           'trace-view.php': records['transfer']['id'], 'survey-view.php': records['survey']['id'],
           'survey-new.php': records['survey']['id']}
    for locale in ['es-UY', 'en']:
        for theme in ['dark', 'light']:
            settings = current.call('/api/v1/settings.php?action=get')[1]['data']['settings']
            assert current.call('/api/v1/settings.php?action=preferences-update', 'POST',
                                dict(version=settings['version'], locale=locale, theme=theme))[0] == 200
            for file in sorted((BASELINE/'public').rglob('*.php')):
                relative = file.relative_to(BASELINE/'public').as_posix()
                if relative.endswith('/error.php'):
                    continue
                path = '/'+relative+'?lang='+locale
                if file.name in ids:
                    path += '&id='+ids[file.name]
                left = original.call(path)
                right = current.call(path)
                body = right[1]
                if file.name == 'documents-viewer.php':
                    # These are exactly the two separately reviewed archive-label changes.
                    replacement = 'data-i18n="patients.archivar">Archivar'
                    assert body.count(replacement) == 2
                    body = body.replace(replacement, 'data-i18n="documents_viewer.eliminar_documento">Eliminar documento')
                check(left[0] == right[0] == 200 and left[1] == body,
                      'normal page HTML retains exact bytes: '+mount+'/'+locale+'/'+theme+'/'+relative)
                report['normal_html_pairs'].append([mount, locale, theme, relative])

def apache_checks():
    apache = Path(PHP).with_name('httpd')
    apache_port = int(os.environ.get('HOSPITAL_ERROR_APACHE_PORT', '8895'))
    for port, mount in [(apache_port, ''), (apache_port + 1, '/hospital')]:
        with socket.socket() as probe:
            probe.bind(('127.0.0.1', port))
        name = 'mounted' if mount else 'root'
        conf = OUTPUT/f'apache-{name}.conf'
        lines = ['ServerRoot "/Applications/XAMPP/xamppfiles"', f'Listen 127.0.0.1:{port}',
                 'ServerName 127.0.0.1', f'PidFile "{OUTPUT}/apache-{name}.pid"',
                 f'ErrorLog "{OUTPUT}/apache-{name}.log"']
        lines += [f'LoadModule {module}_module modules/mod_{module}.so'
                  for module in ['unixd', 'authz_core', 'authz_host', 'dir', 'mime', 'alias', 'rewrite', 'headers']]
        lines += ['LoadModule php_module modules/libphp.so', 'AddType application/x-httpd-php .php',
                  'TypesConfig /Applications/XAMPP/xamppfiles/etc/mime.types',
                  'User '+pwd.getpwuid(os.getuid()).pw_name, 'Group '+grp.getgrgid(os.getgid()).gr_name,
                  'StartServers 1', 'MinSpareServers 1', 'MaxSpareServers 2', 'MaxRequestWorkers 3',
                  f'DocumentRoot "{ROOT}/public"', f'Alias "{mount}/api/v1/" "{ROOT}/backend/api/v1/"']
        if mount:
            lines.append(f'Alias "{mount}/" "{ROOT}/public/"')
        lines += [f'<Directory "{ROOT}/public">', 'Options -Indexes +FollowSymLinks',
                  'AllowOverride FileInfo Options', 'Require all granted', 'DirectoryIndex index.php',
                  'RewriteEngine On', 'RewriteRule "^apache-error-403.php$" "-" [R=403,L]',
                  'RewriteRule "^apache-error-500.php$" "-" [R=500,L]', '</Directory>',
                  f'<Directory "{ROOT}/backend/api/v1">', 'Require all granted', 'AllowOverride None',
                  '</Directory>', f'<Location "{mount}/api/v1/">',
                  f'ErrorDocument 404 {mount}/api/v1/errors/not-found.php', '</Location>']
        conf.write_text('\n'.join(lines)+'\n')
        env = dict(ENV, HOSPITAL_BASE_PATH=mount, HOSPITAL_APP_URL=f'http://127.0.0.1:{port}{mount}/')
        subprocess.run([str(apache), '-t', '-f', str(conf)], env=env, check=True, capture_output=True)
        with (OUTPUT/f'apache-{name}-stdout.log').open('w') as log:
            server = subprocess.Popen([str(apache), '-D', 'FOREGROUND', '-f', str(conf)],
                                      env=env, stdout=log, stderr=log, start_new_session=True)
            try:
                base = f'http://127.0.0.1:{port}{mount}'
                for attempt in range(60):
                    try:
                        urllib.request.urlopen(base+'/index.php', timeout=1).close()
                        break
                    except OSError:
                        if server.poll() is not None:
                            raise RuntimeError('Isolated Apache stopped.')
                        time.sleep(.05)
                opener = urllib.request.build_opener()
                for route, status in [('/apache-error-403.php', 403), ('/src/views/apache-missing.php', 404),
                                      ('/apache-error-500.php', 500), ('/src/views/error.php?status=503', 503),
                                      ('/api/v1/missing.php', 404)]:
                    code, headers, body = call(opener, base+route, 'en')
                    check(code == status and (b'"success":false' in body if route.startswith('/api/v1/') else b'error-card' in body),
                          'Apache ErrorDocument preserves status and HTML/JSON routing: '+mount+route)
                    report['apache'].append([mount, route, status])
                    if not route.startswith('/api/v1/'):
                        separator = '&' if '?' in route else '?'
                        code, headers, body = call(opener, base+route+separator+'lang=en', 'es-UY')
                        check(code == status and headers['content-language'] == 'en',
                              'Apache error retains the original English query language: '+mount+route)
                code, _, body = call(opener, base+'/assets/missing-error-check.css', 'en')
                check(code == 404 and body == b'', 'Apache missing static assets retain a plain 404: '+mount)
            finally:
                server.terminate()
                server.wait(timeout=10)

try:
    for offset, mount in [(0, ''), (2, '/hospital')]:
        before, _ = start(BASELINE, 8886+offset, mount)
        after, log = start(ROOT, 8887+offset, mount)
        opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(CookieJar()))
        call(opener, before+'/api/v1/auth.php?action=csrf', 'es-UY')
        for locale in ['es-UY', 'en']:
            for suffix in [prefix+'error-probe-'+str(status)+'.php' for prefix in ['/api/v1/', '/%61pi/v1/']
                           for status in [401,403,404,405,409,422,429,500,503,'runtime']]:
                path = suffix
                original = call(opener, before+path, locale)
                current = call(opener, after+path, locale)
                if original != current:
                    (OUTPUT/'mismatch.json').write_text(json.dumps({'before': [original[0], original[1], original[2].decode()], 'after': [current[0], current[1], current[2].decode()]}))
                check(original == current, 'byte-identical API error body/status/application headers: '+mount+'/'+locale+'/'+suffix)
                report['api_byte_identical'].append([mount, locale, suffix])
            for suffix, status in [('500', 500), ('503', 503), ('422', 500), ('runtime', 503), ('lost-db', 503), ('partial', 503)]:
                current = call(opener, after+'/src/views/error-probe-'+suffix+'.php?lang='+locale, locale)
                body = current[2].decode()
                check(current[0] == status and 'text/html' in current[1]['content-type'] and 'error-card' in body,
                      'page exception renders HTML: '+mount+'/'+locale+'/'+suffix)
                check('0123456789abcdef01234567' in body and 'Fictional private failure detail' not in body
                      and 'Fictional partial output' not in body and body.count('<!DOCTYPE html>') == 1,
                      'page exception exposes only the request ID: '+mount+'/'+locale+'/'+suffix)
                report['page_errors'].append([mount, locale, suffix])
        if '--html' in sys.argv:
            normal_html(before, after, mount)
        content = log.read_text()
        check('Hospital request 0123456789abcdef01234567 failed: RuntimeException at router.php:' in content,
              'existing safe exception diagnostics are still logged: '+mount)
        report['logs_preserved'].append(mount)
    if '--apache' in sys.argv:
        apache_checks()
    report['checks'] = checks
    print('TOTAL', checks, 'page/API exception contract checks')
finally:
    for server, stream in servers:
        server.terminate()
        server.wait(timeout=10)
        stream.close()
    (OUTPUT/'report.json').write_text(json.dumps(report, indent=2)+'\n')
