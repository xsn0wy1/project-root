"""Focused route catalog, optional presets, snapshots and cached mapping checks."""
import hashlib,json,os,subprocess
from datetime import datetime,timedelta,timezone
from pathlib import Path
from urllib.parse import urlencode
from auth_integration import simulation_control, Client,PASSWORD,ADMIN_PASSWORD,SUFFIX,PHP,ROOT,check,sql


def cache(url,data):
    payload=json.dumps(data).replace("'","''")
    sql("INSERT INTO route_map_cache (cache_key,payload,expires_at) VALUES ('"+hashlib.sha256(url.encode()).hexdigest()+"','"+payload+"',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY)) ON DUPLICATE KEY UPDATE payload=VALUES(payload),expires_at=VALUES(expires_at)")


def router_url(points):
    coordinates=';'.join(format(p['longitude'],'.12g')+','+format(p['latitude'],'.12g') for p in points)
    return 'https://127.0.0.1:9/route/v1/driving/'+coordinates+'?overview=full&geometries=geojson&steps=false'


def cache_path(places):
    # Fictional deterministic provider responses; never calls public APIs in the suite.
    points=[]
    for index,place in enumerate(places):
        point=dict(latitude=round(-34.89-index*.005,7),longitude=round(-56.16-index*.01,7));points.append(point)
        query=place['address'] or place['name']
        cache('https://127.0.0.1:9/api/?'+urlencode(dict(q=query,countrycode='UY',limit=1)),dict(features=[dict(geometry=dict(coordinates=[point['longitude'],point['latitude']]))]))
    cache(router_url(points),dict(code='Ok',routes=[dict(distance=5600,duration=900,geometry=dict(coordinates=[[p['longitude'],p['latitude']] for p in points]))]))
    return points


