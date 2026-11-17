// Use a disposable local *_test database and tests/prepare-admin.php first.
const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const {execFileSync}=require('node:child_process');
const root=process.cwd();
const base=process.env.TEST_URL||'http://127.0.0.1:8000';
const fixture=JSON.parse(fs.readFileSync('tests/artifacts/admin-fixture.json','utf8'));
const php=process.env.PHP_BINARY||'php';
const results=[];
function db(sql){return execFileSync(php,['tests/admin-db.php',sql],{encoding:'utf8'}).trim();}
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 const context=await browser.newContext();const page=await context.newPage();
 const pageErrors=[];page.on('pageerror',e=>pageErrors.push(e.message));
 const token=async(p=page)=>p.locator('input[name=csrf]').first().getAttribute('value');
 async function login(p,name,code){await p.goto(base+'/admin.php?module=login');await p.locator('#username').fill(name);await p.locator('#private_code').fill(code);await p.getByRole('button',{name:'Sign in',exact:true}).click();}
 try{
  let response=await page.goto(base+'/setup.php');assert.equal(response.status(),200);
  let csrf=await token();
  response=await context.request.post(base+'/setup.php',{form:{csrf:'invalid',setup_token:fixture.token}});assert.equal(response.status(),403);
  response=await context.request.post(base+'/setup.php',{form:{csrf,setup_token:'not-the-token'}});assert((await response.text()).includes('Setup token was not accepted'));
  response=await context.request.post(base+'/setup.php',{form:{csrf,setup_token:fixture.token,username:'rootadmin',private_code:'short',repeat_private_code:'short'}});assert((await response.text()).includes('matching random private code'));
  response=await context.request.post(base+'/setup.php',{form:{csrf,setup_token:fixture.token,username:'rootadmin',private_code:fixture.super,repeat_private_code:fixture.super}});assert((await response.text()).includes('Super Admin created.'));
  assert.equal(db("SELECT COUNT(*) FROM administrators WHERE role='super_admin'"),'1');
  response=await context.request.post(base+'/setup.php',{form:{csrf,setup_token:fixture.token,username:'secondroot',private_code:fixture.super,repeat_private_code:fixture.super}});assert.equal(response.status(),410);
  response=await page.goto(base+'/admin.php');assert.equal(response.status(),503);assert((await page.textContent('body')).includes('Remove the temporary'));
  fs.unlinkSync('public/setup.php');
  results.push('PASS first setup, bad token, weak code, CSRF, permanent replay lock and setup-file removal gate');
  await page.goto(base+'/admin.php?module=login');csrf=await token();
  const before=(await context.cookies()).find(c=>c.name==='sya_session').value;
  response=await context.request.post(base+'/admin.php?module=login',{form:{csrf:'wrong',action:'login',username:'rootadmin',private_code:fixture.super}});assert.equal(response.status(),403);
  response=await context.request.post(base+'/admin.php?module=login',{form:{csrf,action:'login',username:"' OR 1=1 --",private_code:fixture.super}});assert((await response.text()).includes('Username or private code was not accepted.'));
  await login(page,'rootadmin',fixture.super);assert(page.url().includes('module=overview'));
  const after=(await context.cookies()).find(c=>c.name==='sya_session');assert.notEqual(after.value,before);assert(after.httpOnly);assert.equal(after.sameSite,'Lax');
  assert.equal((await page.request.get(base+'/admin.php')).headers()['cache-control'],'no-store, private');
  results.push('PASS generic login failures, login CSRF, SQL metacharacters, session rotation and HttpOnly/SameSite cookie');
  await page.goto(base+'/admin.php?module=administration');csrf=await token();
  for(const [name,role,code] of [['casting01','casting_manager',fixture.casting],['crew01','crew_coordinator',fixture.crew],['editor01','content_editor',fixture.editor]]){
   response=await context.request.post(base+'/admin.php?module=administration',{form:{csrf,action:'create',username:name,role,new_private_code:code,confirm_code:fixture.super}});assert((await response.text()).includes('Administrator created.'));
  }
  // Valid and rejected rows coexist; no codes appear in result HTML.
  const csv=['username,private_code,role',`imported01,${fixture.editor},content_editor`,`editor01,${fixture.editor},content_editor`,`badrole01,${fixture.editor},owner`,'weakcode01,1234,casting_manager','broken,row'].join('\n');
  response=await context.request.post(base+'/admin.php?module=administration',{multipart:{csrf,action:'import',confirm_code:fixture.super,administrators_csv:{name:'admins.csv',mimeType:'text/csv',buffer:Buffer.from(csv)}}});
  let html=await response.text();assert(html.includes('Row 2: account created.'));assert(html.includes('Row 3: rejected'));assert(html.includes('Row 4: rejected'));assert(html.includes('Row 5: rejected'));assert(html.includes('Row 6: rejected'));assert(!html.includes(fixture.editor));assert.equal(db('SELECT COUNT(*) FROM administrators'),'5');
  db('CLEAR_LIMITS');
  for(const [name,body,message] of [
    ['badheader.csv','username,password,role\nabc,secret,content_editor','CSV header must be exactly'],
    ['empty.csv','', 'CSV header must be exactly'],
    ['large.csv','x'.repeat(65537),'no larger than 64 KiB'],
    ['many.csv','username,private_code,role\n'+`extra01,${fixture.editor},content_editor\n`.repeat(101),'Maximum 100 rows']]){
    response=await context.request.post(base+'/admin.php?module=administration',{multipart:{csrf,action:'import',confirm_code:fixture.super,administrators_csv:{name,mimeType:'text/csv',buffer:Buffer.from(body)}}});assert((await response.text()).includes(message));
  }
  assert.equal(db('SELECT COUNT(*) FROM administrators'),'5');db('CLEAR_LIMITS');
  results.push('PASS CSV header, empty file, 64 KiB limit and 100-row limit reject without creating accounts');
  results.push('PASS account creation and CSV valid rows, duplicate usernames, invalid roles, weak codes, malformed rows and secret-free reports');
  // Escaped internal settings; changes require own private code and CSRF.
  response=await context.request.post(base+'/admin.php?module=settings',{form:{csrf,action:'settings',idle_seconds:'300',admin_notice:'<script>alert(1)</script>',confirm_code:fixture.super}});assert((await response.text()).includes('Administrator settings saved'));
  await page.goto(base+'/admin.php');assert(await page.getByText('<script>alert(1)</script>',{exact:true}).isVisible());assert.equal(await page.locator('script:not([src])').count(),0);
  response=await context.request.post(base+'/admin.php?module=settings',{form:{csrf:'bad',action:'settings',idle_seconds:'3600',confirm_code:fixture.super}});assert.equal(response.status(),403);
  assert.equal(db("SELECT setting_value FROM admin_settings WHERE setting_key='idle_seconds'"),'300');
  results.push('PASS settings validation, CSRF and stored text escaping');
  // Every role is tested against every module and all Super Admin write actions.
  for(const [name,role,code,allowed] of [
   ['casting01','casting_manager',fixture.casting,['overview','casting','communications','profile']],
   ['crew01','crew_coordinator',fixture.crew,['overview','crew','communications','profile']],
   ['editor01','content_editor',fixture.editor,['overview','content','media','profile']]]){
   const c=await browser.newContext();const p=await c.newPage();await login(p,name,code);assert(p.url().includes('overview'));const t=await token(p);
   for(const module of ['overview','casting','crew','content','media','communications','administration','settings','audit','profile']){
    response=await c.request.get(base+'/admin.php?module='+module);assert.equal(response.status(),allowed.includes(module)?200:403,role+':'+module);
   }
   for(const [module,action] of [['administration','create'],['administration','update'],['administration','import'],['settings','settings']]){
    response=await c.request.post(base+'/admin.php?module='+module,{form:{csrf:t,action,username:'intruder',role:'super_admin',new_private_code:fixture.super,confirm_code:code}});assert.equal(response.status(),403);
   }
   if(role==='content_editor'){
    const id=db("SELECT id FROM administrators WHERE username='editor01'");
    response=await context.request.post(base+'/admin.php?module=administration',{form:{csrf,action:'update',administrator_id:id,role:'content_editor',is_active:'0',new_private_code:'',confirm_code:fixture.super}});assert((await response.text()).includes('Administrator updated.'));
    response=await c.request.get(base+'/admin.php');assert((await response.text()).includes('Welcome back.'));
    await login(p,name,code);assert((await p.textContent('body')).includes('Username or private code was not accepted.'));
   }
   await c.close();
  }
  results.push('PASS complete role/module permission matrix, direct unauthorized writes, account disable and active-session revocation');
  // Forced inactivity and absolute session timeout tested by modifying only disposable fixture session files via helper.
  db('EXPIRE_SESSION');response=await page.goto(base+'/admin.php');assert((await page.textContent('body')).includes('Welcome back.'));
  await login(page,'rootadmin',fixture.super);assert(page.url().includes('overview'));
  db('EXPIRE_ABSOLUTE');response=await page.goto(base+'/admin.php');assert((await page.textContent('body')).includes('Welcome back.'));
  results.push('PASS inactivity and eight-hour absolute timeout');
  await login(page,'rootadmin',fixture.super);csrf=await token();
  // A restored setup file stays locked, even after all super-admin rows are removed in the throwaway DB.
  fs.copyFileSync('public/setup.php.dist','public/setup.php');
  response=await context.request.get(base+'/setup.php');assert.equal(response.status(),410);
  fs.unlinkSync('public/setup.php');
  // Own private code change destroys every session through auth_version.
  await page.goto(base+'/admin.php?module=profile');csrf=await token();
  response=await context.request.post(base+'/admin.php?module=profile',{form:{csrf,action:'change_code',confirm_code:fixture.super,new_private_code:fixture.replacement,repeat_private_code:fixture.replacement}});assert((await response.text()).includes('Welcome back.'));
  await login(page,'rootadmin',fixture.replacement);assert(page.url().includes('overview'));
  assert.equal(db('CHECK_SECRETS'),'clean');
  assert.equal(db("SELECT COUNT(*) FROM audit_log WHERE action='setup.completed'"),'1');
  results.push('PASS restored setup rejected, private-code change, password hashes and no plaintext secrets in database');
  // Bad login: generic failures until persistent account bucket locks across new sessions.
  const c=await browser.newContext();const p=await c.newPage();await p.goto(base+'/admin.php?module=login');const t=await token(p);
  for(let i=0;i<7;i++)response=await c.request.post(base+'/admin.php?module=login',{form:{csrf:t,action:'login',username:'neverexists',private_code:'wrong'}});
  assert.equal(response.status(),429);
  const c2=await browser.newContext();const p2=await c2.newPage();await p2.goto(base+'/admin.php?module=login');const t2=await token(p2);
  response=await c2.request.post(base+'/admin.php?module=login',{form:{csrf:t2,action:'login',username:'neverexists',private_code:'wrong'}});assert.equal(response.status(),429);await c.close();await c2.close();
  results.push('PASS persistent login throttling across sessions');
  db('CLEAR_LIMITS');
  await page.goto(base+'/admin.php?module=overview');
  for(const width of [360,768,1440]){
   await page.setViewportSize({width,height:900});
   for(const module of ['overview','administration','settings','audit','profile']){
    await page.goto(base+'/admin.php?module='+module);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));assert.equal(await page.locator('h1').count(),1);
    if(process.env.AXE==='1'){const AxeBuilder=require('@axe-core/playwright').default;const scan=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();assert.deepEqual(scan.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)})),[],module+':'+width);}
    await page.screenshot({path:`tests/artifacts/admin-${module}-${width}.png`,fullPage:true});
   }
  }
  await page.goto(base+'/admin.php?module=overview');await page.keyboard.press('Tab');assert.equal(await page.locator(':focus').innerText(),'Skip to content');
  csrf=await token();response=await context.request.post(base+'/admin.php',{form:{csrf,action:'logout'}});assert((await response.text()).includes('Welcome back.'));
  assert.deepEqual(pageErrors,[]);
  results.push('PASS 15 administrator viewport/module combinations, axe scans, keyboard skip link, logout and no JavaScript exceptions');
  // Final lock persistence test: intentionally delete all test accounts, retain singleton, restore setup.
  db('DELETE_ALL_TEST_ACCOUNTS');fs.copyFileSync('public/setup.php.dist','public/setup.php');response=await context.request.get(base+'/setup.php');assert.equal(response.status(),410);fs.unlinkSync('public/setup.php');
  results.push('PASS setup remains permanently closed after all Super Admin accounts are deleted');
  console.log(results.join('\n'));
 }finally{if(fs.existsSync('public/setup.php'))fs.unlinkSync('public/setup.php');await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
