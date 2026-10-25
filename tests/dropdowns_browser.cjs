/* Shared dropdown checks using the existing Playwright/runtime fixtures. Disposable SQL only. */
'use strict';
const fs=require('fs'),path=require('path'),assert=require('assert/strict'),{chromium}=require('playwright');
const runtime=process.env.HOSPITAL_DROPDOWN_TEST_DIR || path.join(__dirname,'.runtime/stage3/browser');
const env=JSON.parse(fs.readFileSync(path.join(runtime,'env.json'))),records=JSON.parse(fs.readFileSync(env.HOSPITAL_I18N_TEST_RECORDS));
assert(env.HOSPITAL_DB_NAME.endsWith('_test'));
const base=env.HOSPITAL_AUTH_TEST_URL,report={controls:0,variants:[],journeys:[],errors:[]};
const pages=[['management/ambulances.php','#new-ambulance'],['management/patients.php','#new-patient-button'],['management/doctors.php','#new-doctor-button'],['management/personnel.php'],['management/destinations.php'],['management/routes.php','#new-route'],['settings.php'],['documents/documents-upload.php'],['documents/documents-edit.php',null,records.document.id],['documents/documents-viewer.php',null,records.document.id],['traces/trace-new.php'],['traces/trace-view.php','#edit-transfer',records.transfer.id],['surveys/survey-new.php']];
async function ready(p){await p.evaluate(async()=>{await HospitalI18n.ready;await HospitalAuth?.ready;});await p.waitForLoadState('networkidle',{timeout:2000}).catch(()=>{});await p.evaluate(()=>Promise.race([document.fonts.ready,new Promise(resolve=>setTimeout(resolve,4000))]));}
async function visit(p,file,id){await p.evaluate(()=>HospitalApi.post('auth.php?action=heartbeat'));const target=base+'/src/views/'+file+(id?'?id='+id:'');await p.goto(target,{waitUntil:'domcontentloaded'});assert.equal(new URL(p.url()).pathname,new URL(target).pathname,'unexpected page redirect');await ready(p);}
async function selectButton(p,selector){const id=await p.locator(selector).getAttribute('data-hospital-button');assert(id,'missing shared dropdown for '+selector);return p.locator('#'+id);}
async function reviewControls(p,label){
 const states=await p.locator('select').evaluateAll(nodes=>nodes.map((node,index)=>({index,button:node.dataset.hospitalButton,multiple:node.multiple,disabled:node.disabled})));
 for(const state of states){
  if(state.multiple)continue;assert(state.button,label+' has an unstyled select');
  const control=p.locator('select').nth(state.index),button=p.locator('#'+state.button);
  if(!await button.isVisible())continue;
  if(await p.locator('dialog[open]').count() && !await control.evaluate(node=>Boolean(node.closest('dialog[open]'))))continue;
  const box=await button.boundingBox();assert(box.height>=28 && box.width>20,label+' '+state.button+' field dimensions '+JSON.stringify(box));
  if(await control.evaluate(node=>node.classList.contains('hospital-native-select'))){
   assert.equal(await control.evaluate(node=>getComputedStyle(node).opacity),'0',label+' native select stays hidden, including disabled fields');
   assert.equal(await control.getAttribute('aria-hidden'),'true',label+' one accessible control');
   assert.equal(await button.locator('xpath=..').locator(':scope > .hospital-select-trigger').count(),1,label+' one dropdown trigger');
  }
  if(state.disabled){assert(await button.isDisabled(),label+' disabled control');continue;}
  await button.scrollIntoViewIfNeeded();await p.waitForTimeout(180);await button.evaluate(node=>node.focus({preventScroll:true}));await button.press('ArrowDown');
  const menu=p.locator('#'+await button.getAttribute('aria-controls'));await menu.waitFor();await p.waitForTimeout(200);
  assert.equal(await button.getAttribute('aria-expanded'),'true',label+' '+state.button+' remains open');
  const style=await menu.evaluate(node=>{const s=getComputedStyle(node);return {radius:s.borderRadius,bg:s.backgroundColor,padding:s.padding}});
  assert.equal(style.radius,'11px',label+' menu radius');assert.equal(style.padding,'6px',label+' menu padding');
  assert.notEqual(style.bg,'rgba(0, 0, 0, 0)',label+' opaque menu');
  const rect=await menu.boundingBox(),trigger=await button.boundingBox(),viewport=p.viewportSize();
  assert(rect.x>=0 && rect.x+rect.width<=viewport.width+1,label+' horizontal clipping');
  assert(rect.y>=0 && rect.y+rect.height<=viewport.height+1,label+' vertical clipping');
  assert(rect.y>=trigger.y+trigger.height+4 || rect.y+rect.height<=trigger.y-4,label+' menu overlaps its field');
  const choices=menu.locator('button:not(:disabled)');if(await choices.count()){
   await choices.first().focus();await p.keyboard.press('End');assert(await choices.last().evaluate(node=>node===document.activeElement),label+' End key');
   await p.keyboard.press('Home');assert(await choices.first().evaluate(node=>node===document.activeElement),label+' Home key');
  }
  await p.keyboard.press('Escape');assert(await menu.isHidden(),label+' Escape');assert(await button.evaluate(node=>node===document.activeElement),label+' returns focus');
  assert.equal(await control.isDisabled(),state.disabled);report.controls++;
 }
}
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try{
  const context=await browser.newContext();await context.route(/\/api\/v1\/settings\.php\?action=diagnostics$/,r=>r.fulfill({status:200,contentType:'application/json',body:'{"success":true,"data":{"recorded":true}}'}));
  const p=await context.newPage();p.setDefaultTimeout(10000);p.setDefaultNavigationTimeout(15000);p.on('pageerror',e=>report.errors.push(e.message));p.on('dialog',async d=>{report.errors.push('unexpected '+d.type());await d.dismiss()});
  await p.goto(base+'/',{waitUntil:'domcontentloaded'});await ready(p);await p.evaluate(password=>HospitalApi.post('auth.php?action=login',{username:'system_admin',password}),env.HOSPITAL_ADMIN_PASSWORD);
  for(const [size,viewport] of (process.env.HOSPITAL_DROPDOWN_JOURNEY_ONLY ? [] : [['desktop',{width:1440,height:900}],['mobile',{width:390,height:844}]]))for(const theme of ['dark','light']){
   await p.setViewportSize(viewport);
   for(const [file,opener,id] of pages.filter(([file])=>!process.env.HOSPITAL_DROPDOWN_FILES||process.env.HOSPITAL_DROPDOWN_FILES.split(',').includes(file))){
    await visit(p,file,id);await p.evaluate(theme=>HospitalTheme.preview(theme),theme);
    if(opener){await reviewControls(p,file+' '+size+' '+theme+' page');const b=p.locator(opener);if(await b.isEnabled())await b.click();}
    await p.waitForTimeout(100);await reviewControls(p,file+' '+size+' '+theme);
    // Suggestions use the same menu while still accepting free text.
    const inputs=p.locator('input[data-hospital-list]:visible:not(:disabled):not([readonly])');
    if(await inputs.count()){
     const input=inputs.first();await input.fill('');await input.focus();
     const menu=p.locator('#'+await input.getAttribute('aria-controls'));
     if(await menu.locator('button').count()){
      await input.press('ArrowDown');assert(await menu.isVisible());const expected=await menu.locator('button').first().getAttribute('data-value');
      await input.press('Enter');assert.equal(await input.inputValue(),expected);assert(await menu.isHidden());
     }
     await input.fill('Texto ficticio libre sin sugerencia');assert.equal(await input.inputValue(),'Texto ficticio libre sin sugerencia');
    }
    if(file==='surveys/survey-new.php'){
     await p.locator('.builder-step[data-step="2"]').click();await p.locator('#add-default-question').click();await p.waitForTimeout(100);await reviewControls(p,file+' inspector '+size+' '+theme);
     await p.locator('.builder-step[data-step="3"]').click();await p.waitForTimeout(100);await reviewControls(p,file+' participation '+size+' '+theme);
    }
    report.variants.push({file,size,theme});
    if(file==='management/patients.php'||file==='traces/trace-new.php')await p.screenshot({path:path.join(runtime,`dropdown-${path.basename(file)}-${size}-${theme}.png`)});
   }
   console.log('PASS shared dropdowns, keyboard, bounds, disabled fields and suggestions: '+size+' '+theme);
  }
  await p.setViewportSize({width:390,height:844});await visit(p,'management/patients.php');await p.locator('#new-patient-button').click();
  await p.locator('[name=name]').fill('Paciente Dropdown Ficticio');await p.locator('[name=dni]').fill(String(79000000+Math.floor(Math.random()*999999)));await p.locator('[name=phone]').fill('099 123 456');
  const sex=await selectButton(p,'[name=sex]');await sex.click();await p.locator('#'+await sex.getAttribute('aria-controls')).locator('[data-value=female]').click();
  const status=await selectButton(p,'[name=status]');await status.click();await p.locator('#'+await status.getAttribute('aria-controls')).locator('[data-value=""]').click();
  assert(await p.locator('#patient-form').evaluate(form=>form.checkValidity()),'the fictional patient fixture must satisfy the existing form rules');
  const endpoint=base+'/api/v1/patients.php?action=create';
  await p.route(endpoint,r=>r.fulfill({status:503,contentType:'application/json',body:'{"success":false,"error":{"code":"SERVICE_UNAVAILABLE","message":"Offline test"}}'}));
  let response=p.waitForResponse(r=>r.url()===endpoint);await p.locator('#patient-form button[type=submit]').click();assert.equal((await response).status(),503);
  await p.waitForFunction(()=>!document.querySelector('#patient-form button[type=submit]').disabled);
  assert.equal(await p.locator('[name=sex]').inputValue(),'female');assert.equal(await p.locator('[name=name]').inputValue(),'Paciente Dropdown Ficticio');assert(await p.locator('#patient-dialog').isVisible());assert(await p.locator('#patient-toast').isHidden());
  assert(await (await selectButton(p,'[name=doctor]')).isDisabled(),'disabled clinical fields remain disabled after a failed save');
  await p.unroute(endpoint);response=p.waitForResponse(r=>r.url()===endpoint);await p.locator('#patient-form button[type=submit]').click();const result=await response;assert.equal(result.status(),201);const patient=(await result.json()).data;
  const saved=await p.evaluate(id=>HospitalApi.get('patients.php?action=get&id='+id),patient.id);assert.equal(saved.sex,'female');
  await p.evaluate(patient=>HospitalApi.post('patients.php?action=archive',{id:patient.id,version:patient.version}),saved);
  report.journeys.push('Patient selection survives a 503, persists through the real API, and archives successfully.');
  await visit(p,'management/doctors.php');await p.locator('#new-doctor-button').click();
  await p.evaluate(()=>{const select=document.createElement('select');select.id='dynamic-dropdown-proof';select.innerHTML='<option value="a">Uno</option><option value="b">Dos</option>';document.querySelector('dialog[open] form').append(select)});
  await p.waitForFunction(()=>document.querySelector('#dynamic-dropdown-proof').dataset.hospitalButton);
  const dynamic=await selectButton(p,'#dynamic-dropdown-proof');await dynamic.click();await p.locator('#'+await dynamic.getAttribute('aria-controls')).locator('[data-value=b]').click();assert.equal(await p.locator('#dynamic-dropdown-proof').inputValue(),'b');
  await p.evaluate(()=>{const s=document.querySelector('#dynamic-dropdown-proof');s.disabled=true;});await p.waitForFunction(()=>document.querySelector('#dynamic-dropdown-proof-button').disabled);
  report.journeys.push('Dynamic controls receive the same dropdown and synchronize disabled state.');
  assert.deepEqual(report.errors,[]);console.log('PASS real patient persistence, retained input, dynamic controls and failure feedback');
 }finally{fs.writeFileSync(path.join(runtime,'dropdown-report.json'),JSON.stringify(report,null,2));await browser.close();}
 console.log('TOTAL',report.controls,'dropdown checks across',report.variants.length,'page/theme/viewport variants;',report.journeys.length,'journeys');
})().catch(e=>{console.error(e);process.exitCode=1});
