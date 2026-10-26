"""Focused destination templates and optional trace endpoints; disposable DB only."""
import json
import os
import subprocess
from datetime import datetime, timedelta, timezone
from pathlib import Path
from auth_integration import simulation_control, Client, PASSWORD, ADMIN_PASSWORD, SUFFIX, PHP, ROOT, check
from catalog_write_checks import verify_catalog_writes


def run():
    dispatcher=Client();dispatcher.csrf();username='destinations_'+SUFFIX
    code,payload,_=dispatcher.call('/api/v1/auth.php?action=register','POST',dict(username=username,email=username+'@hospital.example',national_id='98004234',password=PASSWORD));assert code==201,payload
    subprocess.run([PHP,'backend/php/cli/users.php','approve',username,'dispatcher'],cwd=ROOT,capture_output=True,check=True)
    assert dispatcher.login(username,PASSWORD)[0]==200
    admin=Client();assert admin.login('system_admin',ADMIN_PASSWORD)[0]==200
    call=lambda action,data=None:dispatcher.call('/api/v1/destinations.php?action=' + action.replace('.php', '').replace('?', '&'),'POST' if data is not None else 'GET',data)
    catalog=lambda:dispatcher.call('/api/v1/transfers.php?action=catalogs')[1]['data']
    get=lambda id:dispatcher.call('/api/v1/transfers.php?action=get&id='+id)[1]['data']
    def state(trace,status):
        code,payload,_=dispatcher.call('/api/v1/transfers.php?action=status','POST',dict(id=trace['id'],version=trace['version'],status=status));assert code==200,payload
        return payload['data']
    check(Client().call('/api/v1/destinations.php?action=list')[0]==401 and admin.call('/api/v1/destinations.php?action=list')[0]==200,'destination management admits dispatchers/admins and requires authentication')
    check({c['name'] for c in call('list.php')[1]['data']} == {'Hospital de Clinicas de Montevideo', 'Facultad de Medicina'},'fresh center catalog contains only the two configured institutional defaults')
    record=dict(name='Centro Ficticio',address='Calle Prueba 100',locality='Montevideo',facility_type='health_center',phone='',notes='Entrada lateral.\nAvisar al llegar.',availability='active')
    check(dispatcher.call('/api/v1/destinations.php?action=create','POST',record,csrf=False)[0]==403,'destination writes require CSRF')
    code,payload,_=call('create.php',record);assert code==201,payload
    center=payload['data'];cid=center['id']
    check(center['notes']==record['notes'] and center['latitude'] is None and center['longitude'] is None,'all form fields persist without inventing coordinates')
    check(any(c['id']==cid for c in catalog()['facilities']) and any(c['id']==cid for c in dispatcher.call('/api/v1/catalogs.php?action=facilities')[1]['data']),'new center appears in trace suggestions and the shared settings catalog')
    check(call('create.php',record)[0]==409 and call('create.php',{**record,'address':'Calle Prueba 200'})[0]==201,'exact duplicates conflict but different branches can share a name')
    edit={**record,'id':cid,'version':center['version'],'phone':'2487 1515'}
    code,payload,_=call('update.php',edit);assert code==200,payload
    center=payload['data']
    check(call('get.php?id='+cid)[1]['data']['phone']=='2487 1515' and call('update.php',edit)[0]==409,'metadata updates persist and stale revisions conflict')
    subprocess.run([PHP,'backend/php/cli/transfers.php','seed-demo'],cwd=ROOT,capture_output=True,check=True)
    options=catalog();drivers=[c['id'] for c in options['crew'] if c['role_code']=='driver']
    def fields(index=0,**locations):
        return dict(ambulance_id=options['ambulances'][index]['id'],driver_id=drivers[index],companion_id=None,patient_id=None,
            scheduled_at=(datetime.now(timezone(timedelta(hours=-3)))+timedelta(hours=2)).replace(microsecond=0).isoformat(),
            cargo_type='equipment',priority='normal',reason='insumos',notes='',special_equipment=False,with_family=False,interdepartmental=False,critical_patient=False,
            create_token=dispatcher.call('/api/v1/transfers.php?action=create-token')[1]['data']['create_token'],**locations)
    mixed_fields=fields(origin_facility_id=cid,destination_facility_id=None,destination_name='Laboratorio Particular',destination_address='Calle Libre 50')
    code,payload,_=dispatcher.call('/api/v1/transfers.php?action=create','POST',mixed_fields);assert code==201,payload
    mixed=payload['data']
    check(mixed['origin']['id']==cid and mixed['destination']['id'] is None and mixed['destination']['address']=='Calle Libre 50','trace can combine a saved template and a custom location')
    check(mixed['geometry']==[] and mixed['route_template_id'] is None and mixed['position'] is None and mixed['duration_seconds'] is None and mixed['distance_meters'] is None and mixed['eta'] is None,'unmapped traces have no fabricated route, position or ETA')
    code,payload,_=call('archive.php',dict(id=cid,version=center['version']));assert code==200,payload
    center=payload['data']
    check(all(c['id']!=cid for c in catalog()['facilities']) and get(mixed['id'])['origin']['name']==record['name'],'archiving a template preserves pending trace snapshots')
    attempt={**mixed_fields,'create_token':dispatcher.call('/api/v1/transfers.php?action=create-token')[1]['data']['create_token']}
    check(dispatcher.call('/api/v1/transfers.php?action=create','POST',attempt)[1]['error']['code']=='FACILITY_UNAVAILABLE','archived template IDs cannot be newly selected')
    mixed=state(mixed,'in_progress');mixed=state(mixed,'arrived');mixed=state(mixed,'completed')
    check(mixed['status']=='completed' and mixed['position'] is None,'unmapped trace lifecycle works after template removal')
    code,payload,_=call('restore.php',dict(id=cid,version=center['version']));assert code==200,payload
    center=payload['data']
    code,payload,_=call('update.php',{**edit,'version':center['version'],'availability':'inactive'});assert code==200,payload
    center=payload['data']
    check(all(c['id']!=cid for c in catalog()['facilities']) and all(c['id']!=cid for c in dispatcher.call('/api/v1/catalogs.php?action=facilities')[1]['data']),'inactive centers are excluded from both selection catalogs')
    code,payload,_=call('update.php',{**edit,'version':center['version']});assert code==200,payload
    custom_fields=fields(1,origin_facility_id=None,origin_name='Dirección de origen libre',origin_address='Calle A 10',destination_facility_id=None,destination_name='Dirección de destino libre',destination_address='Calle B 20')
    code,payload,_=dispatcher.call('/api/v1/transfers.php?action=create','POST',custom_fields);assert code==201,payload
    custom=payload['data'];update={k:v for k,v in custom_fields.items() if k!='create_token'}
    code,payload,_=dispatcher.call('/api/v1/transfers.php?action=update','POST',{**update,'id':custom['id'],'version':custom['version'],'destination_name':'Destino actualizado'});assert code==200,payload
    custom=state(payload['data'],'in_progress')
    check(custom['origin']['id'] is None and custom['destination']['name']=='Destino actualizado' and custom['geometry']==[],'fully custom traces create/edit/start without center records')
    route=options['routes'][0]
    mapped_fields=fields(origin_facility_id=route['origin_facility_id'],destination_facility_id=route['destination_facility_id'])
    code,payload,_=dispatcher.call('/api/v1/transfers.php?action=create','POST',mapped_fields);assert code==201,payload
    mapped=payload['data'];geometry=mapped['geometry'];old_name=mapped['origin']['name']
    legacy=call('get.php?id='+mapped['origin']['id'])[1]['data']
    code,payload,_=call('update.php',dict(id=legacy['id'],version=legacy['version'],name='Centro renombrado',address='Dirección nueva 999',locality='Montevideo',facility_type='hospital',phone='',notes='',availability='active'));assert code==200,payload
    changed=payload['data'];assert changed['latitude'] is None
    assert call('archive.php',dict(id=changed['id'],version=changed['version']))[0]==200
    mapped=state(mapped,'in_progress')
    check(mapped['geometry']==geometry and mapped['origin']['name']==old_name and mapped['position'] is not None,'existing mapped traces retain geometry/history when a center changes or is archived')
    simulation=simulation_control()[1]['data']
    code,payload,_=simulation_control(dict(command='start',version=simulation['version']));assert code==200,payload
    simulation=payload['data']
    program=r"require 'backend/php/autoload.php';$c=Hospital\Config::load();echo tracking_tick(Hospital\Database::connect($c), $c);"
    tick=subprocess.run([PHP,'-r',program],cwd=ROOT,capture_output=True,text=True,check=True)
    check(tick.stdout=='1' and get(custom['id'])['position'] is None,'simulation continues mapped routes and skips unmapped traces safely')
    code,payload,_=simulation_control(dict(command='reset',version=simulation['version']));assert code==200,payload
    check(get(mapped['id'])['position']['progress']==0 and get(custom['id'])['position'] is None,'simulation reset preserves the distinction between mapped and unmapped traces')
    verify_catalog_writes('destinations', dispatcher, admin, {**record, 'name': 'Centro de prueba de atomicidad', 'address': 'Calle de prueba 100'})
    proof=Path(os.environ['HOSPITAL_DESTINATION_TEST_DIR'])/'browser.json'
    proof.write_text(json.dumps(dict(username=username,password=PASSWORD,id=cid,trace_id=custom['id'])));proof.chmod(0o600)


if __name__=='__main__':
    run()
