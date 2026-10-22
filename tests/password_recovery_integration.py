"""Focused recovery checks in a disposable SQL database; optional --browser adds Chrome checks."""
import hashlib
import http.cookiejar
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import os
import re
import secrets
import shlex
import shutil
import subprocess
import sys
import time
import threading
import urllib.error
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
RUNTIME=Path(os.environ.get('HOSPITAL_RECOVERY_TEST_DIR', str(ROOT/'tests/.runtime/password-recovery'))).resolve()
RUNTIME.relative_to(ROOT/'tests/.runtime')
PHP=os.environ.get('HOSPITAL_TEST_PHP','/Applications/XAMPP/xamppfiles/bin/php')
NODE=os.environ.get('HOSPITAL_TEST_NODE',str(Path.home()/'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node'))
env=dict(os.environ)
env.setdefault('NODE_PATH',str(Path.home()/'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'))
env.update(HOSPITAL_DB_NAME='hospital_recovery_'+secrets.token_hex(4)+'_test',HOSPITAL_DB_USER='recovery_'+secrets.token_hex(4),HOSPITAL_DB_PASSWORD=secrets.token_hex(24),HOSPITAL_DB_SOCKET='/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock',HOSPITAL_APP_KEY=secrets.token_hex(32),HOSPITAL_STORAGE_PATH=str(RUNTIME/'storage'),HOSPITAL_BASE_PATH='',HOSPITAL_SECURE_COOKIES='false',HOSPITAL_BCRYPT_COST='10',HOSPITAL_BREVO_API_KEY='offline-test-key',HOSPITAL_MAIL_FROM_EMAIL='sender@hospital.example',HOSPITAL_APP_URL='http://127.0.0.1:8881/',HOSPITAL_ADMIN_PASSWORD=secrets.token_urlsafe(24))
servers=[]
mail_peer=None
mail_started=threading.Event()
mail_requests=[]
mail_status=201
checks=0
PASSWORD=secrets.token_urlsafe(20)
NEW_PASSWORD=secrets.token_urlsafe(20)

def check(value,label):
    global checks
    assert value,label
    checks+=1
    print('PASS',label,flush=True)

def php(code,stdin='',override=None):
    result=subprocess.run([PHP,'-r',code],input=stdin,text=True,cwd=ROOT,env=override or env,capture_output=True,check=True)
    return json.loads(result.stdout)

def sql(statement,administrative=False):
    assert env['HOSPITAL_DB_NAME'].endswith('_test')
    override=dict(env,HOSPITAL_DB_USER=env.get('HOSPITAL_SETUP_DB_USER','root'),HOSPITAL_DB_PASSWORD=env.get('HOSPITAL_SETUP_DB_PASSWORD','')) if administrative else env
    return php('require "backend/php/autoload.php";$db=Hospital\\Database::connect(Hospital\\Config::load());$s=$db->query(stream_get_contents(STDIN));echo json_encode($s->columnCount()?$s->fetchAll():[]);',statement,override)

def service(action,override=None,**values):
    result=subprocess.run([PHP,'backend/php/tests/password-recovery.php'],input=json.dumps(dict(action=action,**values)),text=True,cwd=ROOT,env=override or env,capture_output=True,check=True)
    # Only the test transport returns raw links; output is never printed or committed.
    if result.stderr:
        assert 'sensitive provider' not in result.stderr
        assert 'request recovery-test' in result.stderr
    return json.loads(result.stdout)

def request(email='active@hospital.example',**kw):
    sql('DELETE FROM auth_rate_limits')
    result=service('request',email=email,**kw)
    assert result['status']==200,result
    return result

def token(result,index=-1):
    return re.search(r'#token=([a-f0-9]{64})',result['mails'][index]['textContent'])[1]

class Client:
    api_base_path = ''
    def __init__(self,base='http://127.0.0.1:8881'):
        self.base=base
        self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf=None
    def call(self,path,body=None,csrf=True,method=None):
        if body is not None and csrf and self.csrf is None:self.start()
        headers={'Accept':'application/json','X-Hospital-Locale':'en'}
        if body is not None:headers['Content-Type']='application/json'
        if csrf and self.csrf:headers['X-CSRF-Token']=self.csrf
        req=urllib.request.Request(self.base+'/api/v1/'+path,data=json.dumps(body).encode() if body is not None else None,headers=headers,method=method)
        try:r=self.opener.open(req,timeout=15)
        except urllib.error.HTTPError as error:r=error
        payload=json.loads(r.read())
        if isinstance(payload.get('data'),dict):
            if payload['data'].get('csrf_token'):self.csrf=payload['data']['csrf_token']
            if payload['data'].get('sign_in_required'):self.csrf=None
        return r.status,payload,r.headers
    def start(self):
        self.csrf=self.call('auth.php?action=csrf')[1]['data']['csrf_token']
        return self

