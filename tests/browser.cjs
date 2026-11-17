/* Development-only: npm install --no-save --prefix /tmp/sya-test playwright @axe-core/playwright */
const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 const base=process.env.TEST_URL||'http://127.0.0.1:8000';
 const errors=[];const results=[];
 fs.mkdirSync('tests/artifacts',{recursive:true});
 for(const width of [320,360,768,1440]){
  const context=await browser.newContext({viewport:{width,height:900},reducedMotion:'reduce'});
  const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));page.on('console',e=>{if(e.type()==='error')errors.push(e.text())});
  for(const route of ['home','about','characters','casting','crew','updates','media','contact']){
   const response=await page.goto(`${base}/index.php?page=${route}`);assert.equal(response.status(),200);
   assert.equal(await page.locator('h1').count(),1);assert.equal(await page.locator('main').count(),1);
   assert.equal(await page.locator('nav [aria-current="page"]').count(),1);
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   assert.equal(await page.locator('form').count(),0);
   if(route==='home'){assert(await page.locator('h1').evaluate(e=>e.scrollWidth<=e.parentElement.clientWidth));assert.equal(await page.locator('.hero-content').evaluate(e=>getComputedStyle(e).animationName),'none');}
   assert(await page.locator('body').innerText().then(t=>!(/Warning:|Fatal error:|Notice:/.test(t))));
   for(const img of await page.locator('img').all()){await img.scrollIntoViewIfNeeded();await img.evaluate(i=>i.decode());assert(await img.evaluate(i=>i.complete&&i.naturalWidth>0));assert(await img.getAttribute('alt'))}
   await page.screenshot({path:`tests/artifacts/${route}-${width}.png`,fullPage:true});
   if(process.env.AXE==='1'){
    const AxeBuilder=require('@axe-core/playwright').default;
    const scan=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();
    assert.deepEqual(scan.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)})),[]);
   }
   results.push(`${route} ${width}px: PASS`);
  }
  await page.goto(base);await page.keyboard.press('Tab');assert.equal(await page.locator(':focus').innerText(),'Skip to content');await page.keyboard.press('Enter');assert.equal(await page.evaluate(()=>location.hash),'#main');
  if(width<1100){const menu=page.getByRole('button',{name:'Menu'});await menu.focus();await page.keyboard.press('Enter');assert.equal(await menu.getAttribute('aria-expanded'),'true');await page.keyboard.press('Tab');assert.equal(await page.locator(':focus').innerText(),'Home');await page.keyboard.press('Escape');assert.equal(await menu.getAttribute('aria-expanded'),'false');assert(await menu.evaluate(e=>e===document.activeElement));await menu.click();await page.getByRole('navigation').getByRole('link',{name:'About the Film',exact:true}).click();assert(page.url().includes('page=about'));}
  await context.close();
 }
 const context=await browser.newContext({javaScriptEnabled:false,viewport:{width:360,height:800}});const page=await context.newPage();await page.goto(base);assert(await page.getByRole('navigation').getByText('Characters',{exact:true}).isVisible());await context.close();
 const request=await browser.newContext();
 for(const path of ['/config/config.php','/config/config.example.php','/app/bootstrap.php','/database/migrations/001_foundation.sql','/.git/config','/bin/migrate.php']){assert.equal((await request.request.get(base+path)).status(),404);}
 for(const suffix of ['?page=missing','?page[]=home','?page=%3Cscript%3Ealert(1)%3C/script%3E']){const response=await request.request.get(base+'/index.php'+suffix);assert.equal(response.status(),404);assert(!(await response.text()).includes('<script>alert(1)</script>'));}
 const post=await request.request.post(base+'/index.php');assert.equal(post.status(),405);
 const home=await request.request.get(base);assert(home.headers()['content-security-policy'].includes("frame-ancestors 'none'"));assert.equal(home.headers()['x-content-type-options'],'nosniff');
 assert.deepEqual(errors,[]);await request.close();await browser.close();
 results.push('Keyboard skip link, menu, Escape and navigation: PASS','No-JavaScript mobile navigation: PASS','Protected file paths, invalid routes, escaped input, POST rejection, security headers: PASS','Browser JavaScript/console errors: NONE');console.log(results.join('\n'));
})().catch(e=>{console.error(e);process.exit(1)});
