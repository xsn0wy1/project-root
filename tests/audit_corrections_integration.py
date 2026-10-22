"""Focused audit fixes; only disposable *_test databases and private fixture storage."""
import hashlib,json,os,secrets,shutil,socketserver,subprocess,threading,time
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime,timedelta,timezone
from pathlib import Path
from auth_integration import Client,PASSWORD,ADMIN_PASSWORD,SUFFIX,PHP,ROOT,check,sql
from documents_integration import upload,pdf_bytes,docx_bytes
from routes_integration import cache_path

PRIVATE=Path(os.environ['HOSPITAL_AUDIT_TEST_DIR'])


def data(client,path):
    code,payload,_=client.call('/api/v1/'+path);assert code==200,payload
    return payload['data']


def post(client,path,body,expected=200):
    code,payload,_=client.call('/api/v1/'+path,'POST',body);assert code==expected,payload
    return payload.get('data',payload.get('error'))


def register(name,ci):
    client=Client();client.csrf();post(client,'auth.php?action=register',dict(username=name,email=name+'@hospital.example',national_id=ci,password=PASSWORD),201)
    return client


def run():
    system=Client();assert system.login('system_admin',ADMIN_PASSWORD)[0]==200
    directory=lambda client=system:data(client,'accounts.php?action=directory')
    record=lambda username:next(u for u in directory() if u['username']==username)
    def manage(row,action,roles=None,client=system):
        return post(client,'accounts.php?action=manage',dict(user_id=row['id'],version=row['version'],action=action,roles=roles or []))
    root=record('system_admin')
    check(system.call('/api/v1/accounts.php?action=manage','POST',dict(user_id=root['id'],version=root['version'],action='roles',roles=['staff']))[1]['error']['code']=='LAST_ADMIN','last administrator cannot lose the admin role')
    name='audit_admin_'+SUFFIX;register(name,'98006234');manage(record(name),'approve',['admin','staff','dispatcher','records_manager'])
    admin=Client();assert admin.login(name,PASSWORD)[0]==200
    check(data(admin,'auth.php?action=me')['user']['roles']==['admin','dispatcher','records_manager','staff'],'pending account approval can grant the required combination of roles')
    person='audit_staff_'+SUFFIX;target=register(person,'98006235');pending=record(person)
    check(target.call('/api/v1/accounts.php?action=directory')[0]==401,'pending accounts cannot access account administration')
    manage(pending,'approve',['staff','dispatcher']);assert target.login(person,PASSWORD)[0]==200
    other=Client();assert other.login(person,PASSWORD)[0]==200
    check(target.call('/api/v1/accounts.php?action=directory')[0]==403 and target.call('/src/views/management/accounts.php')[0]==303,'ordinary staff cannot access the account API or page')
    selected=record(person);manage(selected,'roles',['staff','dispatcher','records_manager'])
    check(target.call('/api/v1/auth.php?action=me')[0]==401 and other.call('/api/v1/auth.php?action=me')[0]==401,'role changes invalidate all existing sessions')
    check(system.call('/api/v1/accounts.php?action=manage','POST',dict(user_id=selected['id'],version=selected['version'],action='disable',roles=[]))[0]==409,'stale account edits are rejected')
    assert target.login(person,PASSWORD)[0]==200
    manage(record(person),'disable');check(target.call('/api/v1/auth.php?action=me')[0]==401,'disabling an account closes its sessions')
    manage(record(person),'reactivate');assert target.login(person,PASSWORD)[0]==200
    check(data(target,'auth.php?action=me')['user']['roles']==['dispatcher','records_manager','staff'],'reactivation preserves explicit roles without creating another staff identity')
    changed=secrets.token_urlsafe(20);post(system,'accounts.php?action=reset-password',dict(user_id=record(person)['id'],version=record(person)['version'],new_password=changed))
    check(target.call('/api/v1/auth.php?action=me')[0]==401 and target.login(person,PASSWORD)[0]==401 and target.login(person,changed)[0]==200,'administrator password reset revokes sessions and replaces the old password')
    check(target.call('/api/v1/auth.php?action=change-password','POST',dict(current_password='wrong',new_password=PASSWORD))[0]==422,'incorrect current passwords fail without changing credentials')
    before_hash=sql('SELECT password_hash,auth_version FROM users WHERE id='+record(person)['id'])
    for _ in range(4):assert target.call('/api/v1/auth.php?action=change-password','POST',dict(current_password='wrong',new_password=PASSWORD))[0]==422
    limited=target.call('/api/v1/auth.php?action=change-password','POST',dict(current_password='wrong',new_password=PASSWORD))
    check(limited[0]==429 and limited[2].get('Retry-After') and before_hash==sql('SELECT password_hash,auth_version FROM users WHERE id='+record(person)['id']),'current-password verification is bounded after five failures without changing credentials')
    sql('DELETE FROM auth_rate_limits')
    again=secrets.token_urlsafe(20);post(target,'auth.php?action=change-password',dict(current_password=changed,new_password=again))
    check(target.call('/api/v1/auth.php?action=me')[0]==401 and target.login(person,again)[0]==200,'own password change logs out old sessions and accepts the new password')
    before=data(target,'auth.php?action=me')
    doctor=dict(title='Dra.',full_name='Doctora de prueba',national_id='98006235',license='MSP-AUDIT1',specialty='Cardiología',department='Consultorios Externos',office='Consultorio 10',phone='099 111 222',email='doctor@hospital.example',availability='available',shift='night',days='rotating',start_time='22:00',end_time='06:00',notes='Ficción.')
    d=post(target,'doctors.php?action=create',doctor,201)
    check(target.call('/api/v1/settings.php?action=profile-update','POST',dict(version=before['settings']['version'],full_name='Nombre viejo'))[0]==409,'settings detect staff changes made in doctor management')
    crew=dict(full_name='Tripulante de prueba',national_id='98006235',phone='099 111 222',responsibility='copilot',driving_license='',availability='active')
    p=post(target,'personnel.php?action=create',crew,201)
    check(target.call('/api/v1/doctors.php?action=update','POST',{**doctor,'id':d['id'],'version':d['version'],'office':'Otro consultorio'})[0]==409,'doctor revisions detect newer shared-personnel changes')
    current=data(target,'auth.php?action=me');post(target,'settings.php?action=profile-update',dict(version=current['settings']['version'],full_name='Nombre canónico actualizado'))
    check(target.call('/api/v1/personnel.php?action=update','POST',{**crew,'id':p['id'],'version':p['version']})[0]==409 and data(target,'auth.php?action=me')['user']['full_name']=='Nombre canónico actualizado','stale personnel edits cannot overwrite current settings identity')
    catalog=data(admin,'transfers.php?action=catalogs');vehicle=catalog['ambulances'][1]['id'];driver=next(c['id'] for c in catalog['crew'] if c['role_code']=='driver')
    when=(datetime.now(timezone(timedelta(hours=-3)))+timedelta(days=5)).replace(microsecond=0)
    def trace_fields(start=when):
        return dict(route_template_id=None,ambulance_id=vehicle,driver_id=driver,companion_id=None,patient_id=None,origin_facility_id=None,destination_facility_id=None,origin_name='Origen de prueba '+SUFFIX,origin_address='',destination_name='Destino de prueba '+SUFFIX,destination_address='',scheduled_at=start.isoformat(),cargo_type='equipment',priority='normal',reason='insumos',notes='',special_equipment=False,with_family=False,interdepartmental=False,critical_patient=False,create_token=data(admin,'transfers.php?action=create-token')['create_token'])
    invalid=trace_fields();invalid.update(route_template_id='',origin_name='',destination_name='')
    check(admin.call('/api/v1/transfers.php?action=create','POST',invalid)[0]==422,'empty preset IDs cannot bypass manual-location validation')
    # A local TCP peer stalls TLS. The public Internet is never needed here.
    entered=threading.Event()
    class Stall(socketserver.BaseRequestHandler):
        def handle(self):entered.set();time.sleep(4)
    peer=socketserver.ThreadingTCPServer(('127.0.0.1',0),Stall);peer.daemon_threads=True
    threading.Thread(target=peer.serve_forever,daemon=True).start()
    log=(PRIVATE/'slow-server.log').open('w');env={**os.environ,'HOSPITAL_ROUTE_MAPPING_ENABLED':'true','HOSPITAL_GEOCODER_URL':'https://127.0.0.1:'+str(peer.server_address[1])+'/api/'}
    server=subprocess.Popen([PHP,'-S','127.0.0.1:8879','-t','public','backend/php/dev-router.php'],cwd=ROOT,env=env,stdout=log,stderr=log,start_new_session=True)
    try:
        time.sleep(.3);slow=Client('http://127.0.0.1:8879');assert slow.login(name,PASSWORD)[0]==200
        fields=trace_fields()
        with ThreadPoolExecutor(max_workers=1) as executor:
            future=executor.submit(post,slow,'transfers.php?action=create',fields,201)
            assert entered.wait(5),'Mapping did not reach the local peer.'
            program=r"require 'backend/php/autoload.php';$c=Hospital\Config::load();transfer_serialized(Hospital\Database::connect($c), $c, function(){echo 'acquired';});"
            started=time.monotonic();probe=subprocess.run([PHP,'-r',program],cwd=ROOT,capture_output=True,text=True,check=True)
            check(probe.stdout=='acquired' and time.monotonic()-started<1,'slow address mapping does not hold the transfer-domain lock')
            trace=future.result()
        check(trace['geometry']==[] and trace['duration_seconds'] is None,'failed lookup saves an unmapped trace without inventing an ETA')
        collision=trace_fields(when+timedelta(minutes=30));check(slow.call('/api/v1/transfers.php?action=create','POST',collision)[1]['error']['code']=='ASSIGNMENT_CONFLICT','unmapped bookings use the conservative hour plus buffer')
    finally:
        server.terminate();server.wait(timeout=5);log.close();peer.shutdown();peer.server_close()
    cache_path([dict(name=fields['origin_name'],address=''),dict(name=fields['destination_name'],address='')])
    update={k:v for k,v in fields.items() if k!='create_token'}
    mapped=post(admin,'transfers.php?action=update',{**update,'id':trace['id'],'version':trace['version'],'refresh_route':True})
    check(len(mapped['geometry'])==2 and mapped['duration_seconds']==900,'explicit retry maps a scheduled trace and recalculates its booking')
    lock_program=r'''require 'backend/php/autoload.php';$c=Hospital\Config::load();transfer_serialized(Hospital\Database::connect($c), $c, function(){echo "locked\n";flush();sleep(7);});'''
    lock=subprocess.Popen([PHP,'-r',lock_program],cwd=ROOT,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
    assert lock.stdout.readline().strip()=='locked'
    worker=subprocess.run([PHP,'backend/php/cli/simulate-transfers.php','worker','7'],cwd=ROOT,capture_output=True,text=True,timeout=18);lock.wait(timeout=2)
    check(worker.returncode==0 and 'tick deferred' in worker.stderr,'the simulation worker survives lock contention and resumes after release')
    category=next(c['id'] for c in data(admin,'catalogs.php?action=document-categories') if not c['requires_patient'])
    def document_fields(title):
        return dict(title=title,description='',patient_id='',category_id=category,document_date='2026-10-14',create_token=data(admin,'documents.php?action=create-token')['create_token'])
    relationship='<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="link" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.com/" TargetMode="External"/></Relationships>'
    code,payload=upload(admin,document_fields('Enlace permitido'),docx_bytes({'word/_rels/document.xml.rels':relationship}),'enlace.docx');assert code==201,payload
    check(True,'ordinary DOCX hyperlinks upload successfully without server-side fetching')
    bad=relationship.replace('/hyperlink','/image');code,payload=upload(admin,document_fields('Imagen externa'),docx_bytes({'word/_rels/document.xml.rels':bad}),'externo.docx')
    check(code==415,'external image/object relationships remain rejected')
    for index in range(25):
        code,payload=upload(admin,document_fields(f'Auditoría documento {index+1:02d}'),pdf_bytes(pages=1));assert code==201,payload
        if index==0:first=payload['data']
    check(data(admin,'documents.php?action=get&id='+first['id'])['id']==first['id'],'uploaded PDF metadata uses the same stored-file/version records')
    row=sql('SELECT f.id,f.storage_key,f.sha256 FROM stored_files f JOIN document_versions v ON v.stored_file_id=f.id WHERE v.document_id='+first['id'])[0]
    storage=Path(os.environ['HOSPITAL_STORAGE_PATH']);backup=PRIVATE/'backup';(backup/'documents').mkdir(parents=True,exist_ok=True)
    binary=storage/'documents'/(row['storage_key']+'.bin');shutil.copy2(binary,backup/'documents'/binary.name);binary.unlink()
    before_check=subprocess.run([PHP,'backend/php/cli/preflight.php'],cwd=ROOT,capture_output=True,text=True)
    check(before_check.returncode==1 and str(row['id']) in json.loads(before_check.stdout)['storage']['missing'],'preflight detects missing binaries even when SQL references are valid')
    recovery=subprocess.run([PHP,'backend/php/cli/preflight.php','--recover-from='+str(backup)],cwd=ROOT,capture_output=True,text=True,check=True)
    check(str(row['id']) in json.loads(recovery.stdout)['recovery']['restored'] and hashlib.sha256(binary.read_bytes()).hexdigest()==row['sha256'],'private recovery restores only hash-matching backup files without changing SQL')
    pending_name='audit_pending_'+SUFFIX;register(pending_name,'98006236')
    proof=PRIVATE/'browser.json';proof.write_text(json.dumps(dict(username=name,password=PASSWORD,pending=pending_name,trace_id=mapped['id'],staff=person,staff_password=again)));proof.chmod(0o600)


if __name__=='__main__':run()
