"""Destructive demo checks, restricted to an explicitly prepared disposable theme database.

First run theme_environment.py prepare with HOSPITAL_THEME_TEST_DIR/PORT overrides.
This reuses that environment and the real configured router; it never touches local.php.
"""
import argparse
import json
import os
from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('HOSPITAL_TEST_PHP', '/Applications/XAMPP/xamppfiles/bin/php')
SCRIPT = 'backend/php/cli/demo-data.php'


def php(environment, code):
    result = subprocess.run([PHP, '-r', code], cwd=ROOT, env=environment,
                            text=True, capture_output=True, check=True)
    return result.stdout.strip()


def snapshot(environment):
    return php(environment, r'''
require 'backend/php/autoload.php';
$db = Hospital\Database::connect(Hospital\Config::load());
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
sort($tables);
$rows = [];
foreach ($tables as $table) {
    $encoded = [];
    foreach ($db->query('SELECT * FROM `' . $table . '`')->fetchAll() as $row) {
        $encoded[] = json_encode($row, JSON_THROW_ON_ERROR);
    }
    sort($encoded);
    $rows[$table] = $encoded;
}
echo hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
''')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--environment', type=Path, required=True)
    args = parser.parse_args()
    environment = dict(os.environ)
    environment.update(json.loads(args.environment.read_text()))
    database = environment['HOSPITAL_DB_NAME']
    assert database.startswith('hospital_theme_') and database.endswith('_test')
    environment.update(HOSPITAL_ROUTE_MAPPING_ENABLED='true',
                       HOSPITAL_GEOCODER_URL='https://127.0.0.1:1/no-geocoding',
                       HOSPITAL_ROUTER_URL='https://router.project-osrm.org/route/v1/driving')
    output = args.environment.resolve().parent.parent
    checks = []

    def command(arguments, overrides=None, input_text=None):
        configured = dict(environment)
        configured.update(overrides or {})
        return subprocess.run([PHP, SCRIPT, *arguments], cwd=ROOT, env=configured,
                              input=input_text, text=True, capture_output=True)

    unchanged = snapshot(environment)
    cases = [
        ('confirmation declined', ['--reset'], {}, 'NO\n', 0),
        ('reset required', [], {}, None, 1),
        ('production name refused', ['--reset', '--yes'], {'HOSPITAL_DB_NAME': 'hospital_production_test'}, None, 1),
        ('unmarked name refused', ['--reset', '--yes'], {'HOSPITAL_DB_NAME': 'hospital'}, None, 1),
        ('remote database refused', ['--reset', '--yes'], {'HOSPITAL_DB_HOST': '198.51.100.2'}, None, 1),
        ('mapping disabled refused', ['--reset', '--yes'], {'HOSPITAL_ROUTE_MAPPING_ENABLED': 'false'}, None, 1),
        ('unreachable router preserves data', ['--reset', '--yes'], {'HOSPITAL_ROUTER_URL': 'https://127.0.0.1:1/route/v1/driving'}, None, 1),
    ]
    for name, arguments, overrides, input_text, status in cases:
        result = command(arguments, overrides, input_text)
        assert result.returncode == status, name + ': ' + result.stderr
        assert snapshot(environment) == unchanged, name + ' changed existing records'
        checks.append(name)

    php(environment, "require 'backend/php/cli/demo-data.php';")
    assert snapshot(environment) == unchanged
    checks.append('importing helpers does not seed')

    lock_code = r'''
require 'backend/php/autoload.php';
$config = Hospital\Config::load();
$db = Hospital\Database::connect($config);
$statement = $db->prepare('SELECT GET_LOCK(?,0)');
$statement->execute([transfer_lock_name($config, 'worker')]);
if ((int)$statement->fetchColumn() !== 1) exit(1);
echo "ready\n";
fflush(STDOUT);
fgets(STDIN);
'''
    owner = subprocess.Popen([PHP, '-r', lock_code], cwd=ROOT, env=environment,
                             stdin=subprocess.PIPE, stdout=subprocess.PIPE, text=True)
    try:
        assert owner.stdout.readline().strip() == 'ready'
        result = command(['--reset', '--yes'])
        assert result.returncode == 1 and 'worker' in result.stderr
        assert snapshot(environment) == unchanged
        checks.append('active worker prevents reset')
    finally:
        owner.communicate('\n', timeout=5)

    credentials_path = ROOT / 'tests/.runtime/demo-data' / database / 'credentials.json'
    for run in range(2):
        result = command(['--reset', '--yes'])
        (output / ('seed-' + str(run + 1) + '.log')).write_text(result.stdout + result.stderr)
        assert result.returncode == 0, result.stderr
        credentials = json.loads(credentials_path.read_text())
        for account in credentials['accounts'].values():
            assert account['password'] not in result.stdout + result.stderr
            assert account['password'] not in (credentials_path.parent / 'upload-server.log').read_text()
        assert credentials_path.stat().st_mode & 0o777 == 0o600
        checks.append('repeatable seed ' + str(run + 1) + '; private passwords')
    checks.append('stored coordinates work with an unreachable geocoder')

    verification = json.loads(php(environment, r'''
require 'backend/php/cli/demo-data.php';
$config = Hospital\Config::load();
$db = Hospital\Database::connect($config);
$states = $db->query('SELECT status,COUNT(*) FROM transfers GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$surveys = $db->query('SELECT s.status,COUNT(r.id) responses FROM surveys s LEFT JOIN survey_responses r ON r.survey_id=s.id GROUP BY s.id ORDER BY responses')->fetchAll();
$routes = $db->query('SELECT geometry,distance_meters,duration_seconds FROM route_templates')->fetchAll();
$points = [];
foreach ($routes as $route) {
    $points[] = count(json_decode($route['geometry'], true, 64, JSON_THROW_ON_ERROR));
    if ($route['distance_meters'] <= 100 || $route['duration_seconds'] <= 60) throw new RuntimeException('Unrealistic route estimates.');
}
$files = $db->query('SELECT storage_key,sha256,purpose FROM stored_files')->fetchAll();
$storage = new Hospital\Storage\PrivateStorage($config);
foreach ($files as $file) {
    if ($file['purpose'] !== 'document') throw new RuntimeException('Old photo remains.');
    $path = $storage->path('documents', $file['storage_key']);
    if (!hash_equals($file['sha256'], hash_file('sha256', $path))) throw new RuntimeException('Stored bytes changed.');
    if (substr(file_get_contents($path), 0, 8) !== '%PDF-1.4') throw new RuntimeException('Missing generated PDF.');
}
$span = $db->query('SELECT DATEDIFF(UTC_TIMESTAMP(),MIN(scheduled_at)) past_days,DATEDIFF(MAX(scheduled_at),UTC_TIMESTAMP()) future_days FROM transfers')->fetch();
echo json_encode(['states'=>$states,'surveys'=>$surveys,'path_points'=>$points,'pdf_files'=>count($files),'span'=>$span], JSON_THROW_ON_ERROR);
'''))
    assert verification['states'] == {'cancelled': 2, 'completed': 6, 'in_progress': 2, 'scheduled': 2}
    assert sorted(row['responses'] for row in verification['surveys']) == [0, 32, 36]
    assert min(verification['path_points']) > 20 and verification['pdf_files'] == 6
    assert verification['span']['past_days'] >= 28 and verification['span']['future_days'] >= 2
    checks.append('states, dates, routed paths, immutable PDF bytes and survey totals')
    (output / 'cli-checks.json').write_text(json.dumps({'passed': True, 'checks': checks, 'data': verification}, indent=2) + '\n')
    print(str(len(checks)) + ' demo checks passed; both explicit resets completed.')


if __name__ == '__main__':
    main()
