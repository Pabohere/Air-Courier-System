const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
import assert from 'node:assert/strict';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
assert.equal(process.env.UAT_DISPOSABLE_DATABASE, '1', 'Run only against a separate disposable database seeded with BranchDemoSeeder.');
const baseURL = process.env.UAT_BASE_URL || 'http://127.0.0.1:8018';
assert.ok(['127.0.0.1', 'localhost'].includes(new URL(baseURL).hostname), 'Use a localhost QA server.');
const browser = await chromium.launch({headless:true,channel:'chrome'});
const context = await browser.newContext({baseURL,viewport:{width:1440,height:1000},serviceWorkers:'block'});
const page = await context.newPage();
const errors=[]; page.on('pageerror',e=>errors.push(e.message));
page.on('dialog',d=>{console.log('Unexpected native dialog:',d.message());d.dismiss();});
const login=async(email)=>{await page.goto('/login');await page.locator('[name=email]').fill(email);await page.locator('[name=password]').fill('CourierDemo2026!');await page.locator('.login-form button[type=submit], .login-form form button.button').last().click();await page.waitForURL(url=>url.pathname==='/');};
const next=()=>page.locator('[data-step-next]').click();
const visibleStep=async(n)=>assert.equal(await page.locator('[data-step]:visible').getAttribute('data-step-title'),n);
const noOverflow=async()=>{const size=await page.evaluate(()=>({viewport:innerWidth,body:document.documentElement.scrollWidth}));assert.ok(size.body<=size.viewport+1,JSON.stringify(size));};
try {
 await login('ygn@courier.test');
 assert.equal(await page.locator('.daily-task').count(),4);
 assert.equal(await page.locator('body').evaluate(e=>getComputedStyle(e).fontSize),'18px');
 await page.screenshot({path:join(tmpdir(), 'courier-home-desktop.png'),fullPage:true});
 for(const width of [1440,1024,768,390,375]) {
  await page.setViewportSize({width,height:900});
  for(const path of ['/','/shipments/create','/shipments','/dispatches','/inventory','/reports','/customers','/branches']) {
   const response=await page.goto(path); assert.equal(response.status(),200,path); await noOverflow();
   if(width<=780)assert.equal(await page.locator('.mobile-nav > [aria-current=page]').count(),1,path);
  }
 }
 await page.setViewportSize({width:1440,height:1000});
 await page.goto('/shipments/create');await visibleStep('Customer Information');
 await next();await visibleStep('Customer Information');
 await page.locator('[data-customer-search]').fill('Branch workflow sample');await page.locator('[data-customer-find]').click();
 await page.locator('[data-customer-matches] button').first().click();
 assert.ok(Number(await page.locator('[name=customer_id]').inputValue())>0);
 await next();await visibleStep('Recipient Information');
 await page.locator('[name=recipient_name]').fill('Browser QA recipient');await page.locator('[name=recipient_phone]').fill('+65 8000 0001');
 await next();await visibleStep('Parcel Information');
 await page.locator('[name=description]').fill('Browser QA books');await page.locator('[name=actual_weight]').fill('1.250');await page.locator('[name=price]').fill('25000.00');
 await next();await visibleStep('Review and Save');
 assert.match(await page.locator('[data-workflow-review]').textContent(),/25000.00/);
 await page.locator('[data-step-back]').click();assert.equal(await page.locator('[name=price]').inputValue(),'25000.00');await next();
 await page.locator('[data-workflow-submit] button').click();await page.waitForURL(/\/shipments\/\d+$/);
 assert.equal(await page.locator('.booking-complete').count(),1);const parcelURL=page.url();
 const tracking=await page.locator('h1').textContent();console.log('Created test parcel:',tracking.trim());
 await page.goto(parcelURL+'?task=payment');
 const payment=page.locator('form[action$="/payments"]');
 await payment.locator('[name=amount]').fill('5000.00');await payment.locator('[name=cash_tendered]').fill('10000.00');
 await payment.locator('button.button').last().click();
 await page.locator('dialog.review-dialog[open]').waitFor();assert.match(await page.locator('dialog.review-dialog[open]').textContent(),/5000.00/);
 await page.locator('dialog.review-dialog[open] button.secondary').click();assert.equal(await payment.locator('[name=amount]').inputValue(),'5000.00');
 await payment.locator('button.button').last().click();await page.locator('dialog.review-dialog[open] button:not(.secondary)').click();await page.waitForLoadState('networkidle');
 assert.match(await page.locator('.notice.success').first().textContent(),/Payment recorded/);
 await page.goto('/dispatches?shipment='+new URL(parcelURL).pathname.split('/').pop());
 await visibleStep('Flight details');await page.locator('[name=carrier_name]').fill('Browser QA carrier');await page.locator('[name=flight]').fill('QA 123');
 const today=await page.evaluate(()=>new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Yangon'}).format(new Date()));
 await page.locator('[name=flight_departure_at]').fill(today+'T09:00');await next();await visibleStep('Choose Parcels');await next();await visibleStep('Review Flight Plan');
 assert.match(await page.locator('[data-workflow-review]').textContent(),/QA 123/);
 assert.equal(await page.locator('[data-selected-parcel-review] [data-parcel-preview]').count(),1);
 await page.locator('button[name=quick_action][value=draft]').click();await page.waitForURL(/\/dispatches\/\d+$/);
 assert.match(await page.locator('.trip-workflow').textContent(),/Parcels are still in origin storage/);
 await page.locator('form[action$="/handover"] button').click();await page.locator('dialog.review-dialog[open]').waitFor();assert.match(await page.locator('dialog.review-dialog[open]').textContent(),/QA 123/);
 await page.locator('dialog.review-dialog[open] button:not(.secondary)').click();await page.waitForLoadState('networkidle');
 await page.locator('header form[action$="/logout"] button').click();await page.waitForURL('**/login');
 await login('sg@courier.test');await page.goto(parcelURL);
 const receive=page.locator('form[action$="/receive"]');await receive.locator('[name=storage_location]').fill('QA collection shelf');await receive.locator('button').click();await page.waitForLoadState('networkidle');
 const collect=page.locator('form[action$="/collect"]');await collect.locator('[name=receive_balance]').check();await collect.locator('[name=cash_tendered]').fill('20000.00');
 await collect.locator('button').click();await page.locator('dialog.review-dialog[open]').waitFor();
 assert.match(await page.locator('dialog.review-dialog[open]').textContent(),/20000.00/);
 await page.locator('dialog.review-dialog[open] button:not(.secondary)').click();await page.waitForLoadState('networkidle');
 assert.match(await page.locator('main').textContent(),/Customer collection completed/);
 console.log('PASS: booking, selected customer, review/back, partial cash payment/change, dispatch, destination receipt and settled collection');
 // Saving an incomplete draft must work from step 1 and preserve selection after reopening.
 await page.goto('/shipments/create');await page.locator('[name=customer_name]').fill('Browser draft customer');await page.locator('[name=customer_phone]').fill('09000000001');
 await page.locator('button[formnovalidate]').click();await page.waitForURL(/draft=/);assert.equal(await page.locator('[name=customer_name]').inputValue(),'Browser draft customer');
 // A phone that passes the loose HTML pattern must still recover to its server-rejected step.
 await next();await page.locator('[name=recipient_name]').fill('Invalid phone test');await page.locator('[name=recipient_phone]').fill('1234567890123456');await next();
 await page.locator('[name=description]').fill('Validation test only');await page.locator('[name=actual_weight]').fill('1');await page.locator('[name=price]').fill('100');await next();
 await page.locator('[data-workflow-submit] button').click();await page.waitForLoadState('networkidle');
 await visibleStep('Recipient Information');assert.equal(await page.locator('[name=recipient_phone]').getAttribute('aria-invalid'),'true');
 assert.equal(await page.locator('[name=description]').inputValue(),'Validation test only');
 console.log('PASS: server validation reopens the correct step, highlights its field and preserves information');
 for(const width of [1440,768,390,375]) {
  await page.setViewportSize({width,height:900});await page.goto('/');
  const language=width>780?page.locator('header .language-switch button[value=my]'):page.locator('.mobile-menu .language-switch button[value=my]');
  if(width<=780)await page.locator('[data-mobile-menu-open]').click();
  await language.click();await page.waitForLoadState('networkidle');assert.equal(await page.locator('html').getAttribute('lang'),'my');
  for(const path of ['/','/shipments/create','/dispatches','/shipments?task=payment']){await page.goto(path);await noOverflow();}
  await page.goto('/');await page.screenshot({path:join(tmpdir(), 'courier-home-'+width+'-my.png'),fullPage:true});
 }
 assert.deepEqual(errors,[]);
 console.log('PASS: incomplete drafts; desktop/tablet/Android/iPhone widths in English and Myanmar; no page errors or horizontal overflow');
} catch(e) {await page.screenshot({path:join(tmpdir(), 'courier-browser-failure.png'),fullPage:true});console.error('FAILED at',page.url());throw e;}
finally {await browser.close();}
