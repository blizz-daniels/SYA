const {chromium}=require('playwright');const assert=require('node:assert/strict');const fs=require('node:fs');const {execFileSync}=require('node:child_process');
const base=process.env.TEST_URL||'http://127.0.0.1:8097';const fixture=JSON.parse(fs.readFileSync('tests/artifacts/recruitment-fixture.json'));
const php=process.env.PHP_BINARY||'php';
function db(sql){const result=execFileSync(php,['tests/recruitment-db.php',sql],{encoding:'utf8'});return sql.startsWith('SELECT ')?JSON.parse(result):result;}
const out=[];
(async()=>{const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',headless:true,args:['--no-sandbox']});const contexts=[];
try{
 async function login(name,code){const context=await browser.newContext();contexts.push(context);const page=await context.newPage();await page.goto(base+'/admin.php?module=login');await page.locator('#username').fill(name);await page.locator('#private_code').fill(code);await page.getByRole('button',{name:'Sign in',exact:true}).click();assert(page.url().includes('overview'));return{context,page};}
 const admin=await login('superadmin',fixture.super);const cast=await login('castadmin',fixture.cast);const crew=await login('crewadmin',fixture.crew);const editor=await login('editoradmin',fixture.editor);
 const token=async page=>page.locator('input[name=csrf]').first().getAttribute('value');
 async function adminPost(actor,kind,data){await actor.page.goto(base+'/admin.php?module=overview');return actor.context.request.post(base+'/admin.php?module='+kind,{form:{csrf:await token(actor.page),...data}});}
 const fields={'questions[qmotivation][id]':'qmotivation','questions[qmotivation][label]':'Why this production?','questions[qmotivation][type]':'textarea','questions[qmotivation][required]':'1','questions[qmotivation][order]':'10','questions[qchoice][id]':'qchoice','questions[qchoice][label]':'Experience level','questions[qchoice][type]':'select','questions[qchoice][options]':'Developing\nExperienced','questions[qchoice][required]':'1','questions[qchoice][order]':'20','questions[qfile][id]':'qfile','questions[qfile][label]':'Headshot','questions[qfile][type]':'file','questions[qfile][order]':'30'};
 const draft={action:'role_save',role_id:'0',title:'TEST Acting Role',location:'Test City',availability:'Test availability',description:'Approved fixture description only.',mandatory_requirements:'Adults, available for the test dates.',preferences:'Experience preferred, not mandatory.',age_min:'18',age_max:'60',deadline:'2030-12-31T12:00',...fields};
 let response=await adminPost(cast,'casting',draft);assert((await response.text()).includes('Draft saved as role'));
 const role=db("SELECT * FROM recruitment_roles WHERE kind='casting'")[0];
 response=await crew.context.request.get(base+'/admin.php?module=crew&role='+role.id);assert.equal(response.status(),403);assert(!(await response.text()).includes('TEST Acting Role'));
 response=await adminPost(crew,'crew',{...draft,title:'TEST Crew Role'});assert((await response.text()).includes('Draft saved as role'));
 const crewRole=db("SELECT * FROM recruitment_roles WHERE kind='crew'")[0];
 response=await adminPost(editor,'casting',draft);assert.equal(response.status(),403);
 response=await adminPost(crew,'crew',{action:'role_publish',role_id:String(role.id)});assert.equal(response.status(),403);
 response=await adminPost(cast,'casting',{action:'role_publish',role_id:String(role.id)});assert((await response.text()).includes('Published a new immutable form version'));
 response=await adminPost(crew,'crew',{action:'role_publish',role_id:String(crewRole.id)});assert((await response.text()).includes('Published a new immutable form version'));
 out.push('PASS department-scoped role creation/publication and denied editor/cross-department operations');
 response=await adminPost(cast,'casting',{action:'template_save',role_id:String(role.id),template_name:'Casting base'});assert((await response.text()).includes('Reusable department template saved'));
 const template=db('SELECT id FROM recruitment_form_templates')[0];
 response=await adminPost(crew,'crew',{action:'template_apply',role_id:String(crewRole.id),template_id:String(template.id)});assert((await response.text()).includes('Template not found'));
 const applicant=await browser.newContext();contexts.push(applicant);const page=await applicant.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));page.on('console',e=>{if(e.type()==='error')errors.push(e.text());});cast.page.on('pageerror',e=>errors.push(e.message));cast.page.on('console',e=>{if(e.type()==='error')errors.push(e.text());});
 await page.goto(base+'/index.php?page=casting');assert((await page.textContent('body')).includes('TEST Acting Role'));
 await page.goto(base+'/recruitment.php?action=apply&role='+role.id);let csrf=await token(page);
 const form={csrf,form_version:'1',full_name:'TEST Applicant',email:'actor@example.test',age:'24',location:'Test City',availability:'Available',eligibility:'1',privacy_ack:'1',qmotivation:'A thoughtful application.',qchoice:'Experienced'};
 response=await applicant.request.post(base+'/recruitment.php?action=apply&role='+role.id,{form:{...form,csrf:'bad'}});assert.equal(response.status(),403);
 response=await applicant.request.post(base+'/recruitment.php?action=apply&role='+role.id,{form:{...form,age:'17'}});assert((await response.text()).includes('adults aged 18'));
 response=await applicant.request.post(base+'/recruitment.php?action=apply&role='+role.id,{form:{...form,qchoice:'forged'}});assert((await response.text()).includes('Choose an available option'));
 const fake={name:'headshot.jpg',mimeType:'image/jpeg',buffer:Buffer.from('<?php echo "not-an-image"; ?>')};
 response=await applicant.request.post(base+'/recruitment.php?action=apply&role='+role.id,{multipart:{...form,qfile:fake}});assert((await response.text()).includes('Attachments must contain'));
 const oversized={name:'large.png',mimeType:'image/png',buffer:Buffer.alloc(2097153,65)};
 response=await applicant.request.post(base+'/recruitment.php?action=apply&role='+role.id,{multipart:{...form,qfile:oversized}});assert((await response.text()).includes('no larger than 2 MiB'));
 db('limits');
 const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jTioAAAAASUVORK5CYII=','base64');
 response=await applicant.request.post(base+'/recruitment.php?action=apply&role='+role.id,{multipart:{...form,qfile:{name:'private.php',mimeType:'application/x-php',buffer:png}}});assert((await response.text()).includes('We have received your application'));
 let app=db('SELECT * FROM recruitment_applications ORDER BY id')[0];assert.match(app.reference,/^SYA-[A-F0-9]{32}$/);assert.equal(app.form_version,1);assert.equal(app.notice_version,'test-v1');assert(app.retention_due_at);assert(!JSON.stringify(app).includes('marketing'));
 const attachment=db('SELECT * FROM recruitment_attachments')[0];assert.match(attachment.storage_key,/^[a-f0-9]{64}\.bin$/);assert.equal(attachment.mime,'image/png');
 response=await applicant.request.get(base+'/attachment.php?id='+attachment.id);assert.equal(response.status(),403);
 response=await crew.context.request.get(base+'/attachment.php?id='+attachment.id);assert.equal(response.status(),403);
 response=await cast.context.request.get(base+'/attachment.php?id='+attachment.id);assert.equal(response.status(),200);assert(response.headers()['content-disposition'].startsWith('attachment'));assert.equal(response.headers()['x-content-type-options'],'nosniff');assert.deepEqual(await response.body(),png);
 out.push('PASS public role listing, CSRF, adult eligibility, option validation, MIME/size checks, randomized private storage and authorized-only attachment downloads');
 response=await applicant.request.post(base+'/recruitment.php?action=apply&role='+role.id,{form});assert((await response.text()).includes('We have received your application'));
 assert.equal(db('SELECT COUNT(*) AS n FROM recruitment_applications')[0].n,2);assert(db('SELECT duplicate_flag FROM recruitment_applications').every(a=>a.duplicate_flag===1));
 // Publish v2 and reject stale submissions; v1 schema and role snapshot remain unchanged.
 response=await adminPost(cast,'casting',{...draft,role_id:String(role.id),title:'TEST Acting Role Revised','questions[qmotivation][label]':'Revised motivation question'});assert((await response.text()).includes('Draft saved'));
 response=await adminPost(cast,'casting',{action:'template_apply',role_id:String(role.id),template_id:String(template.id)});assert((await response.text()).includes('Template copied'));
 response=await adminPost(cast,'casting',{action:'role_publish',role_id:String(role.id)});assert((await response.text()).includes('Published a new immutable'));
 response=await applicant.request.post(base+'/recruitment.php?action=apply&role='+role.id,{form});assert.equal(response.status(),409);
 const versions=db('SELECT version,schema_json,role_snapshot FROM recruitment_form_versions WHERE role_id='+role.id+' ORDER BY version');assert.equal(versions.length,2);assert.equal(JSON.parse(versions[0].role_snapshot).title,'TEST Acting Role');assert.equal(db('SELECT form_version FROM recruitment_applications LIMIT 1')[0].form_version,1);
 out.push('PASS reusable templates, duplicate review flags without rejection, immutable form/role snapshots and stale-form rejection');
 response=await adminPost(crew,'crew',{action:'application_update',application_id:String(app.id),public_status:'Shortlisted',outcome:'pending',private_note:'cross department'});assert.equal(response.status(),403);
 response=await adminPost(cast,'casting',{action:'application_update',application_id:String(app.id),public_status:'Shortlisted',outcome:'selected',private_note:'forged selection'});assert.equal(response.status(),403);
 response=await adminPost(cast,'casting',{action:'application_update',application_id:String(app.id),public_status:'Shortlisted',outcome:'pending',private_note:'PRIVATE NOTE MUST NEVER APPEAR IN TRACKING'});assert((await response.text()).includes('Application updated'));
 // Unknown and correct reference/email return the same generic message; only matching recipient receives a code.
 await page.goto(base+'/recruitment.php?action=track');csrf=await token(page);
 const requestCode=async(reference,email)=>applicant.request.post(base+'/recruitment.php?action=track',{form:{csrf,stage:'request',reference,email}});
 response=await requestCode(app.reference,'wrong@example.test');assert((await response.text()).includes('If the reference and email match'));
 response=await requestCode(app.reference,app.email);assert((await response.text()).includes('If the reference and email match'));
 function code(){const messages=fs.readdirSync('/tmp/sya-m3-mail').map(f=>({file:f,...JSON.parse(fs.readFileSync('/tmp/sya-m3-mail/'+f))})).filter(m=>m.email===app.email&&m.purpose==='tracking');assert(messages.length);return messages.map(m=>m.text.match(/code is (\d{6})/)[1]).find(c=>{const active=db('SELECT token,code_hash FROM recruitment_tracking_challenges WHERE used_at IS NULL ORDER BY expires_at DESC LIMIT 1')[0];return active&&require('node:crypto').createHmac('sha256',getKey()).update(active.token+':'+c).digest('hex')===active.code_hash;});}
 function getKey(){return execFileSync(php,['-r','require "app/bootstrap.php"; echo $config["security"]["app_key"];'],{encoding:'utf8'});}
 let verification=code();assert(verification);
 response=await applicant.request.post(base+'/recruitment.php?action=track',{form:{csrf,stage:'verify',verification_code:'000000'}});assert((await response.text()).includes('Verification could not be completed'));
 response=await applicant.request.post(base+'/recruitment.php?action=track',{form:{csrf,stage:'verify',verification_code:verification}});let html=await response.text();assert(html.includes('Shortlisted'));assert(html.includes('TEST Acting Role'));assert(!html.includes('TEST Acting Role Revised'));assert(!html.includes('PRIVATE NOTE'));assert(!html.includes('actor@example.test'));assert(!html.includes('Headshot'));assert(!html.includes('A thoughtful application.'));
 response=await applicant.request.get(base+'/recruitment.php?action=status&id=999');assert((await response.text()).includes(app.reference));
 await page.goto(base+'/recruitment.php?action=status');csrf=await token(page);response=await applicant.request.post(base+'/recruitment.php?action=status',{form:{csrf,stage:'end'}});assert(!(await response.text()).includes('<h2>Shortlisted</h2>'));
 response=await applicant.request.post(base+'/recruitment.php?action=track',{form:{csrf,stage:'verify',verification_code:verification}});assert((await response.text()).includes('Verification could not be completed'));
 db('limits');await requestCode(app.reference,app.email);verification=code();db('expire');response=await applicant.request.post(base+'/recruitment.php?action=track',{form:{csrf,stage:'verify',verification_code:verification}});assert((await response.text()).includes('Verification could not be completed'));
 db('limits');await requestCode(app.reference,app.email);let lockedCode=code();
 for(let attempt=0;attempt<5;attempt++)await applicant.request.post(base+'/recruitment.php?action=track',{form:{csrf,stage:'verify',verification_code:'000000'}});
 response=await applicant.request.post(base+'/recruitment.php?action=track',{form:{csrf,stage:'verify',verification_code:lockedCode}});assert((await response.text()).includes('Verification could not be completed'));
 db('limits');for(let attempt=0;attempt<4;attempt++)response=await requestCode('SYA-'+('F'.repeat(32)),'unknown@example.test');assert.equal(response.status(),429);db('limits');
 db('limits');await requestCode(app.reference,app.email);verification=code();response=await applicant.request.post(base+'/recruitment.php?action=track',{form:{csrf,stage:'verify',verification_code:verification}});assert((await response.text()).includes('Shortlisted'));db('expire_grant');response=await applicant.request.get(base+'/recruitment.php?action=status');assert(!(await response.text()).includes('<h2>Shortlisted</h2>'));db('limits');
 out.push('PASS tracking access expires independently of its one-time verification code');
 out.push('PASS persistent five-code-attempt lock and tracking request throttling');
 out.push('PASS email/reference verification, generic requests, invalid/expired/replayed code rejection, restricted status and no private-data leaks');
 db('limits');db('disable');response=await applicant.request.get(base+'/recruitment.php?action=apply&role='+role.id);assert.equal(response.status(),410);db('enable');db('privacy_off');response=await applicant.request.get(base+'/recruitment.php?action=apply&role='+role.id);assert.equal(response.status(),410);db('privacy_on');db('storage_off');response=await applicant.request.get(base+'/recruitment.php?action=apply&role='+role.id);assert.equal(response.status(),410);db('storage_on');db('mail_off');response=await applicant.request.get(base+'/recruitment.php?action=apply&role='+role.id);assert.equal(response.status(),410);db('mail_on');
 out.push('PASS fail-closed global recruitment, privacy, private-storage and email readiness gates');
 response=await adminPost(admin,'casting',{action:'application_update',application_id:String(app.id),public_status:'Closed',outcome:'selected',private_note:'PRIVATE FINAL SELECTION'});assert((await response.text()).includes('Application updated'));assert.equal(db('SELECT retention_due_at,outcome FROM recruitment_applications WHERE id='+app.id)[0].retention_due_at,null);
 response=await adminPost(cast,'casting',{action:'application_update',application_id:String(app.id),public_status:'Received',outcome:'pending',private_note:'cannot overwrite decision'});assert.equal(response.status(),403);assert.equal(db('SELECT outcome FROM recruitment_applications WHERE id='+app.id)[0].outcome,'selected');
 response=await applicant.request.get(base+'/index.php?page=characters');assert(!(await response.text()).includes('TEST Applicant'));
 out.push('PASS Super Admin final selection, selected-record retention separation, protected decisions and no automatic actor reveal');
 // Review and application pages across mobile and desktop, with axe.
 for(const width of [360,768,1440]){
  await cast.page.setViewportSize({width,height:900});await page.setViewportSize({width,height:900});
  for(const url of [base+'/admin.php?module=casting',base+'/admin.php?module=casting&role='+role.id,base+'/admin.php?module=casting&role='+role.id+'&preview=1',base+'/admin.php?module=casting&application='+app.id]){
   await cast.page.goto(url);assert(await cast.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));if(process.env.AXE==='1'){const AxeBuilder=require('@axe-core/playwright').default;const scan=await new AxeBuilder({page:cast.page}).withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();assert.deepEqual(scan.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)})),[],url);}
  }
  for(const action of ['list','apply&role='+role.id,'track']){
   await page.goto(base+'/recruitment.php?action='+action);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));if(process.env.AXE==='1'){const AxeBuilder=require('@axe-core/playwright').default;const scan=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();assert.deepEqual(scan.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)})),[],action);}
  }
  await page.screenshot({path:'tests/artifacts/recruitment-track-'+width+'.png',fullPage:true});await cast.page.screenshot({path:'tests/artifacts/recruitment-review-'+width+'.png',fullPage:true});
 }
 out.push('PASS 21 recruitment page/viewport checks and axe scans');
 response=await adminPost(cast,'casting',{action:'role_close',role_id:String(role.id)});assert((await response.text()).includes('Role closed'));assert(db("SELECT retention_due_at FROM recruitment_applications WHERE outcome<>'selected'").every(a=>a.retention_due_at));response=await applicant.request.get(base+'/recruitment.php?action=apply&role='+role.id);assert.equal(response.status(),410);
 db('deadline_past');response=await applicant.request.get(base+'/recruitment.php?action=apply&role='+crewRole.id);assert.equal(response.status(),410);
 assert.deepEqual(errors,[]);out.push('PASS role closure, deadline enforcement, retention review dates and no applicant JavaScript exceptions');
 console.log(out.join('\n'));
}finally{for(const c of contexts)await c.close();await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