def run():
    dispatcher=Client();dispatcher.csrf();username='routes_'+SUFFIX
    status,payload,_=dispatcher.call('/api/v1/auth.php?action=register','POST',dict(username=username,email=username+'@hospital.example',national_id='98005234',password=PASSWORD));assert status==201,payload
    subprocess.run([PHP,'backend/php/cli/users.php','approve',username,'dispatcher'],cwd=ROOT,capture_output=True,check=True)
    assert dispatcher.login(username,PASSWORD)[0]==200
    admin=Client();assert admin.login('system_admin',ADMIN_PASSWORD)[0]==200
    call=lambda action,data=None:dispatcher.call('/api/v1/routes.php?action=' + action.replace('.php', '').replace('?', '&'),'POST' if data is not None else 'GET',data)
    catalog=lambda:dispatcher.call('/api/v1/transfers.php?action=catalogs')[1]['data']
    check(Client().call('/api/v1/routes.php?action=list')[0]==401 and admin.call('/api/v1/routes.php?action=list')[0]==403,'routes require dispatcher access')
    check(admin.call('/src/views/management/routes.php')[0]==303,'route page is protected by the same role')
    check(call('list.php')[1]['data']==[],'route catalog starts empty without browser samples')
    record=dict(name='Ruta de prueba',category='consultation',direction='round_trip',availability='active',origin_name='Clínica ficticia',origin_address='Av. Italia 2870, Montevideo',destination_name='Centro ficticio',destination_address='25 de Mayo 174, Montevideo',stops=[dict(name='Parada ficticia',address='Bv. Artigas 1850, Montevideo')],distance_meters=5600,duration_seconds=900,notes='Entrada lateral.\nAvisar antes de salir.')
    places=[dict(name=record['origin_name'],address=record['origin_address']),*record['stops'],dict(name=record['destination_name'],address=record['destination_address'])]
    points=cache_path(places)
    check(dispatcher.call('/api/v1/routes.php?action=create','POST',record,csrf=False)[0]==403,'route mutations require CSRF')
    status,payload,_=call('create.php',record);assert status==201,payload
    route=payload['data'];rid=route['id']
    check(route['geometry']==points and route['stops']==record['stops'] and route['direction']=='round_trip' and route['notes']==record['notes'],'all editor fields and road geometry persist in canonical SQL')
    check(call('create.php',record)[0]==409 and call('create.php',{**record,'name':'Invalid','stops':record['stops']*7})[0]==422,'duplicates and too many stops are rejected')
    check(call('create.php',{**record,'name':'Invalid','duration_seconds':0})[0]==422 and call('create.php',{**record,'name':'Invalid','stops':[dict(name='x',address='y',role='admin')]})[0]==422,'estimate bounds and unexpected stop fields are validated')
    subprocess.run([PHP,'backend/php/cli/transfers.php','seed-demo'],cwd=ROOT,capture_output=True,check=True)
    options=catalog();drivers=[c['id'] for c in options['crew'] if c['role_code']=='driver']
    check(any(r['id']==rid and r['stops']==record['stops'] for r in options['routes']),'presets appear in the trace catalog with their itinerary and revision')
    def fields(index=0,**locations):
        return dict(ambulance_id=options['ambulances'][index]['id'],driver_id=drivers[index],companion_id=None,patient_id=None,scheduled_at=(datetime.now(timezone(timedelta(hours=-3)))+timedelta(hours=2)).replace(microsecond=0).isoformat(),cargo_type='equipment',priority='normal',reason='insumos',notes='',special_equipment=False,with_family=False,interdepartmental=False,critical_patient=False,create_token=dispatcher.call('/api/v1/transfers.php?action=create-token')[1]['data']['create_token'],**locations)
    preset=fields(route_template_id=rid,route_version=route['version'])
    status,payload,_=dispatcher.call('/api/v1/transfers.php?action=create','POST',preset);assert status==201,payload
    trace=payload['data']
    check(trace['route_template_id']==rid and trace['origin']['name']==record['origin_name'] and trace['geometry']==points and trace['route_snapshot']['stops']==record['stops'] and trace['route_snapshot']['direction']=='round_trip','preset creates an outbound trace with immutable itinerary/geometry/estimate snapshots')
    edit={**record,'id':rid,'version':route['version'],'notes':'Indicaciones actualizadas'}
    status,payload,_=call('update.php',edit);assert status==200,payload
    route=payload['data']
    check(call('update.php',edit)[0]==409 and route['geometry']==points and route['notes']==edit['notes'],'edits persist, preserve unchanged paths, and reject stale versions')
    check(dispatcher.call('/api/v1/transfers.php?action=create','POST',{**preset,'create_token':dispatcher.call('/api/v1/transfers.php?action=create-token')[1]['data']['create_token']})[0]==409,'a changed preset cannot silently replace the revision selected in an older form')
    status,payload,_=call('archive.php',dict(id=rid,version=route['version']));assert status==200,payload
    route=payload['data']
    check(all(r['id']!=rid for r in catalog()['routes']) and dispatcher.call('/api/v1/transfers.php?action=create','POST',{**preset,'route_version':route['version'],'create_token':dispatcher.call('/api/v1/transfers.php?action=create-token')[1]['data']['create_token']})[1]['error']['code']=='ROUTE_UNAVAILABLE','archived presets leave selectors and reject new assignments')
    body={k:v for k,v in preset.items() if k not in ['create_token','route_version']}
    status,payload,_=dispatcher.call('/api/v1/transfers.php?action=update','POST',{**body,'id':trace['id'],'version':trace['version'],'notes':'Trace notes'});assert status==200,payload
    trace=payload['data']
    status,payload,_=dispatcher.call('/api/v1/transfers.php?action=status','POST',dict(id=trace['id'],version=trace['version'],status='in_progress'));assert status==200,payload
    trace=payload['data']
    check(trace['geometry']==points and trace['route_snapshot']['stops']==record['stops'] and trace['position'] is not None,'existing traces can edit/start after preset archival without losing their path or stops')
    status,payload,_=call('restore.php',dict(id=rid,version=route['version']));assert status==200,payload
    route=payload['data']
    status,payload,_=call('update.php',{**record,'id':rid,'version':route['version'],'availability':'inactive'});assert status==200,payload
    route=payload['data']
    check(all(r['id']!=rid for r in catalog()['routes']),'inactive presets are excluded from new trace selection')
    status,payload,_=call('update.php',{**record,'id':rid,'version':route['version']});assert status==200,payload
    route=payload['data']
    manual_places=[places[0],places[-1]];manual_points=cache_path(manual_places)
    manual=fields(1,route_template_id=None,origin_facility_id=None,origin_name=places[0]['address'],origin_address='',destination_facility_id=None,destination_name=places[-1]['address'],destination_address='')
    status,payload,_=dispatcher.call('/api/v1/transfers.php?action=create','POST',manual);assert status==201,payload
    custom=payload['data']
    check(custom['route_template_id'] is None and custom['geometry']==manual_points and custom['duration_seconds']==900 and custom['position'] is not None,'manual entry maps directly without creating a preset or a center')
    unknown='Dirección ficticia sin coincidencias 123'
    cache('https://127.0.0.1:9/api/?'+urlencode(dict(q=unknown,countrycode='UY',limit=1)),dict(features=[]))
    fallback=fields(2,route_template_id=None,origin_facility_id=None,origin_name=unknown,origin_address='',destination_facility_id=None,destination_name='Otro destino ficticio',destination_address='')
    status,payload,_=dispatcher.call('/api/v1/transfers.php?action=create','POST',fallback);assert status==201,payload
    check(payload['data']['geometry']==[] and payload['data']['position'] is None and payload['data']['duration_seconds'] is None,'unresolved lookups still save with no invented map, movement or ETA')
    status,payload,_=call('create.php',{**record,'name':'Ruta con mapa pendiente','origin_address':unknown});assert status==201,payload
    check(payload['data']['geometry']==[] and payload['data']['duration_seconds']==record['duration_seconds'],'unresolved preset mapping preserves the saved itinerary and staff-entered estimates')
    changed_stops=[*record['stops'],dict(name='Segunda parada ficticia',address='Avenida de prueba 100')]
    changed_places=[places[0],*changed_stops,places[-1]];changed_points=cache_path(changed_places)
    status,payload,_=call('update.php',{**record,'id':rid,'version':route['version'],'stops':changed_stops});assert status==200,payload
    route=payload['data']
    old=dispatcher.call('/api/v1/transfers.php?action=get&id='+trace['id'])[1]['data']
    check(route['stops']==changed_stops and route['geometry']==changed_points and old['geometry']==points and old['route_snapshot']['stops']==record['stops'],'changing template stops updates its path while existing trace snapshots stay intact')
    reversed_stops=list(reversed(changed_stops));cache_path([places[0],*reversed_stops,places[-1]])
    status,payload,_=call('update.php',{**record,'id':rid,'version':route['version'],'stops':reversed_stops});assert status==200,payload
    route=payload['data']
    check(route['stops']==reversed_stops,'ordered intermediate stops survive an edit and database reload')
    draft=dispatcher.call('/api/v1/transfers.php?action=draft')[1]['data']
    status,payload,_=dispatcher.call('/api/v1/transfers.php?action=draft','POST',dict(version=draft['version'],payload=dict(routeTemplate=rid,routeVersion=str(route['version']),origin=record['origin_address'],destination=record['destination_address'])))
    check(status==200 and dispatcher.call('/api/v1/transfers.php?action=draft')[1]['data']['payload']['routeTemplate']==rid,'SQL drafts retain the optional preset and its selected revision')

    simulation=simulation_control()[1]['data']
    status,payload,_=simulation_control(dict(command='start',version=simulation['version']));assert status==200,payload
    program=r"require 'backend/php/autoload.php';$c=Hospital\Config::load();echo tracking_tick(Hospital\Database::connect($c), $c);"
    tick=subprocess.run([PHP,'-r',program],cwd=ROOT,capture_output=True,text=True,check=True)
    check(tick.stdout=='1','the existing server simulator accepts mapped preset traces')
    verify_route_regressions(dispatcher, record, places)
    proof=Path(os.environ['HOSPITAL_ROUTE_TEST_DIR'])/'browser.json';proof.write_text(json.dumps(dict(username=username,password=PASSWORD,id=rid,trace_id=trace['id'],manual_trace_id=custom['id'])));proof.chmod(0o600)


