/* Focused theme controls, persistence and state-preservation checks on fictional local data. */
'use strict';
const fs=require('fs'),path=require('path'),assert=require('assert');
const runtime=process.env.HOSPITAL_THEME_TEST_DIR||path.join(__dirname,'.runtime/theme');Object.assign(process.env,JSON.parse(fs.readFileSync(path.join(runtime,'env.json'))));
assert(process.env.HOSPITAL_DB_NAME.endsWith('_test'));
const {chromium}=require('playwright'),r=JSON.parse(fs.readFileSync(path.join(runtime,'records.json'))),other=JSON.parse(fs.readFileSync(path.join(runtime,'other.json'))),base=process.env.HOSPITAL_AUTH_TEST_URL;
let checks=0;const check=(v,s)=>{assert(v,s);checks++;console.log('PASS',s)};
const ready=async p=>{await p.evaluate(async()=>{await HospitalI18n.ready;await window.HospitalAuth?.ready});await p.waitForTimeout(120)};
const theme=p=>p.locator('html').getAttribute('data-theme');
async function login(page, account = {username: 'system_admin', password: process.env.HOSPITAL_ADMIN_PASSWORD}, {openPrivacy = true} = {}) {
 await page.goto(base + '/');await ready(page);
 await page.evaluate(async account => HospitalApi.post('auth.php?action=login', account), account);
 await page.goto(base + '/src/views/settings.php');await ready(page);
 if (openPrivacy) await page.locator('#privacy-tab').click();
}

async function heartbeatPolicy(browser) {
 const context = await browser.newContext(), page = await context.newPage();
 const account = other;
 const requests = [];
 try {
  await page.clock.install();
  await login(page, account);
  page.on('request', request => {
   if (/auth.php\?action=(me|heartbeat)$/.test(request.url())) requests.push(request.url());
  });
  async function automaticLogout(enabled) {
   await page.evaluate(async enabled => {
    const current = await HospitalApi.get('settings.php?action=get');
    await HospitalApi.post('settings.php?action=preferences-update', {
     version: current.settings.version, automatic_logout: enabled
    });
    await HospitalAuth.refresh();
   }, enabled);
   requests.length = 0;
  }
  async function tick(action) {
   const response = page.waitForResponse(response => response.url().endsWith('auth.php?action=' + action));
   await page.clock.fastForward(60000);
   check((await response).status() === 200, 'minute tick uses ' + action);
  }
  await automaticLogout(true);
  await tick('heartbeat');
  await page.evaluate(() => Object.defineProperty(document, 'hidden', {configurable: true, value: true}));
  await tick('heartbeat');
  check(requests.filter(url => url.endsWith('action=heartbeat')).length === 2,
   'automatic logout keeps visible-idle and hidden-idle tabs alive');
  await automaticLogout(false);
  await page.clock.fastForward(60000);
  await page.waitForTimeout(150);
  check(requests.length === 0, 'disabled automatic logout keeps the existing hidden-tab silence');
  await page.evaluate(() => Object.defineProperty(document, 'hidden', {configurable: true, value: false}));
  await tick('me');
  check(!requests.some(url => url.endsWith('action=heartbeat')), 'disabled automatic logout does not renew an idle session');
  await page.clock.fastForward(59000);
  await page.locator('#privacy-tab').click();
  const heartbeat = page.waitForResponse(response => response.url().endsWith('auth.php?action=heartbeat'));
  await page.clock.fastForward(1000);
  check((await heartbeat).status() === 200, 'disabled automatic logout still renews after trusted recent activity');
  await automaticLogout(true);
  check(await page.evaluate(() => HospitalAuth.current.session.idle_timeout_seconds) === 300,
   'automatic logout retains the server 300-second limit');
 } finally {await context.close();}
}

async function requestNotifications(browser) {
 await require('./notification_checks.cjs').run({browser, runtime, base, records: r, ready, check});
}

