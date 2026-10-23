/* Real SQL-backed QR creation and anonymous browser file access. */
'use strict';
const fs=require('fs'),path=require('path'),assert=require('assert');
const {chromium}=require('playwright');
const runtime=process.env.HOSPITAL_DOCUMENT_LINK_TEST_DIR||path.join(__dirname,'.runtime/document-links');
const fixtures=JSON.parse(fs.readFileSync(path.join(runtime,'browser.json')));
let checks=0;
function check(value,label){assert(value,label);checks++;}
async function ready(page){await page.evaluate(async()=>{await HospitalI18n.ready;await HospitalAuth.ready;});await page.locator('#qr-button').waitFor({state:'visible'});await page.waitForFunction(()=>!document.getElementById('qr-button').disabled);}
async function formJourney(browser, storageState) {
 const base='http://127.0.0.1:8885/hospital';
 const context=await browser.newContext({storageState,viewport:{width:390,height:844},permissions:['clipboard-read','clipboard-write']});
 context.setDefaultTimeout(12000);context.setDefaultNavigationTimeout(20000);
 const page=await context.newPage(),errors=[],dialogs=[];
 page.on('pageerror',error=>errors.push(error.message));
 page.on('dialog',async dialog=>{dialogs.push(dialog.type());await dialog.accept();});
 try {
  const upload=base+'/src/views/documents/documents-upload.php';
  await page.goto(upload);await page.waitForFunction(()=>document.querySelector('#documentForm').getAttribute('aria-busy')==='false');
  check(await page.locator('.sidebar-nav a[href="../management/management.php"]').count()===1,'Upload Document includes the mounted Management navigation link');
  check(await page.locator('html').getAttribute('lang')==='en'&&await page.locator('html').getAttribute('data-theme')==='light','mounted form reuses its English/light staff session');
  console.log('Verified document form initialization');
  const source=await page.request.get(base+'/api/v1/documents.php?action=content&id='+fixtures.pdf.id+'&version=1');
  const original=await source.body();check(source.status()===200,'form journey uses real immutable fixture bytes');
  await page.locator('#nombre').fill('Fictional browser document');
  await page.locator('#descripcion').fill('Retain these fields and original bytes after failure.');
  const category=await page.evaluate(async()=> (await DocumentApi.categories()).find(item=>item.code==='administrative').id);
  await page.locator('#category-button').click();await page.locator(`#category-menu [data-value="${category}"]`).click();
  await page.locator('#archivo').setInputFiles({name:'browser-original.pdf',mimeType:'application/pdf',buffer:original});
  const failure=/\/documents\.php\?action=create$/;
  await page.route(failure,route=>route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({success:false,error:{code:'SERVICE_UNAVAILABLE',message:'Focused retained-input fixture'}})}));
  await page.locator('[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#documentForm').getAttribute('aria-busy')==='false');
  check(page.url()===upload&&await page.locator('#nombre').inputValue()==='Fictional browser document'&&await page.locator('#descripcion').inputValue()==='Retain these fields and original bytes after failure.','failed upload retains typed metadata');
  check(await page.locator('#categoria').inputValue()===category&&await page.locator('#archivo').evaluate(el=>el.files[0]?.name==='browser-original.pdf')&&dialogs.length===0,'failed upload retains category/file and reports only to console');
  await page.unroute(failure);
  const createdResponse=page.waitForResponse(response=>new URL(response.url()).searchParams.get('action')==='create'&&response.request().method()==='POST');
  await page.locator('[type=submit]').click();const createdStatus=(await createdResponse).status();
  await page.waitForURL('**/documents.php?document_id=*');
  await page.waitForFunction(()=>Boolean(window.DocumentApi));
  const savedId=new URL(page.url()).searchParams.get('document_id');
  const item=await page.evaluate(id=>DocumentApi.get(id),savedId);
  check(createdStatus===201&&item.file.version===1,'retry saves one real document and returns to the mounted list');
  console.log('Verified document upload failure and persisted retry');
  await page.goto(base+'/src/views/documents/documents-edit.php?id='+item.id);
  await page.waitForFunction(()=>document.querySelector('#documentForm').getAttribute('aria-busy')==='false');
  check(await page.locator('#nombre').inputValue()===item.title&&await page.locator('#categoria').inputValue()===category&&await page.locator('#currentFile strong').textContent()==='browser-original.pdf','edit form restores persisted metadata and attachment');
  const image=await page.request.get(base+'/api/v1/documents.php?action=content&id='+fixtures.png.id);
  const replacement=await image.body();await page.locator('#nombre').fill('Fictional replacement');
  await page.locator('#archivo').setInputFiles({name:'browser-replacement.png',mimeType:'image/png',buffer:replacement});
  const replacedResponse=page.waitForResponse(response=>new URL(response.url()).searchParams.get('action')==='replace-file');
  await page.locator('[type=submit]').click();const replacedStatus=(await replacedResponse).status();await page.waitForURL('**/documents.php?document_id=*');
  await page.waitForFunction(()=>Boolean(window.DocumentApi));
  const replaced=await page.evaluate(id=>DocumentApi.get(id),item.id);
  check(replacedStatus===200&&replaced.file.version===2&&replaced.file_versions.length===2,'edit publishes a new immutable file version');
  console.log('Verified document replacement');
  await page.goto(base+'/src/views/documents/documents-viewer.php?id='+item.id);await ready(page);
  const currentBytes=await page.request.get(base+'/api/v1/documents.php?action=content&id='+item.id+'&version=2');
  const oldBytes=await page.request.get(base+'/api/v1/documents.php?action=content&id='+item.id+'&version=1');
  check((await currentBytes.body()).equals(replacement)&&(await oldBytes.body()).equals(original),'both downloaded versions retain their exact bytes');
  const versionButton=await page.locator('#file-version').evaluate(el=>el.dataset.hospitalButton);
  await page.locator('#'+versionButton).click();await page.locator('[role=option][data-value="1"]:visible').click();
  await page.waitForFunction(()=>document.getElementById('file-version').value==='1'&&!document.getElementById('file-version').disabled);
  await page.locator('#share-button').click();await page.locator('#share-dialog[open]').waitFor();
  const shared=new URL(await page.locator('#share-url').inputValue());
  check(shared.pathname.endsWith('/documents-viewer.php')&&shared.searchParams.get('file_version')==='1'&&!shared.searchParams.has('token'),'staff sharing stays pinned to the selected version without patient credentials');
  const anonymous=await browser.newContext();const staffLink=await anonymous.request.get(shared.href);
  check(staffLink.url().includes('/index.php?reason=session_expired'),'ordinary sharing requires a staff login');await anonymous.close();
  await page.locator('#share-dialog [data-close-dialog]').click();
  await page.locator('#delete-button').click();
  await page.locator('#archive-dialog[open]').waitFor();
  check((await page.locator('#archive-description').textContent()).includes('Fictional replacement') && await page.locator('#archive-dialog [autofocus]').evaluate(button=>button===document.activeElement),'archive dialog names the actual document and defaults focus to Cancel');
  const failedArchive=/\/documents\.php\?action=archive$/;
  await page.route(failedArchive,route=>route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({success:false,error:{code:'SERVICE_UNAVAILABLE',message:'Fictional archive failure'}})}));
  await page.locator('#confirm-archive').click();await page.waitForFunction(()=>!document.getElementById('confirm-archive').disabled);
  check(await page.locator('#archive-dialog').evaluate(dialog=>dialog.open),'failed archive retains the styled dialog and allows retry');
  await page.unroute(failedArchive);
  await page.locator('#confirm-archive').click();await page.waitForURL('**/documents.php');
  const archived=await page.evaluate(async id=>DocumentApi.get(id),item.id);
  check(Boolean(archived.archived_at)&&archived.file_versions.length===2,'archive preserves both versions and revision history');
  await page.goto(base+'/src/views/documents/documents-viewer.php?id='+item.id);
  await page.waitForFunction(()=>!document.getElementById('delete-button').disabled);
  check(await page.locator('#qr-button').isDisabled()&&await page.locator('#download-button').isDisabled(),'archived viewer disables file and patient sharing actions');
  await page.locator('#delete-button').click();await page.waitForURL('**/documents.php');
  const restored=await page.evaluate(async id=>DocumentApi.get(id),item.id);
  check(!restored.archived_at&&restored.file_versions.length===2,'restore retains the complete document history');
  check(errors.length===0&&dialogs.length===0,'document journey has no script errors or native confirmations');
 } catch(error) {
  await page.screenshot({path:path.join(runtime,'form-failure.png')}).catch(()=>{});
  console.error('Document form failed at',new URL(page.url()).pathname);
  throw error;
 } finally {await context.close();}
}
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try{
  let mountedState;
  for(const mount of ['', '/hospital'])for(const theme of ['dark','light'])for(const mobile of [false,true]){
   const base=mount?'http://127.0.0.1:8885/hospital':'http://127.0.0.1:8884';
   const locale=mobile?'en':'es-UY',context=await browser.newContext({viewport:mobile?{width:390,height:844}:{width:1440,height:900},permissions:['clipboard-read','clipboard-write']});
   context.setDefaultTimeout(12000);context.setDefaultNavigationTimeout(20000);
   const page=await context.newPage(),errors=[],dialogs=[];
   page.on('pageerror',e=>errors.push(e.message));page.on('dialog',async d=>{dialogs.push(d.type());await d.dismiss();});
   await page.goto(base+'/');await page.evaluate(async ({username,password,locale,theme})=>{
    await HospitalApi.post('auth.php?action=login',{username,password});
    const state=await HospitalApi.get('settings.php?action=get');
    await HospitalApi.post('settings.php?action=preferences-update',{version:state.settings.version,locale,theme});
   },{...fixtures,locale,theme});
   await page.goto(base+'/src/views/documents/documents-viewer.php?id='+fixtures.pdf.id);await ready(page);
   if(theme==='light'){
    await page.locator('.sidebar-action').click();
    check(await page.locator('.app-shell').evaluate(el=>el.classList.contains('menu-open')),'QR also works with expanded navigation');
    if(mobile){
     await page.keyboard.press('Escape');
     check(await page.locator('.app-shell').evaluate(el=>!el.classList.contains('menu-open')),'phone drawer dismisses before accessing document actions');
    }
   }
   check(await page.evaluate(()=>HospitalI18n.locale===document.documentElement.lang&&(HospitalTheme.current==='dark'||HospitalTheme.current==='light')),'hydrated locale/theme');
   // Observe the input to the real encoder while still drawing its actual modules.
   await page.evaluate(()=>{const original=window.qrcode;window.qrcode=(...args)=>{const result=original(...args),add=result.addData;result.addData=(value,...rest)=>{window.encodedPatientUrl=value;return add.call(result,value,...rest);};return result;};});
   await page.locator('#qr-button').click();await page.locator('#qr-dialog[open]').waitFor();
   const url=await page.locator('#patient-qr-url').inputValue();
   const parsed=new URL(url);
   check(parsed.origin===new URL(base).origin&&parsed.pathname===mount+'/api/v1/documents.php'&&parsed.searchParams.get('action')==='public-file'&&parsed.searchParams.get('token').length===64,'QR points directly to mounted file endpoint');
   check(await page.evaluate(url=>encodedPatientUrl===url,url),'canvas encodes the exact patient access URL');
   check(await page.evaluate(()=>{const c=document.getElementById('qr-canvas'),a=c.getContext('2d').getImageData(0,0,c.width,c.height).data;let dark=0;for(let i=0;i<a.length;i+=4)if(a[i]<30)dark++;return dark>1000;}),'QR contains real dark modules');
   check(!(await page.locator('#qr-expiry').textContent()).includes('{')&&(await page.locator('#qr-expiry').textContent()).includes('24'),'expiry and selected version rendered');
   await page.locator('#copy-qr-link').click();
   check(await page.evaluate(()=>navigator.clipboard.readText())===url,'copy action copies patient credential rather than staff viewer URL');
   const anonymous=await browser.newContext();
   const response=await anonymous.request.get(url);
   check(response.status()===200&&response.headers()['content-type']==='application/pdf'&&response.headers()['content-disposition'].startsWith('inline;'),'anonymous browser receives inline PDF');
   check((await response.body()).subarray(0,5).toString()==='%PDF-','response contains file bytes and no custom viewer');
   check((await anonymous.request.get(base+'/api/v1/documents.php?action=get&id='+fixtures.pdf.id)).status()===401,'capability grants no staff API access');
   const dialog=await page.locator('#qr-dialog').evaluate(el=>({width:el.getBoundingClientRect().width,height:el.getBoundingClientRect().height,overflow:getComputedStyle(el).overflowY,scroll:el.scrollHeight,client:el.clientHeight}));
   check(dialog.width<=(mobile?390:1440)&&dialog.height<=(mobile?844:900),'QR dialog fits viewport with native overflow');
   check(await page.locator('#qr-dialog .dialog-icon img').count()===1 && await page.locator('#revoke-qr-link').evaluate(button=>button.classList.contains('dialog-secondary')),'QR uses the management header and outlined revoke action');
   if(!mount)await page.screenshot({path:path.join(runtime,`qr-${theme}-${mobile?'mobile':'desktop'}.png`)});
   if(!mount&&!mobile&&theme==='dark'){
    // Opening the URL is a real native PDF navigation, independent of the staff page.
    const native=await anonymous.newPage();const nativeResponse=await native.goto(url);
    check(nativeResponse.status()===200&&native.url()===url,'patient opens the file directly without a login redirect');
    await native.waitForTimeout(700);await native.screenshot({path:path.join(runtime,'patient-native-pdf.png')});await native.close();
   }
   await page.locator('#revoke-qr-link').click();await page.locator('#qr-dialog').waitFor({state:'hidden'});
   check((await anonymous.request.get(url)).status()===404,'revoke action immediately blocks anonymous retrieval');
   await page.locator('#qr-button').click();await page.locator('#qr-dialog[open]').waitFor();
   const renewed=await page.locator('#patient-qr-url').inputValue();
   check(renewed!==url&&(await anonymous.request.get(renewed)).status()===200,'reopening after revoke produces a different working token');
   await page.locator('#qr-dialog [data-close-dialog]').click();
   // A failed server write reports to the console without a fabricated QR or popup.
   await page.route(/\/documents\.php\?action=patient-link$/,route=>route.fulfill({status:409,contentType:'application/json',body:JSON.stringify({success:false,error:{code:'VERSION_CONFLICT',message:'Focused failure fixture'}})}));
   await page.locator('#qr-button').click();await page.waitForFunction(()=>!document.getElementById('qr-button').disabled);
   check(!(await page.locator('#qr-dialog').evaluate(el=>el.open))&&await page.locator('#patient-qr-url').inputValue()==='','failed issuance leaves no misleading QR or link');
   await page.unroute(/\/documents\.php\?action=patient-link$/);
   await page.locator('#share-button').click();await page.locator('#share-dialog[open]').waitFor();
   const staffUrl=await page.locator('#share-url').inputValue();
   await page.locator('#copy-share-link').click();
   check(await page.evaluate(()=>navigator.clipboard.readText())===staffUrl && await page.locator('#share-dialog .dialog-icon img').count()===1,'styled staff share keeps its exact version URL and working primary copy action');
   await page.locator('#share-dialog [data-close-dialog]').click();
   await page.locator('#delete-button').click();await page.locator('#archive-dialog[open]').waitFor();
   const archiveLabel = locale === 'en' ? 'Archive' : 'Archivar';
   check(await page.locator('#archive-title').textContent() === archiveLabel && await page.locator('#confirm-archive').textContent() === archiveLabel,'archive title and confirm use the existing archive translation');
   const archive=await page.locator('#archive-dialog').evaluate(dialog=>({width:dialog.getBoundingClientRect().width,height:dialog.getBoundingClientRect().height}));
   check(archive.width<=(mobile?390:1440) && archive.height<=(mobile?844:900) && (await page.locator('#archive-description').textContent()).includes(fixtures.pdf.title),'localized archive dialog fits the viewport and includes the document name');
   await page.locator('#archive-dialog [autofocus]').click();
   check(!(await page.locator('#archive-dialog').evaluate(dialog=>dialog.open)) && !(await page.evaluate(id=>DocumentApi.get(id),fixtures.pdf.id)).archived_at,'Cancel leaves document bytes and archive state unchanged');
   check(errors.length===0&&dialogs.length===0,'no script errors or native dialogs');
   // The staff header geometry is independent of the patient QR dialog.
   if(!mobile)check(await page.locator('.topbar').evaluate(el=>Math.round(el.getBoundingClientRect().height))===100,'desktop header retains 100px geometry');
   if(mount)mountedState=await context.storageState();
   await anonymous.close();await context.close();
   console.log('Verified QR browser case:',mount||'root',theme,mobile?'mobile':'desktop');
  }
  // Native image navigation and DOCX download use the same patient access flow.
  const staff=await browser.newContext(),page=await staff.newPage();await page.goto('http://127.0.0.1:8884/');
  await page.evaluate(async f=>HospitalApi.post('auth.php?action=login',{username:f.username,password:f.password}),fixtures);
  const anon=await browser.newContext();
  for(const ext of ['png','docx']){
   await page.goto('http://127.0.0.1:8884/src/views/documents/documents-viewer.php?id='+fixtures[ext].id);await ready(page);
   await page.locator('#qr-button').click();await page.locator('#qr-dialog[open]').waitFor();
   const url=await page.locator('#patient-qr-url').inputValue(),native=await anon.newPage();
   if(ext==='docx'){
    const event=native.waitForEvent('download');await native.goto(url).catch(error=>{if(!error.message.includes('Download is starting'))throw error;});
    const download=await event;check(download.suggestedFilename()==='patient.docx','DOCX opens as a browser download');await download.delete();
   }else{
    await native.goto(url);check(await native.locator('img').evaluate(el=>el.naturalWidth===20),'PNG opens directly in the native image document');
   }
   await native.close();
  }
  await anon.close();await staff.close();
  await formJourney(browser,mountedState);
  console.log('PASS',checks,'focused browser checks across eight root/mount, language, theme and viewport cases');
 }finally{await browser.close();}
})().catch(error=>{console.error(error.name+': '+error.message.replace(/token=[a-f0-9]+/g,'token=[redacted]'));process.exit(1);});