def verify_route_regressions(dispatcher, record, places):
    """Exercise extracted persistence and mapping through the existing API/SQL fixture."""
    from documents_integration import admin_sql

    def request(action, body):
        return dispatcher.call('/api/v1/routes.php?action='+action, 'POST', body)

    def snapshot():
        return [sql('SELECT * FROM route_templates ORDER BY id'),
                sql('SELECT * FROM route_stops ORDER BY route_id,stop_number'),
                sql("SELECT * FROM audit_events WHERE resource_type='route' ORDER BY id")]

    points=cache_path(places)
    base={**record, 'name':'Ruta de regresión '+SUFFIX}
    trigger='route_regression_fault'
    for action in ['create', 'update']:
        if action=='update':
            status,payload,_=request('create',base); assert status==201,payload
            item=payload['data']
            body={**base,'id':item['id'],'version':item['version'],
                  'stops':[dict(name='Nueva parada ficticia',address='Calle ficticia 100')],
                  'notes':'Este cambio debe revertirse'}
            cache_path([places[0],*body['stops'],places[-1]])
        else:
            body=base
        before=snapshot()
        admin_sql("CREATE TRIGGER "+trigger+" BEFORE INSERT ON audit_events FOR EACH ROW BEGIN IF NEW.resource_type='route' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Disposable route audit fault'; END IF; END")
        try:
            status,payload,_=request(action,body)
            check(status==503 and payload['error']['code']=='SERVICE_UNAVAILABLE', 'route '+action+' reports an injected final audit failure')
            check(snapshot()==before, 'route '+action+' rolls back row, code, ordered stops, revision and audit together')
        finally:
            admin_sql('DROP TRIGGER '+trigger)
    before=snapshot()
    admin_sql("CREATE TRIGGER "+trigger+" BEFORE INSERT ON route_stops FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Disposable route stop fault'")
    try:
        status,payload,_=request('update',body)
        check(status==503 and snapshot()==before, 'failed replacement stop insertion restores the original stop rows and route revision')
    finally:
        admin_sql('DROP TRIGGER '+trigger)
    status,payload,_=request('update',body)
    check(status==200 and payload['data']['stops']==body['stops'], 'route update retry succeeds after rollback and keeps stop order')
    cache_path(places)
    good=dict(distance=5600,duration=900,geometry=dict(coordinates=[[p['longitude'],p['latitude']] for p in points]))
    bad_routes=[None,dict(code='NoRoute',routes=[good]),dict(code='Ok',routes=[])]
    for changes in [dict(distance=0),dict(duration=0),dict(geometry=dict(coordinates=[[-56,-34]])),
                    dict(geometry=dict(coordinates=[[-56,-34],[181,-34.1]])),
                    dict(geometry=dict(coordinates=[[-56,-34],['invalid',-34.1]]))]:
        bad_routes.append(dict(code='Ok',routes=[{**good,**changes}]))
    for index, response in enumerate(bad_routes):
        cache(router_url(points),response)
        status,payload,_=request('create',{**record,'name':'Mapa inválido '+SUFFIX+' '+str(index)})
        check(status==201 and payload['data']['geometry']==[] and payload['data']['duration_seconds']==record['duration_seconds'],
              'malformed route response '+str(index)+' preserves the manual itinerary and estimates without inventing geometry')
    for index, xy in enumerate([[-56],[-56,91],['invalid',-34]]):
        cache_path(places)
        url='https://127.0.0.1:9/api/?'+urlencode(dict(q=places[0]['address'],countrycode='UY',limit=1))
        cache(url,dict(features=[dict(geometry=dict(coordinates=xy))]))
        status,payload,_=request('create',{**record,'name':'Geocodificación inválida '+SUFFIX+' '+str(index)})
        check(status==201 and payload['data']['geometry']==[], 'invalid geocoder coordinate '+str(index)+' retains mapping fallback')
    cache_path(places)
    raw=[[-56.16-index*.00001,-34.89-index*.000005] for index in range(1501)]
    cache(router_url(points),dict(code='Ok',routes=[{**good,'geometry':dict(coordinates=raw)}]))
    status,payload,_=request('create',{**record,'name':'Geometría acotada '+SUFFIX});assert status==201,payload
    geometry=payload['data']['geometry']
    check(len(geometry)==1000 and geometry[0]==dict(longitude=raw[0][0],latitude=raw[0][1]) and
          geometry[-1]==dict(longitude=round(raw[-1][0],7),latitude=round(raw[-1][1],7)),
          'long road paths retain both endpoints and exactly 1000 bounded points')
    program="require 'backend/php/autoload.php';$c=Hospital\\Config::load();$d=Hospital\\Database::connect($c);$u=trim(stream_get_contents(STDIN));echo json_encode([route_mapping_cached($d,$u,microtime(true)-1)!==null,route_mapping_cached($d,$u.'&uncached=1',microtime(true)-1)]);"
    response=subprocess.run([PHP,'-r',program],cwd=ROOT,input=router_url(points),capture_output=True,text=True,check=True)
    check(json.loads(response.stdout)==[True,None], 'expired absolute deadline permits a cache hit and rejects an uncached request without HTTP')


if __name__=='__main__':run()