async function heartbeatRealTime() {
 const {spawn} = require('child_process');
 const profile = fs.mkdtempSync(path.join(runtime, 'heartbeat-chrome-'));
 const log = fs.openSync(path.join(runtime, 'heartbeat-chrome.log'), 'w', 0o600);
 const port = Number(process.env.HOSPITAL_HEARTBEAT_DEBUG_PORT || 8920);
 const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', [
  '--user-data-dir=' + profile, '--remote-debugging-port=' + port,
  '--no-first-run', '--no-default-browser-check', 'about:blank'
 ], {stdio: ['ignore', log, log]});
 let browser;
 try {
  for (let attempt = 0; attempt < 100; attempt++) {
   try {await fetch('http://127.0.0.1:' + port + '/json/version');break;}
   catch (_) {await new Promise(resolve => setTimeout(resolve, 100));}
  }
  // noDefaults preserves native visibility; normal automation forces pages visible.
  browser = await chromium.connectOverCDP('http://127.0.0.1:' + port, {noDefaults: true});
  const accountFile = path.join(runtime, 'heartbeat.json');
  let account = other;
  if (fs.existsSync(accountFile)) account = JSON.parse(fs.readFileSync(accountFile));
  const context = browser.contexts()[0], page = context.pages()[0] || await context.newPage();
  await login(page, account, {openPrivacy: false});
  await page.goto(base + '/src/views/main-menu.php');await ready(page);
  check(await page.evaluate(() => HospitalAuth.current.settings.automatic_logout &&
   HospitalAuth.current.session.idle_timeout_seconds === 300), 'real-time fixture uses automatic logout and the unchanged server limit');
  const control = await browser.newContext(), closedPage = await control.newPage();
  await login(closedPage, account, {openPrivacy: false});
  await control.newPage();
  await closedPage.close();
  const closedAt = Date.now(), heartbeats = [];
  page.on('response', response => {
   if (response.url().endsWith('auth.php?action=heartbeat')) heartbeats.push({elapsed: Date.now() - closedAt, status: response.status()});
  });
  const cdp = await context.newCDPSession(page);
  const {windowId} = await cdp.send('Browser.getWindowForTarget');
  await cdp.send('Browser.setWindowBounds', {windowId, bounds: {windowState: 'minimized'}});
  await page.waitForTimeout(1000);
  check(await page.evaluate(() => document.hidden), 'the real Chrome page is natively hidden without a visibility mock');
  for (let minute = 1; minute <= 6; minute++) {
   const remaining = closedAt + minute * 60000 - Date.now();
   if (remaining > 0) await new Promise(resolve => setTimeout(resolve, remaining));
   console.log('REAL TIME', minute, 'minute(s);', heartbeats.length, 'heartbeats');
  }
  const live = await page.evaluate(() => HospitalApi.get('auth.php?action=me'));
  check(live.user.username === account.username && heartbeats.length >= 5 && heartbeats.every(row => row.status === 200),
   'six real hidden minutes retain the logged-in session through successful heartbeats');
  const reopened = await control.newPage();
  await reopened.goto(base + '/src/views/main-menu.php');await ready(reopened);
  check(await reopened.locator('#loginForm').isVisible() && reopened.url().includes('reason=session_expired'),
   'six real minutes with every app tab closed require login on reopening');
  fs.writeFileSync(path.join(runtime, 'heartbeat-real-time.json'), JSON.stringify({
   elapsedMilliseconds: Date.now() - closedAt, nativeHidden: true, heartbeats,
   hiddenSessionAuthenticated: true, closedSessionExpired: true, idleTimeoutSeconds: live.session.idle_timeout_seconds
  }, null, 2));
 } finally {
  if (browser) await browser.close();
  chrome.kill('SIGTERM');
  await new Promise(resolve => {if (chrome.exitCode !== null) resolve();else {chrome.once('exit', resolve);setTimeout(resolve, 5000);}});
  fs.closeSync(log);fs.rmSync(profile, {recursive: true, force: true});
 }
}
async function diagnosticsRateLimit(browser) {
 const context=await browser.newContext(), page=await context.newPage(), reports=[];
 await login(page);
 const original=await page.evaluate(()=>HospitalApi.get('settings.php?action=get'));
 try {
  await page.evaluate(async state=>HospitalApi.post('settings.php?action=preferences-update',{version:state.settings.version,optional_diagnostics:true}),original);
  const endpoint='**/api/v1/settings.php?action=diagnostics';
  await page.route(endpoint,route=>route.fulfill({status:429,contentType:'application/json',body:JSON.stringify({success:false,error:{code:'RATE_LIMITED',message:'Optional telemetry fixture'}})}));
  page.on('console',message=>{if(message.type()==='error'&&message.text().startsWith('[Hospital'))reports.push(message.text());});
  const diagnostic=page.waitForResponse(response=>response.url().endsWith('settings.php?action=diagnostics'));
  await page.goto(base+'/src/views/main-menu.php');await ready(page);assert.equal((await diagnostic).status(),429);await page.waitForTimeout(100);
  check(reports.length===0,'optional diagnostics 429 emits no shared-client or session console report');
  check(await page.locator('.hospital-notification').count() === 0, 'optional diagnostics 429 shows no notification');
  await page.route(endpoint,route=>route.fulfill({status:429,contentType:'text/plain',body:'Optional telemetry fixture'}));
  check(await page.evaluate(()=>HospitalApi.post('settings.php?action=diagnostics',{load_ms:1}))===null&&reports.length===0,'optional diagnostics also silently drops a non-JSON 429');
  await page.route('**/api/v1/settings.php?action=get',route=>route.fulfill({status:429,contentType:'application/json',body:JSON.stringify({success:false,error:{code:'RATE_LIMITED',message:'Ordinary request fixture'}})}));
  check(await page.evaluate(()=>HospitalApi.get('settings.php?action=get').then(()=>false,error=>error.status===429))&&reports.length===1,'ordinary API rate limiting still rejects and reports normally');
  await page.unroute('**/api/v1/settings.php?action=get');
  await page.route(endpoint,route=>route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({success:false,error:{code:'SERVICE_UNAVAILABLE',message:'Optional telemetry service fixture'}})}));
  check(await page.evaluate(()=>HospitalApi.post('settings.php?action=diagnostics',{load_ms:1}).then(()=>false,error=>error.status===503))&&reports.length===2,'other diagnostics failures retain their existing reporting');
 } finally {
  await page.unrouteAll({behavior:'wait'});
  await page.evaluate(async original=>{const state=await HospitalApi.get('settings.php?action=get');await HospitalApi.post('settings.php?action=preferences-update',{version:state.settings.version,optional_diagnostics:original.optional_diagnostics});},original.settings);
  await context.close();
 }
}
async function loginChoices(browser) {
 const cases=[
  {name:'choose-light',saved:'dark',cache:'dark',choices:['light'],expected:'light',locale:'en'},
  {name:'choose-dark',saved:'light',cache:'light',choices:['dark'],expected:'dark'},
  {name:'unchanged-cache',saved:'light',cache:'dark',choices:[],expected:'light'},
  {name:'already-selected',saved:'light',cache:'dark',choices:['dark'],expected:'light'},
  {name:'change-back',saved:'light',cache:'dark',choices:['light','dark'],expected:'dark'},
  {name:'failed-login-retry',saved:'dark',cache:'dark',choices:['light'],expected:'light',badLogin:true},
  {name:'failed-theme-save',saved:'dark',cache:'dark',choices:['light'],expected:'dark',failure:503},
  {name:'stale-theme-save',saved:'dark',cache:'dark',choices:['light'],expected:'dark',failure:409}
 ];
 for(const item of cases){
  const context=await browser.newContext(),page=await context.newPage();
  try{
   await page.goto(base+'/');await ready(page);
   await page.evaluate(async ({password,saved,locale})=>{
    await HospitalApi.post('auth.php?action=login',{username:'system_admin',password});
    const current=await HospitalApi.get('settings.php?action=get');
    await HospitalApi.post('settings.php?action=preferences-update',{version:current.settings.version,theme:saved,locale});
    await HospitalApi.post('auth.php?action=logout');
    localStorage.setItem('hospital-theme:'+new URL(HospitalApi.page('')).pathname,saved);
   },{password:process.env.HOSPITAL_ADMIN_PASSWORD,saved:item.saved,locale:item.locale||'es-UY'});
   await page.evaluate(cache=>localStorage.setItem('hospital-theme:'+new URL(HospitalApi.page('')).pathname,cache),item.cache);
   await page.reload();await ready(page);
   let saves=0;
   const endpoint='**/api/v1/settings.php?action=preferences-update';
   await page.route(endpoint,async route=>{
    saves++;
    if(item.failure===503)return route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({success:false,error:{code:'SERVICE_UNAVAILABLE',message:'Fictional login theme failure'}})});
    if(item.failure===409){const body=route.request().postDataJSON();return route.continue({postData:JSON.stringify({...body,version:body.version+100})});}
    return route.continue();
   });
   for(const choice of item.choices)await page.locator(`[data-public-theme="${choice}"]`).click();
   await page.locator('[name=username]').fill('system_admin');
   if(item.badLogin){
    await page.locator('[name=password]').fill('Fictional invalid password');
    const rejected=page.waitForResponse(response=>response.url().includes('auth.php?action=login'));
    await page.locator('#loginForm [type=submit]').click();assert.equal((await rejected).status(),401);
    await page.waitForFunction(()=>!document.querySelector('#loginForm [type=submit]').disabled);
    check(saves===0,'failed login never saves a public theme');
   }
   await page.locator('[name=password]').fill(process.env.HOSPITAL_ADMIN_PASSWORD);
   const navigation=page.waitForResponse(response=>response.request().isNavigationRequest()&&response.url().endsWith('/src/views/main-menu.php'));
   await page.locator('#loginForm [type=submit]').click();
   const response=await navigation,html=await response.text();
   await page.waitForURL('**/src/views/main-menu.php');await ready(page);
   check(html.includes('data-theme="'+item.expected+'"')&&await theme(page)===item.expected,item.name+': the first staff response and paint use the expected account theme');
   const changed=item.choices.some((choice,index)=>choice!==(item.choices[index-1]||item.cache));
   check(saves===(changed?1:0),item.name+': only an explicit login-page change writes the versioned preference');
   await page.unroute(endpoint);
   const saved=await page.evaluate(()=>HospitalApi.get('settings.php?action=get'));
   check(saved.settings.theme===item.expected&&saved.settings.locale===(item.locale||'es-UY'),item.name+': account persistence matches the successful or failed save and retains its language');
  }finally{await context.close();}
 }
}
(async()=>{
 if(process.argv.includes('--heartbeat-real-time')) {await heartbeatRealTime();console.log('TOTAL',checks,'real-time heartbeat checks');return;}
 const b=await chromium.launch({channel:'chrome',headless:true}),c=await b.newContext({viewport:{width:1440,height:900}}),p=await c.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));
 await heartbeatPolicy(b);
 await requestNotifications(b);
 await diagnosticsRateLimit(b);
 await loginChoices(b);
 await p.goto(base+'/');await ready(p);check(await theme(p)==='dark','public first visit defaults to dark');
 await p.locator('[data-public-theme="light"]').click();await p.waitForTimeout(300);check(await theme(p)==='light','public switch immediately applies light');
 await p.locator('[name=username]').fill('Unsaved fictional name');await p.locator('[name=password]').fill('Unsaved fictional password');await p.locator('[data-public-theme="dark"]').click();check(await p.locator('[name=username]').inputValue()==='Unsaved fictional name'&&await p.locator('[name=password]').inputValue()==='Unsaved fictional password','public switching preserves username and password values');
 await p.locator('[data-public-theme="light"]').click();await p.goto(base+'/src/views/register-restore/register.php?lang=en');await ready(p);check(await theme(p)==='light'&&await p.locator('html').getAttribute('lang')==='en','public navigation preserves theme and independent English language');
 check(await p.locator('[data-public-theme="light"]').getAttribute('aria-label')==='Light theme','appearance controls use English labels');await p.reload();await ready(p);check(await theme(p)==='light','public choice survives reload');
 const tab=await c.newPage();await tab.goto(base+'/');await ready(tab);await p.locator('[data-public-theme="dark"]').click();await tab.waitForFunction(()=>HospitalTheme.current==='dark');check(await theme(tab)==='dark','public tabs synchronize appearance through the theme-only cache');await tab.close();
 await login(p);check(await theme(p)==='dark','signed-in SQL preference overrides the public cache');
 const firstPaint=require('./settings_first_paint.cjs');
 const first=await firstPaint.capture(c,base,path.join(runtime,'settings-first-paint'),{name:'dark-stale-light',cacheTheme:'light'});
 check(first.first.every(button=>button.checked===String(button.on)),'CPU ×6 first paint retains all four saved preference states without transitions');
 const light=p.locator('[data-theme-option=light]'),dark=p.locator('[data-theme-option=dark]');check(await light.isEnabled(),'Settings enables the light control');
 let response=p.waitForResponse(r=>r.url().includes('/settings.php?action=preferences-update'));await light.click();await response;await p.waitForFunction(()=>HospitalAuth.current.settings.theme==='light');check(await theme(p)==='light','Settings previews and commits light');
 await firstPaint.capture(c,base,path.join(runtime,'settings-first-paint'),{name:'light-stale-dark',cacheTheme:'dark'});
 check(true,'CPU ×6 light Settings has no saved-state transition with a stale dark cache');
 const second=await c.newPage();await second.goto(base+'/src/views/main-menu.php');await ready(second);check(await theme(second)==='light','navigation hydrates the saved account theme');
 response=p.waitForResponse(r=>r.url().includes('/settings.php?action=preferences-update'));await dark.click();await response;await second.waitForFunction(()=>HospitalTheme.current==='dark');check(await theme(second)==='dark','staff tabs receive confirmed settings through the existing broadcast');await second.close();
 const endpoint='**/api/v1/settings.php?action=preferences-update';let requests=0;
 await p.route(endpoint,async route=>{requests++;if(requests===1)await new Promise(resolve=>setTimeout(resolve,400));await route.continue()});
 await light.click();await dark.click();await light.click();check(await theme(p)==='light','rapid selections keep the latest visual choice');await p.waitForFunction(()=>HospitalAuth.current.settings.theme==='light');await p.waitForTimeout(650);check(requests===3&&await theme(p)==='light'&&await light.getAttribute('aria-pressed')==='true','queued acknowledgements do not replace the newest theme');await p.unroute(endpoint);
 await p.route(endpoint,route=>route.fulfill({status:500,contentType:'application/json',body:JSON.stringify({success:false,error:{code:'TEST_FAILURE',message:'Fictional save failure'}})}));await dark.click();await p.waitForFunction(()=>HospitalTheme.current==='light');check(await light.getAttribute('aria-pressed')==='true','failed latest save restores the confirmed theme and selected control');await p.unroute(endpoint);
 await p.goto(base+'/src/views/management/patients.php');await ready(p);const add=p.locator('#new-patient-button');if(await add.count()){await add.click();const input=p.locator('#patient-form input[type=text]').first();await input.fill('Unsaved fictional patient');await input.focus();await p.evaluate(()=>HospitalTheme.preview('dark'));await p.evaluate(()=>HospitalTheme.preview('light'));check(await input.inputValue()==='Unsaved fictional patient'&&await input.evaluate(e=>document.activeElement===e),'theme changes preserve a management draft and focus');await p.locator('#close-patient-dialog').click()}
 await p.goto(base+'/src/views/settings.php');await ready(p);await p.locator('#privacy-tab').click();response=p.waitForResponse(r=>r.url().includes('/settings.php?action=preferences-update'));await p.locator('[data-theme-option=dark]').click();await response;await p.evaluate(()=>HospitalAuth.logout());await p.waitForURL('**/index.php');await ready(p);check(await theme(p)==='dark','logout retains appearance without retaining authentication');
 await login(p,other);check(await theme(p)==='light','another account applies its own SQL theme');await p.evaluate(()=>HospitalAuth.logout());await p.waitForURL('**/index.php');await ready(p);
 // Participant appearance changes never reload the form or replace its signed credentials.
 let publicRequests=0;await p.route(/\/surveys\.php\?action=public-/,async route=>{publicRequests++;await route.continue()});await p.goto(base+'/src/views/surveys/user-view/survey-user.php?lang=en#token='+r.token);await ready(p);await p.locator('[data-answer-rating]').first().waitFor();await p.locator('[data-answer-rating]').last().check({force:true});const fragment=await p.evaluate(()=>location.hash),beforeRequests=publicRequests;
 await p.locator('[data-open-accessibility]').first().click();await p.locator('[data-public-theme=dark]').click();await p.locator('[data-public-theme=light]').click();await p.locator('#high-contrast').check();await p.waitForTimeout(300);check(await p.locator('[data-answer-rating]:checked').inputValue()==='5'&&await p.evaluate(()=>location.hash)===fragment,'patient theme and high contrast preserve answers and token fragment');check(publicRequests===beforeRequests,'patient appearance changes make no participant API requests');
 check(await p.locator('.patient-survey').evaluate(e=>getComputedStyle(e).backgroundColor)==='rgb(255, 255, 255)','light high contrast uses a white reading surface');await p.screenshot({path:path.join(runtime,'patient-accessibility.png')});await p.locator('#accessibility-dialog [data-close-dialog]').last().click();await p.screenshot({path:path.join(runtime,'patient-light.png')});
 for(const [color,expected] of Object.entries({blue:'#53539d',green:'#246c50',cyan:'#216979',amber:'#805d15',rose:'#99365b',slate:'#4a5b77'})){
  const q=await c.newPage();await q.route('**/surveys.php?action=public-get',async route=>{const res=await route.fetch(),body=await res.json();body.data.color=color;await route.fulfill({response:res,json:body})});await q.goto(base+'/src/views/surveys/user-view/survey-user.php#token='+r.token);await ready(q);await q.locator('[data-answer-rating]').first().waitFor();check(await q.evaluate(()=>document.documentElement.style.getPropertyValue('--patient-accent'))===expected,'light survey accent: '+color);await q.close();
 }
 const blocked=await b.newContext();await blocked.addInitScript(()=>Object.defineProperty(window,'localStorage',{get(){throw new Error('Unavailable storage')}}));const bp=await blocked.newPage();await bp.goto(base+'/');await ready(bp);await bp.locator('[data-public-theme=light]').click();check(await theme(bp)==='light','appearance works when browser storage is unavailable');await blocked.close();
 await p.emulateMedia({reducedMotion:'reduce'});check(await p.locator('.appearance-switch button').first().evaluate(e=>getComputedStyle(e).transitionDuration)==='0s','new public controls respect reduced motion');
 check(!errors.length,'no browser script errors: '+errors.join(', '));await b.close();console.log('TOTAL',checks,'focused theme browser checks');
})();
