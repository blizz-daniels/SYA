// Two independently running PHP servers, same disposable *_test database.
const {request}=require('playwright');const fs=require('node:fs');const assert=require('node:assert/strict');
(async()=>{const fixture=JSON.parse(fs.readFileSync('tests/artifacts/admin-fixture.json','utf8'));const contexts=[];try{
 const requests=[];
 for(const [index,url]of ['http://127.0.0.1:8094/setup.php','http://127.0.0.1:8095/public/setup.php'].entries()){
  const c=await request.newContext();contexts.push(c);const page=await c.get(url);assert.equal(page.status(),200);const csrf=(await page.text()).match(/name="csrf" value="([a-f0-9]+)"/)[1];requests.push(()=>c.post(url,{form:{csrf,setup_token:fixture.token,username:'raceadmin'+index,private_code:fixture.super,repeat_private_code:fixture.super}}));
 }
 const responses=await Promise.all(requests.map(fn=>fn()));assert.deepEqual(responses.map(r=>r.status()).sort(),[200,410]);const bodies=await Promise.all(responses.map(r=>r.text()));assert.equal(bodies.filter(b=>b.includes('Super Admin created.')).length,1);
 console.log('PASS concurrent initial setup through two PHP processes: one account created, second request rejected with 410.');
 }finally{for(const c of contexts)await c.dispose();if(fs.existsSync('public/setup.php'))fs.unlinkSync('public/setup.php');}})().catch(e=>{console.error(e);process.exit(1)});