def serve(port,mount='',configured=True,override=None,php_options=()):
    serving=dict(env,HOSPITAL_BASE_PATH=mount,HOSPITAL_APP_URL=f'http://127.0.0.1:{port}{mount}/')
    if not configured:serving['HOSPITAL_BREVO_API_KEY']=''
    serving.update(override or {})
    log=(RUNTIME/f'server-{port}.log').open('w')
    p=subprocess.Popen([PHP,*php_options,'-S',f'127.0.0.1:{port}','-t','public','backend/php/dev-router.php'],cwd=ROOT,env=serving,stdout=log,stderr=log)
    servers.append((p,log))
    for _ in range(60):
        try:urllib.request.urlopen(f'http://127.0.0.1:{port}{mount}/index.php',timeout=1).close();return
        except OSError:
            if p.poll() is not None:raise RuntimeError('Disposable server did not start.')
            time.sleep(.05)
    raise RuntimeError('Disposable server timed out.')

def readiness_checks(client):
    def preflight(override=None, options=()):
        result=subprocess.run([PHP,*options,'backend/php/cli/preflight.php'],cwd=ROOT,
                              env=dict(env,**(override or {})),capture_output=True,text=True,timeout=8)
        for secret in [env['HOSPITAL_APP_KEY'],env['HOSPITAL_DB_PASSWORD'],env['HOSPITAL_BREVO_API_KEY']]:
            assert secret not in result.stdout+result.stderr,'Readiness output disclosed a private value'
        assert not result.stderr,result.stderr
        return result.returncode,json.loads(result.stdout)
    code,report=preflight({'HOSPITAL_BREVO_API_KEY':'','HOSPITAL_MAIL_FROM_EMAIL':''})
    check(code==0 and report['healthy'] and report['recovery_email']['status']=='unconfigured',
          'optional unconfigured mail does not make the installation unhealthy')
    code,report=preflight()
    check(code==0 and report['recovery_email']['status']=='ready' and
          all(report['recovery_email']['checks'].values()),'configured mail preflight checks real CLI/process/storage readiness')
    code,report=preflight({'HOSPITAL_MAIL_PHP_BINARY':''})
    check(code==0 and report['recovery_email']['status']=='ready','automatic PHP CLI discovery remains available')
    executable=RUNTIME/'non executable php';executable.write_text('#!/bin/sh\nexit 0\n');executable.chmod(0o600)
    restricted_cli=RUNTIME/'php with disabled curl'
    restricted_cli.write_text('#!/bin/sh\nexec '+shlex.quote(PHP)+' -d disable_functions=curl_init "$@"\n');restricted_cli.chmod(0o700)
    slow=RUNTIME/'slow php';slow.write_text('#!/bin/sh\nexec /bin/sleep 5\n');slow.chmod(0o700)
    for program,label in [('php','relative override'),(str(RUNTIME/'missing php'),'missing executable'),
                          (str(executable),'non executable file'),('/usr/bin/true','wrong runtime'),
                          (str(restricted_cli),'disabled CLI transport functions')]:
        code,report=preflight({'HOSPITAL_MAIL_PHP_BINARY':program})
        check(code==1 and not report['healthy'] and report['recovery_email']['status']=='unavailable',
              'mail preflight rejects '+label)
    began=time.monotonic();code,report=preflight({'HOSPITAL_MAIL_PHP_BINARY':str(slow)})
    check(code==1 and report['recovery_email']['status']=='unavailable' and time.monotonic()-began<4.5,
          'mail CLI readiness probe has a bounded timeout')
    code,report=preflight(options=('-d','disable_functions=proc_open'))
    check(code==1 and report['recovery_email']['checks']['process_functions'] is False,
          'mail preflight identifies disabled process execution')
    code,report=preflight({'HOSPITAL_MAIL_FROM_EMAIL':'invalid'})
    check(code==1 and report['recovery_email']['checks']['configuration'] is False,
          'partially configured mail is unavailable instead of silently disabled')
    serve(8884,override={'HOSPITAL_MAIL_PHP_BINARY':str(RUNTIME/'missing php')})
    serve(8885,php_options=('-d','disable_functions=proc_open'))
    for port,label in [(8884,'missing PHP CLI'),(8885,'disabled process execution')]:
        unavailable=Client('http://127.0.0.1:'+str(port)).start()
        outcomes=[unavailable.call('auth.php?action=reset-request',{'email':address}) for address in
                  ['active@hospital.example','unknown@hospital.example']]
        check(all(row[0]==503 and row[1]['error']['code']=='RECOVERY_UNAVAILABLE' for row in outcomes),
              label+' returns uniform unavailable before account lookup')
    directory=RUNTIME/'storage/mail';previous=directory.stat().st_mode&0o777;directory.chmod(0o500)
    try:
        code,report=preflight()
        outcomes=[client.call('auth.php?action=reset-request',{'email':address})[0] for address in
                  ['active@hospital.example','unknown@hospital.example']]
        check(code==1 and report['recovery_email']['checks']['storage'] is False and outcomes==[503,503],
              'unwritable mail storage is detected consistently by CLI and HTTP')
    finally:directory.chmod(previous)
    check(not sql('SELECT * FROM password_reset_tokens') and not mail_requests and
          not list(directory.glob('*.bin')),'readiness checks create no reset tokens, delivery jobs or provider requests')

