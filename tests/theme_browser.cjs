/* Focused appearance checks; requires the disposable environment prepared under tests/.runtime/theme. */
'use strict';
const fs=require('fs'), path=require('path'), assert=require('assert');
const runtime=process.env.HOSPITAL_THEME_TEST_DIR || path.join(__dirname,'.runtime/theme');
Object.assign(process.env,JSON.parse(fs.readFileSync(path.join(runtime,'env.json'))));
assert(process.env.HOSPITAL_DB_NAME.endsWith('_test'));
const {chromium}=require('playwright');
const records=JSON.parse(fs.readFileSync(path.join(runtime,'records.json')));
const base=process.env.HOSPITAL_AUTH_TEST_URL;
const pages=fs.readdirSync(path.join(__dirname,'../public'),{recursive:true}).filter(f=>/\.(html|php)$/.test(f)&&!f.endsWith('/error.php')&&(!process.env.THEME_FILES||process.env.THEME_FILES.split(',').some(name=>f.endsWith(name)))).sort();
const queries={ 'documents-viewer.php':records.document.id,'documents-edit.php':records.document.id,'trace-view.php':records.transfer.id,'survey-view.php':records.survey.id,'survey-new.php':records.survey.id };
function url(file){const id=queries[path.basename(file)]; return base+'/'+file+(id?'?id='+id:'')+(file.endsWith('survey-user.php')?'#token='+records.token:'');}
async function ready(p){await p.evaluate(async()=>{await window.HospitalI18n?.ready;await window.HospitalAuth?.ready;if(window.HospitalAuth?.current)await HospitalAuth.refresh(true);}); await p.waitForLoadState('networkidle',{timeout:8000}).catch(()=>{}); await p.evaluate(()=>document.fonts.ready); await p.addStyleTag({content:'*,*::before,*::after{animation-delay:0s!important;animation-duration:0s!important;transition:none!important}'}); await p.waitForTimeout(140);}
async function geometry(p){return p.evaluate(()=>Object.fromEntries(['.tarjeta','.app-shell','.topbar','.sidebar','.hero-panel','.bg-panel','.patient-header','.patient-survey','.patient-layout'].map(s=>{const e=document.querySelector(s);if(!e)return [s,null];const b=e.getBoundingClientRect(),c=getComputedStyle(e);return [s,{x:b.x,y:b.y,w:b.width,h:b.height,blur:c.backdropFilter,radius:c.borderRadius}];})));}
async function authFont(page) {
 if (!await page.locator('.tarjeta').count()) return;
 assert.equal(await page.locator('link[href*="assets/vendor/fonts/inclusive-regular.css"]').count(), 1,
  'auth/error pages link the existing local Inclusive Sans stylesheet once');
 assert(await page.evaluate(() => [...document.fonts].some(font =>
  font.family.includes('Inclusive Sans') && font.status === 'loaded')),
 'Inclusive Sans is actually loaded for the auth/error card');
 assert(await page.locator('.tarjeta').evaluate(element =>
  getComputedStyle(element).fontFamily.includes('Inclusive Sans')), 'the card uses Inclusive Sans');
}
async function errorPages(browser, staff) {
 const setup = await staff.newPage();
 await setup.goto(base+'/src/views/settings.php'); await ready(setup);
 const original = await setup.evaluate(()=>HospitalApi.get('settings.php?action=get'));
 const proof={cases:[],scriptErrors:[],assetErrors:[]}, rendered={};
 const output=path.join(runtime,'error-pages'); fs.mkdirSync(output,{recursive:true});
 try {
  for(const locale of ['es-UY','en']) for(const theme of ['dark','light']) {
   await setup.evaluate(async ({locale,theme})=>{
    const state=await HospitalApi.get('settings.php?action=get');
    await HospitalApi.post('settings.php?action=preferences-update',{version:state.settings.version,locale,theme});
   },{locale,theme});
   const dictionary=JSON.parse(fs.readFileSync(path.join(__dirname,'../db/json',locale==='en'?'en.json':'es.json')));
   for(const authenticated of [false,true]) {
    const context=await browser.newContext(authenticated?{storageState:await staff.storageState()}:{});
    await context.addInitScript(({theme,authenticated})=>{
     for(const mount of ['/','/hospital/'])localStorage.setItem('hospital-theme:'+mount,authenticated?(theme==='light'?'dark':'light'):theme);
    },{theme,authenticated});
    const page=await context.newPage();
    page.on('pageerror',error=>proof.scriptErrors.push(error.message));
    page.on('response',response=>{if(response.status()>=400&&['stylesheet','script','image','font'].includes(response.request().resourceType()))proof.assetErrors.push(response.url());});
    try {
     for(const mount of ['', '/hospital']) for(const mobile of [false,true]) for(const status of [403,404,500,503]) {
      const origin=mount?base.replace(/:\d+$/,':'+(Number(new URL(base).port)+1)):base;
      const name=[status,locale,theme,authenticated?'staff':'anonymous',mount?'mounted':'root',mobile?'mobile':'desktop'].join('-');
      await page.setViewportSize(mobile?{width:390,height:844}:{width:1440,height:900});
      const response=await page.goto(origin+mount+'/src/views/error.php?status='+status+'&lang='+locale);
      assert.equal(response.status(),status,name+' status');
      await page.evaluate(()=>document.fonts.ready);
      await authFont(page);
      const title=status===403?'forbidden_title':status===404?'not_found_title':'server_title';
      const description=status===403?'forbidden_description':status===404?'not_found_description':'server_description';
      assert.equal(await page.locator('h1').textContent(),dictionary['error_page.'+title],name+' title');
      assert.equal(await page.locator('main > p').first().textContent(),dictionary['error_page.'+description],name+' description');
      assert.equal(await page.locator('button').textContent(),dictionary['error_page.'+(authenticated?'home':'login')],name+' action');
      assert.equal(await page.locator('html').getAttribute('lang'),locale,name+' language');
      assert.equal(await page.locator('html').getAttribute('data-theme'),theme,name+' theme, including stale staff cache');
      assert.equal(response.headers()['content-language'],locale,name+' language header');
      assert.equal(await page.locator('small').count(),status>=500?1:0,name+' request ID visibility');
      if(status>=500)assert.match(await page.locator('small').textContent(),/[a-f0-9]{24}$/);
      const geometry=await page.locator('.error-card').boundingBox();
      assert(geometry.x>=0&&geometry.x+geometry.width<= (mobile?390:1440),name+' card fits');
      assert(await page.locator('body').isVisible(),name+' server-rendered English never stays hidden');
      assert.equal(await page.locator('script').count(),2,name+' reuses the existing theme and UI scripts only');
      assert.deepEqual(await page.locator('script[src]').evaluateAll(nodes => nodes.map(node => new URL(node.src).pathname.split('/').pop()).sort()),
       ['theme.js', 'ui.js'], name+' loads exactly the expected shared scripts');
      assert(await page.evaluate(() => typeof HospitalNotify.show === 'function'), name+' shared notification component is available');
      if(!mount&&!mobile)await page.screenshot({path:path.join(output,name+'.png')});
      if(!authenticated&&!mount&&!mobile&&locale==='es-UY'&&theme==='dark'&&status===404)rendered['public/src/views/error.php']=await response.text();
      proof.cases.push(name);
     }
     const missing=await page.goto(base+'/src/views/missing-error-check.php?lang='+locale);
     assert.equal(missing.status(),404);assert(await page.locator('.error-card').isVisible());
     await page.locator('button').click();
     await page.waitForURL('**/'+(authenticated?'src/views/main-menu.php':'index.php')+'*');
     const staticMissing=await context.request.get(base+'/assets/missing-error-check.svg');
     assert.equal(staticMissing.status(),404);assert.equal(await staticMissing.text(),'');
    } finally {await context.close();}
   }
  }
  assert.equal(proof.scriptErrors.length,0,JSON.stringify(proof.scriptErrors));
  assert.equal(proof.assetErrors.length,0,JSON.stringify(proof.assetErrors));
  fs.writeFileSync(path.join(output,'rendered.json'),JSON.stringify(rendered));
  console.log('Error pages: '+proof.cases.length+' status/language/theme/auth/mount/viewport cases, correct buttons, request IDs and no missing assets.');
 } finally {
  await setup.evaluate(async original=>{
   const state=await HospitalApi.get('settings.php?action=get');
   await HospitalApi.post('settings.php?action=preferences-update',{version:state.settings.version,locale:original.locale,theme:original.theme});
  },original.settings);
  await setup.close();fs.writeFileSync(path.join(output,'report.json'),JSON.stringify(proof,null,2));
 }
}
(async()=>{
 const baseline=process.argv.includes('--baseline'), browser=await chromium.launch({channel:'chrome',headless:true});
 const staff=await browser.newContext(), anon=await browser.newContext();
 const login=await staff.newPage();await login.goto(base+'/');await ready(login);
 await login.evaluate(async password=>{await HospitalApi.post('auth.php?action=login',{username:'system_admin',password});},process.env.HOSPITAL_ADMIN_PASSWORD);
 await staff.storageState({path:path.join(runtime,'auth.json')});fs.chmodSync(path.join(runtime,'auth.json'),0o600);await login.close();
 if(baseline){
  const original=new Map(), cp=require('child_process');
  for(const context of [staff,anon])await context.route('**/*',async route=>{
   const u=new URL(route.request().url());let file=decodeURIComponent(u.pathname).replace(/^\//,'')||'index.php';
   if(u.origin!==base||!file.match(/\.(html|js|css)$/)){await route.continue();return;}
   if(!original.has(file)){try{original.set(file,cp.execFileSync('git',['show','HEAD:public/'+file],{cwd:path.join(__dirname,'..'),stdio:['ignore','pipe','ignore']}));}catch{original.set(file,null);}}
   if(!original.get(file)){await route.continue();return;}
   await route.fulfill({status:200,contentType:file.endsWith('.html')?'text/html':file.endsWith('.css')?'text/css':'application/javascript',body:original.get(file)});
  });
 }
 const shapes=baseline&&fs.existsSync(path.join(runtime,'baseline.json'))?JSON.parse(fs.readFileSync(path.join(runtime,'baseline.json'))):{}, errors=[];let count=0;
 for(const file of pages){const publicPage=file==='index.php'||file.includes('register-restore')||file.endsWith('survey-user.php');const context=publicPage?anon:staff;const p=await context.newPage();p.on('pageerror',e=>errors.push(file+': '+e.message));
  for(const [size,viewport] of [['desktop',{width:1440,height:900}],['mobile',{width:390,height:844}]]){
   await p.setViewportSize(viewport);const response=await p.goto(url(file));if(response)assert.equal(response.status(),200,file+' response');await ready(p);assert.equal(new URL(p.url()).pathname,new URL(url(file)).pathname,file+' unexpected redirect');
   const name=file.replaceAll('/','_').replace('.html','')+'-'+size;
   const theme=baseline?'dark':'light';
   await p.evaluate(theme=>HospitalTheme.preview(theme),theme);await p.waitForTimeout(320);assert.equal(await p.locator('html').getAttribute('data-theme'),theme);
   shapes[name]=await geometry(p);
   await authFont(p);
   await p.screenshot({path:path.join(runtime,(baseline?'before-':'light-')+name+'.png')});
   if(!baseline){await p.evaluate(()=>HospitalTheme.preview('dark'));await p.waitForTimeout(320);const old=JSON.parse(fs.readFileSync(path.join(runtime,'baseline.json')))[name],now=await geometry(p);for(const selector of Object.keys(old)){if(!old[selector]||!now[selector])continue;for(const k of ['x','y','w','h'])assert(Math.abs(old[selector][k]-now[selector][k])<1.1,name+' '+selector+' '+k+' changed '+JSON.stringify({old:old[selector],now:now[selector]}));assert.equal(now[selector].blur,old[selector].blur,name+' glass blur');}
    if(size==='desktop')await p.screenshot({path:path.join(runtime,'dark-'+name+'.png')});
   }
   const toggle=p.locator('.sidebar-action');if(await toggle.count()){await toggle.click();await p.waitForTimeout(850);const expanded=await geometry(p);if(baseline)shapes[name+'-expanded']=expanded;else{const old=JSON.parse(fs.readFileSync(path.join(runtime,'baseline.json')))[name+'-expanded'];for(const s of ['.sidebar','.topbar'])if(old?.[s]&&expanded[s])for(const k of ['x','y','w','h'])assert(Math.abs(old[s][k]-expanded[s][k])<1.1,name+' expanded '+s+' '+k+' '+JSON.stringify({old:old[s],now:expanded[s]}));await p.evaluate(()=>HospitalTheme.preview('light'));await p.waitForTimeout(320);}if(size==='desktop')await p.screenshot({path:path.join(runtime,(baseline?'before-expanded-':'light-expanded-')+name+'.png')});}
   count++;
  }await p.close();
 }
 if(!baseline)await errorPages(browser,staff);
 if(baseline)fs.writeFileSync(path.join(runtime,'baseline.json'),JSON.stringify(shapes));
 assert.equal(errors.length,0,errors.join('\n'));console.log((baseline?'Baseline':'Light appearance and dark geometry')+': '+count+' page/viewports, no script errors.');await browser.close();
})();
