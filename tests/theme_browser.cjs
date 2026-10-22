/* Focused appearance checks; requires the disposable environment prepared under tests/.runtime/theme. */
'use strict';
const fs=require('fs'), path=require('path'), assert=require('assert');
const runtime=process.env.HOSPITAL_THEME_TEST_DIR || path.join(__dirname,'.runtime/theme');
Object.assign(process.env,JSON.parse(fs.readFileSync(path.join(runtime,'env.json'))));
assert(process.env.HOSPITAL_DB_NAME.endsWith('_test'));
const {chromium}=require('playwright');
const records=JSON.parse(fs.readFileSync(path.join(runtime,'records.json')));
const base=process.env.HOSPITAL_AUTH_TEST_URL;
const pages=fs.readdirSync(path.join(__dirname,'../public'),{recursive:true}).filter(f=>/\.(html|php)$/.test(f)&&(!process.env.THEME_FILES||process.env.THEME_FILES.split(',').some(name=>f.endsWith(name)))).sort();
const queries={ 'documents-viewer.php':records.document.id,'documents-edit.php':records.document.id,'trace-view.php':records.transfer.id,'survey-view.php':records.survey.id,'survey-new.php':records.survey.id };
function url(file){const id=queries[path.basename(file)]; return base+'/'+file+(id?'?id='+id:'')+(file.endsWith('survey-user.php')?'#token='+records.token:'');}
async function ready(p){await p.evaluate(async()=>{await window.HospitalI18n?.ready;await window.HospitalAuth?.ready;if(window.HospitalAuth?.current)await HospitalAuth.refresh(true);}); await p.waitForLoadState('networkidle',{timeout:8000}).catch(()=>{}); await p.evaluate(()=>document.fonts.ready); await p.addStyleTag({content:'*,*::before,*::after{animation-delay:0s!important;animation-duration:0s!important;transition:none!important}'}); await p.waitForTimeout(140);}
async function geometry(p){return p.evaluate(()=>Object.fromEntries(['.tarjeta','.app-shell','.topbar','.sidebar','.hero-panel','.bg-panel','.patient-header','.patient-survey','.patient-layout'].map(s=>{const e=document.querySelector(s);if(!e)return [s,null];const b=e.getBoundingClientRect(),c=getComputedStyle(e);return [s,{x:b.x,y:b.y,w:b.width,h:b.height,blur:c.backdropFilter,radius:c.borderRadius}];})));}
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
   if(!baseline){await p.evaluate(()=>HospitalTheme.preview('light'));await p.waitForTimeout(320);assert.equal(await p.locator('html').getAttribute('data-theme'),'light');}
   shapes[name]=await geometry(p);
   await p.screenshot({path:path.join(runtime,(baseline?'before-':'light-')+name+'.png')});
   if(!baseline){await p.evaluate(()=>HospitalTheme.preview('dark'));await p.waitForTimeout(320);const old=JSON.parse(fs.readFileSync(path.join(runtime,'baseline.json')))[name],now=await geometry(p);for(const selector of Object.keys(old)){if(!old[selector]||!now[selector])continue;for(const k of ['x','y','w','h'])assert(Math.abs(old[selector][k]-now[selector][k])<1.1,name+' '+selector+' '+k+' changed '+JSON.stringify({old:old[selector],now:now[selector]}));assert.equal(now[selector].blur,old[selector].blur,name+' glass blur');}
    if(size==='desktop')await p.screenshot({path:path.join(runtime,'dark-'+name+'.png')});
   }
   const toggle=p.locator('.sidebar-action');if(await toggle.count()){await toggle.click();await p.waitForTimeout(850);const expanded=await geometry(p);if(baseline)shapes[name+'-expanded']=expanded;else{const old=JSON.parse(fs.readFileSync(path.join(runtime,'baseline.json')))[name+'-expanded'];for(const s of ['.sidebar','.topbar'])if(old?.[s]&&expanded[s])for(const k of ['x','y','w','h'])assert(Math.abs(old[s][k]-expanded[s][k])<1.1,name+' expanded '+s+' '+k+' '+JSON.stringify({old:old[s],now:expanded[s]}));await p.evaluate(()=>HospitalTheme.preview('light'));await p.waitForTimeout(320);}if(size==='desktop')await p.screenshot({path:path.join(runtime,(baseline?'before-expanded-':'light-expanded-')+name+'.png')});}
   count++;
  }await p.close();
 }
 if(baseline)fs.writeFileSync(path.join(runtime,'baseline.json'),JSON.stringify(shapes));
 assert.equal(errors.length,0,errors.join('\n'));console.log((baseline?'Baseline':'Light appearance and dark geometry')+': '+count+' page/viewports, no script errors.');await browser.close();
})();