def run():
    executable=RUNTIME/'php cli with spaces/php'
    executable.parent.mkdir(parents=True,exist_ok=True)
    executable.symlink_to(PHP)
    env['HOSPITAL_MAIL_PHP_BINARY']=str(executable)
    os.environ.update(env)
    global manage_account
    from auth_integration import manage_account
    global mail_peer
    class MockMail(BaseHTTPRequestHandler):
        def log_message(self,*args):pass
        def do_POST(self):
            status=mail_status
            mail_requests.append(json.loads(self.rfile.read(int(self.headers['Content-Length']))))
            mail_started.set();time.sleep(3)
            body=b'{"messageId":"offline-mock-message"}'
            self.send_response(status);self.send_header('Content-Type','application/json');self.send_header('Content-Length',str(len(body)));self.end_headers();self.wfile.write(body)
    mail_peer=ThreadingHTTPServer(('127.0.0.1',0),MockMail)
    threading.Thread(target=mail_peer.serve_forever,daemon=True).start()
    env['HOSPITAL_MAIL_API_URL']='http://127.0.0.1:'+str(mail_peer.server_address[1])+'/email'
    for args in [['setup.php','--no-config'],['users.php','create-admin','system_admin','admin@hospital.example','99000111']]:
        subprocess.run([PHP,'backend/php/cli/'+args[0],*args[1:]],cwd=ROOT,env=env,check=True,stdout=subprocess.DEVNULL)
    check(sql("SELECT version FROM schema_migrations ORDER BY version DESC LIMIT 1")[0]['version']=='014_settings_centers','fresh schema includes all current migrations')
    before=sql('SELECT id,password_hash,auth_version FROM users')
    sql('DROP TABLE password_reset_tokens',administrative=True)
    sql("DELETE FROM schema_migrations WHERE version='012_password_recovery'")
    subprocess.run([PHP,'backend/php/cli/migrate.php'],cwd=ROOT,env=env,check=True,stdout=subprocess.DEVNULL)
    check(sql('SELECT id,password_hash,auth_version FROM users')==before,'upgrade/repeated migration preserves account credentials')
    serve(8881);serve(8882,'/hospital');serve(8883,configured=False)
    c=Client().start();admin=Client().start()
    check(admin.call('auth.php?action=login',{'username':'system_admin','password':env['HOSPITAL_ADMIN_PASSWORD']})[0]==200,'administrator fixture logs in')
    ids={}
    for n,status in enumerate(['active','pending','disabled']):
        result=c.call('auth.php?action=register',{'username':status+'_fixture','email':status+'@hospital.example','national_id':str(99000200+n),'password':PASSWORD})
        ids[status]=result[1]['data']['user_id']
        if status!='pending':
            assert manage_account(admin, 'approve', {'user_id':ids[status],'role':'staff'})[0]==200
        if status=='disabled':sql("UPDATE users SET status='disabled',auth_version=auth_version+1 WHERE id="+ids[status])
    readiness_checks(c)
    check(c.call('auth.php?action=reset-request',{'email':'active@hospital.example'},csrf=False)[0]==403,'request requires CSRF')
    check(c.call('auth.php?action=reset-password',{'token':'a'*64,'new_password':NEW_PASSWORD},csrf=False)[0]==403,'redemption requires CSRF')
    check(c.call('auth.php?action=reset-request')[0]==405 and c.call('auth.php?action=reset-password')[0]==405,'GET cannot request mail or reset passwords')
    missing=Client('http://127.0.0.1:8883').start()
    check(all(missing.call('auth.php?action=reset-request',{'email':email})[0]==503 for email in ['active@hospital.example','unknown@hospital.example']),'missing sender configuration is uniformly unavailable')
    check(c.call('auth.php?action=reset-request',{'email':'bad'})[0]==422 and c.call('auth.php?action=reset-request',{'email':'unknown@hospital.example','role':'admin'})[0]==422,'invalid email and unexpected fields rejected')
    a=c.call('auth.php?action=reset-request',{'email':'unknown@hospital.example'});b=c.call('auth.php?action=reset-request',{'email':'disabled@hospital.example'})
    check(a[0]==b[0]==202 and a[1]['data']==b[1]['data'],'unknown/disabled HTTP confirmations match')
    active=request('  ACTIVE@HOSPITAL.EXAMPLE  ')
    raw=token(active);row=sql('SELECT * FROM password_reset_tokens WHERE user_id='+ids['active'])[0]
    check(row['token_hash']==hashlib.sha256(raw.encode()).hexdigest() and raw not in json.dumps(row),'normalized email lookup stores only a token hash')
    check((datetime.fromisoformat(row['expires_at'])-datetime.fromisoformat(row['created_at'])).total_seconds()==1800,'SQL expiry is 30 minutes')
    check(len(active['mails'])==1 and active['mails'][0]['to']==[{'email':'active@hospital.example'}],'mail is addressed only to the matched account')
    check(request('unknown@hospital.example')['mails']==request('disabled@hospital.example')['mails']==[],'unknown/disabled addresses cause no email delivery')
    pending=request('pending@hospital.example');pending_token=token(pending)
    check(c.call('auth.php?action=reset-password',{'token':pending_token,'new_password':NEW_PASSWORD})[0]==200 and sql('SELECT status,staff_id FROM users WHERE id='+ids['pending'])[0]=={'status':'pending','staff_id':None},'pending credentials reset without granting approval/staff identity')
    check(Client().start().call('auth.php?action=login',{'username':'pending_fixture','password':NEW_PASSWORD})[0]==403,'recovered pending account still cannot log in')
    active=request(locale='en');raw=token(active)
    check('?lang=en#token=' in active['mails'][0]['textContent'] and 'Reset password' in active['mails'][0]['subject'],'English email carries language and fragment credential')
    newer=token(request())
    check(c.call('auth.php?action=reset-password',{'token':raw,'new_password':NEW_PASSWORD})[1]['error']['code']=='RESET_TOKEN_INVALID','replacement link invalidates the previous one')
    sql('UPDATE password_reset_tokens SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND')
    check(c.call('auth.php?action=reset-password',{'token':newer,'new_password':NEW_PASSWORD})[0]==422,'expired token cannot reset credentials')
    raw=token(request())
    check(c.call('auth.php?action=reset-password',{'token':raw,'new_password':'short'})[0]==422 and c.call('auth.php?action=reset-password',{'token':raw,'new_password':'é'*40})[0]==422,'password character/UTF-8 byte rules retained')
    staff=Client().start();other=Client().start()
    for client in [staff,other]:assert client.call('auth.php?action=login',{'username':'active_fixture','password':PASSWORD})[0]==200
    previous=sql('SELECT auth_version,version FROM users WHERE id='+ids['active'])[0]
    check(c.call('auth.php?action=reset-password',{'token':raw,'new_password':NEW_PASSWORD})[0]==200,'valid link commits new password')
    current=sql('SELECT auth_version,version FROM users WHERE id='+ids['active'])[0]
    check(all(int(current[k])==int(previous[k])+1 for k in current) and not sql('SELECT * FROM password_reset_tokens WHERE user_id='+ids['active']),'reset increments revisions and consumes credential atomically')
    check(staff.call('auth.php?action=me')[0]==other.call('auth.php?action=me')[0]==401,'all existing sessions are revoked')
    check(Client().start().call('auth.php?action=login',{'username':'active_fixture','password':PASSWORD})[0]==401 and Client().start().call('auth.php?action=login',{'username':'active_fixture','password':NEW_PASSWORD})[0]==200,'old password fails and new password logs in')
    check(c.call('auth.php?action=reset-password',{'token':raw,'new_password':PASSWORD})[0]==422,'used link cannot be replayed')
    raw=token(request());sql('DELETE FROM auth_rate_limits')
    with ThreadPoolExecutor(2) as pool:
        results=list(pool.map(lambda _:service('reset',body={'token':raw,'new_password':NEW_PASSWORD}),range(2)))
    check(sorted(r['status'] for r in results)==[200,422],'concurrent redemption has exactly one winner')
    raw=token(request());sql('UPDATE users SET auth_version=auth_version+1 WHERE id='+ids['active'])
    check(service('reset',body={'token':raw,'new_password':PASSWORD})['status']==422,'authentication revision changes invalidate outstanding links')
    raw=token(request());sql("UPDATE users SET status='disabled' WHERE id="+ids['active'])
    check(service('reset',body={'token':raw,'new_password':PASSWORD})['status']==422,'redemption rechecks disabled status')
    sql("UPDATE users SET status='active' WHERE id="+ids['active'])
    staff=Client().start();assert staff.call('auth.php?action=login',{'username':'active_fixture','password':NEW_PASSWORD})[0]==200
    raw=token(request());v=staff.call('settings.php?action=get')[1]['data']['settings']['version']
    check(staff.call('settings.php?action=profile-update',{'version':v,'email':'changed@hospital.example'})[0]==200 and not sql('SELECT * FROM password_reset_tokens WHERE user_id='+ids['active']),'email changes remove outstanding recovery credentials')
    v=staff.call('settings.php?action=get')[1]['data']['settings']['version'];staff.call('settings.php?action=profile-update',{'version':v,'email':'active@hospital.example'})
    check(service('reset',body={'token':raw,'new_password':PASSWORD})['status']==422,'changing email back never revives an old link')
    raw=token(request());assert staff.call('auth.php?action=change-password',{'current_password':NEW_PASSWORD,'new_password':NEW_PASSWORD})[0]==200
    check(not sql('SELECT * FROM password_reset_tokens WHERE user_id='+ids['active']) and service('reset',body={'token':raw,'new_password':PASSWORD})['status']==422,'ordinary password changes invalidate recovery links')
    raw=token(request());account=sql('SELECT version FROM users WHERE id='+ids['active'])[0]
    assert admin.call('accounts.php?action=reset-password',{'user_id':ids['active'],'version':int(account['version']),'new_password':NEW_PASSWORD})[0]==200
    check(service('reset',body={'token':raw,'new_password':PASSWORD})['status']==422,'administrator resets invalidate recovery links')
    before=sql('SELECT password_hash,auth_version FROM users WHERE id='+ids['active'])
    failed=request(delivery='fail')
    check(failed['result']['requested'] and not sql('SELECT * FROM password_reset_tokens WHERE user_id='+ids['active']) and before==sql('SELECT password_hash,auth_version FROM users WHERE id='+ids['active']),'delivery failure removes unusable token without changing password')
    replaced=request(delivery='replace_then_fail')
    check(sql('SELECT token_hash FROM password_reset_tokens WHERE user_id='+ids['active'])[0]['token_hash']==hashlib.sha256(token(replaced).encode()).hexdigest(),'failed older delivery preserves the newer token')
    sql('DELETE FROM auth_rate_limits')
    for _ in range(3):assert service('request',email='unknown@hospital.example')['status']==200
    check(service('request',email='unknown@hospital.example')['status']==429,'per-email request limit applies even to unknown addresses')
    sql('DELETE FROM auth_rate_limits')
    for n in range(20):assert c.call('auth.php?action=reset-request',{'email':f'absent{n}@hospital.example'})[0]==202
    check(c.call('auth.php?action=reset-request',{'email':'another@hospital.example'})[0]==429,'per-IP request limit enforced')
    sql('DELETE FROM auth_rate_limits')
    for _ in range(20):assert service('reset',body={'token':'bad','new_password':PASSWORD})['status']==422
    check(service('reset',body={'token':'bad','new_password':PASSWORD})['status']==429,'per-IP redemption limit enforced')
    sql('DELETE FROM auth_rate_limits')
    mounted=dict(env,HOSPITAL_BASE_PATH='/hospital',HOSPITAL_APP_URL='http://127.0.0.1:8882/hospital/')
    result=request(override=mounted,locale='en')
    check('http://127.0.0.1:8882/hospital/src/views/register-restore/restore-password.php?lang=en#token=' in result['mails'][0]['textContent'],'mounted recovery links use configured root and language')
    bad=dict(env,HOSPITAL_APP_URL='https://attacker.example/wrong/')
    check(service('request',override=bad,email='unknown@hospital.example')['status']==503,'misconfigured application path rejected before lookup')
    sql('DELETE FROM auth_rate_limits');mail_started.clear()
    started=time.monotonic();accepted=c.call('auth.php?action=reset-request',{'email':'active@hospital.example'});known_seconds=time.monotonic()-started
    assert mail_started.wait(timeout=3),'Detached mail worker did not reach the local mock provider.'
    started=time.monotonic();unknown=c.call('auth.php?action=reset-request',{'email':'absent-timing@hospital.example'});unknown_seconds=time.monotonic()-started
    check(accepted[0]==unknown[0]==202 and accepted[1]['data']==unknown[1]['data'] and known_seconds<1.5 and unknown_seconds<1.5 and abs(known_seconds-unknown_seconds)<1,'slow provider cannot reveal account existence through the 202 response or occupy the web worker')
    queued=list((RUNTIME/'storage/mail').glob('*.bin'))
    check(bool(queued) and all(path.stat().st_mode&0o777==0o600 for path in queued) and (RUNTIME/'storage/mail').stat().st_mode&0o777==0o700,'pending mail credentials stay in owner-only private storage')
    deadline=time.monotonic()+6
    while time.monotonic()<deadline and list((RUNTIME/'storage/mail').glob('*.bin')):time.sleep(.05)
    check(not list((RUNTIME/'storage/mail').glob('*.bin')) and len(mail_requests)==1 and mail_requests[0]['to']==[{'email':'active@hospital.example'}],'detached worker delivers only the matching account and removes its temporary job')
    global mail_status
    mail_status=500;mail_started.clear();sql('DELETE FROM auth_rate_limits')
    assert c.call('auth.php?action=reset-request',{'email':'active@hospital.example'})[0]==202
    assert mail_started.wait(timeout=3)
    newer=request();newer_hash=hashlib.sha256(token(newer).encode()).hexdigest()
    deadline=time.monotonic()+6
    while time.monotonic()<deadline and list((RUNTIME/'storage/mail').glob('*.bin')):time.sleep(.05)
    check(sql('SELECT token_hash FROM password_reset_tokens WHERE user_id='+ids['active'])[0]['token_hash']==newer_hash and not list((RUNTIME/'storage/mail').glob('*.bin')),'a failed older background delivery cannot revoke a newer reset credential')
    mail_status=201
    (RUNTIME/'browser.json').write_text(json.dumps({'env':{k:v for k,v in env.items() if k.startswith('HOSPITAL_')},'password':NEW_PASSWORD}))
    (RUNTIME/'browser.json').chmod(0o600)
    if '--browser' in sys.argv:
        subprocess.run([NODE,'tests/password_recovery_browser.cjs'],env=env,cwd=ROOT,check=True)
    check(sql("SELECT COUNT(*) n FROM audit_events WHERE action='account.password_reset'")[0]['n']>=3,'successful resets have ID-only audit events')
    print('TOTAL',checks,'focused recovery SQL/API checks',flush=True)

