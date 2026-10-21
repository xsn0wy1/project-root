"""Prepare fictional browser records in an already configured disposable *_test environment."""
from pathlib import Path
import json,os
assert os.environ.get('HOSPITAL_DB_NAME','').endswith('_test'),'Use disposable data only.'
from auth_integration import Client,ADMIN_PASSWORD
from documents_integration import fields as doc_fields,upload,pdf_bytes
from patients_integration import fields as patient_fields
from surveys_integration import record,item
from datetime import datetime,timedelta,timezone
c=Client();assert c.login('system_admin',ADMIN_PASSWORD)[0]==200

def get(path):
 status,payload,_=c.call('/api/v1/'+path);assert status==200,payload;return payload['data']
def post(path,body):
 status,payload,_=c.call('/api/v1/'+path,'POST',body);assert status in [200,201],payload;return payload['data']
patient=post('patients.php?action=create',{**patient_fields(c),'full_name':'Inicio Guardar','national_id':'77001231'})
doctor=post('doctors.php?action=create',dict(title='Dra.',full_name='Doctora Guardar',national_id='77001232',license='MSP-77001232',specialty='Cardiología',department='Consultorios Externos',office='Consultorio 12',phone='099 123 456',email='doctora@hospital.example',availability='available',shift='night',days='rotating',start_time='22:00',end_time='06:00',notes='Datos ficticios.\nGuardia nocturna.'))
crew=post('personnel.php?action=create',dict(full_name='Chofer Guardar',national_id='77001233',phone='099 123 456',responsibility='driver',driving_license='A77001233',availability='active'))
destination=post('destinations.php?action=create',dict(name='Guardar',address='Calle Ficticia 77',locality='Montevideo',facility_type='health_center',phone='2487 1515',notes='Entrada lateral',availability='active'))
category=next(row['id'] for row in get('catalogs.php?action=document-categories') if not row['requires_patient'])
content=pdf_bytes(pages=2);status,payload=upload(c,doc_fields(c,category,title='Guardar <b>Documento</b>'),content);assert status==201,payload;doc=payload['data']
route=post('routes.php?action=create',dict(name='Ruta Guardar',category='discharge',direction='round_trip',availability='active',origin_name='Clínica ficticia',origin_address='Calle Ficticia 10',destination_name='Centro ficticio',destination_address='Calle Ficticia 20',stops=[],distance_meters=5600,duration_seconds=900,notes='Entrada lateral'))
catalog=get('transfers.php?action=catalogs');mapped=next(r for r in catalog['routes'] if r['geometry'])
transfer=post('transfers.php?action=create',dict(route_template_id=mapped['id'],ambulance_id=catalog['ambulances'][0]['id'],driver_id=crew['id'],companion_id=None,patient_id=None,scheduled_at=(datetime.now(timezone(timedelta(hours=-3)))+timedelta(hours=2)).replace(microsecond=0).isoformat(),cargo_type='equipment',priority='high',reason='insumos',notes='Nota original',special_equipment=False,with_family=False,interdepartmental=False,critical_patient=False,create_token=get('transfers.php?action=create-token')['create_token']))
s=record();s['title']='Guardar';s['form_title']='Encuesta original';s['items']=[item('rating','rating',**{})];s['config']['identity']='optional_name';survey=post('surveys.php?action=create',s);survey=post('surveys.php?action=transition',dict(id=survey['id'],version=survey['version'],action='publish'));links=get('surveys.php?action=links&id='+survey['id'])
records_path=Path(os.environ.get('HOSPITAL_I18N_TEST_RECORDS','tests/.runtime/i18n-records.json'))
records_path.write_text(json.dumps(dict(patient=patient,doctor=doctor,crew=crew,destination=destination,document=doc,route=route,transfer=transfer,survey=survey,token=links[0]['token'])));records_path.chmod(0o600)
print('Fictional records created for every module; original Spanish record text retained.')