if __name__=='__main__':
    RUNTIME.mkdir(parents=True,exist_ok=True)
    try:run()
    finally:
        for p,log in servers:p.terminate();p.wait(timeout=10);log.close()
        deadline=time.monotonic()+12
        while time.monotonic()<deadline and list((RUNTIME/'storage/mail').glob('*.bin')):time.sleep(.05)
        if mail_peer is not None:mail_peer.shutdown();mail_peer.server_close()
        php('require "backend/php/autoload.php";$c=Hospital\\Config::load();$name=$c["db"]["name"];$user=$c["db"]["user"];if(!preg_match("/^hospital_recovery_[a-f0-9]{8}_test$/",$name))exit(2);$c["db"]["user"]=getenv("HOSPITAL_SETUP_DB_USER")?:"root";$c["db"]["password"]=getenv("HOSPITAL_SETUP_DB_PASSWORD")?:"";$db=Hospital\\Database::connect($c,false);$db->exec("DROP DATABASE IF EXISTS `".$name."`");foreach(["localhost","127.0.0.1"] as $h)$db->exec("DROP USER IF EXISTS ".$db->quote($user)."@".$db->quote($h));echo "true";')
        shutil.rmtree(RUNTIME/'storage',ignore_errors=True)
        (RUNTIME/'browser.json').unlink(missing_ok=True)
