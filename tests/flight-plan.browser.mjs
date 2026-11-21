import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
assert.equal(process.env.UAT_DISPOSABLE_DATABASE, '1', 'Use a disposable local BranchDemoSeeder database only.');
const baseURL = process.env.UAT_BASE_URL || 'http://127.0.0.1:8018';
assert.ok(['localhost', '127.0.0.1'].includes(new URL(baseURL).hostname));
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const browser = await chromium.launch({ headless: true, channel: 'chrome' });
const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 1000 }, timezoneId: 'America/Los_Angeles', serviceWorkers: 'block' });
const page = await context.newPage();
const errors = [];
page.on('pageerror', e => errors.push(e.message));
try {
    await page.goto('/login');
    await page.locator('[name=email]').fill('ygn@courier.test');
    await page.locator('[name=password]').fill('CourierDemo2026!');
    await page.locator('form:has([name=password]) button').click();
    await page.waitForURL(url => url.pathname === '/');
    await page.goto('/shipments/create');
    const csrf = await page.locator('meta[name=csrf-token]').getAttribute('content');
    const origin = await page.locator('[name=origin_branch_id]').inputValue();
    const destination = await page.locator('[name=destination_branch_id]').inputValue();
    const received = await page.locator('[name=shipment_date]').inputValue();
    const records = [];
    for (const [index, description] of ['Blue suitcase with clothes', 'Red carton with books'].entries()) {
        const response = await context.request.post('/shipments', { form: {
            _token: csrf, booking_key: randomUUID(), origin_branch_id: origin, destination_branch_id: destination,
            customer_name: `Flight QA sender ${index + 1}`, customer_phone: `0900000000${index + 1}`,
            customer_address: `Full sender address ${index + 1}`, recipient_name: `Flight QA recipient ${index + 1}`,
            recipient_phone: `+65 8000 000${index + 1}`, recipient_address: `Full recipient address ${index + 1}`,
            description, quantity: String(index + 2), actual_weight: index ? '6.000' : '24.984',
            price: index ? '25000.00' : '50.00', currency: index ? 'MMK' : 'SGD', shipment_date: received, storage_location: `QA shelf ${index + 1}`,
        } });
        assert.equal(response.status(), 200);
        const path = new URL(response.url()).pathname;
        assert.match(path, /^\/shipments\/\d+$/);
        records.push({ id: path.split('/').pop(), description });
    }
    const photo = await context.request.post(`/shipments/${records[0].id}/photos`, { multipart: {
        _token: csrf,
        'photos[]': { name: 'parcel-test.png', mimeType: 'image/png', buffer: readFileSync(new URL('../public/images/pwa-192.png', import.meta.url)) },
    } });
    assert.equal(photo.status(), 200);
    await page.goto('/dispatches');
    await page.locator('[name=carrier_name]').fill('Flight QA carrier');
    await page.locator('[name=flight]').fill('QA-FUTURE-123');
    const futureDate = await page.evaluate(() => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Yangon' }).format(new Date(Date.now() + 2 * 86400000)));
    await page.locator('[name=flight_departure_at]').fill(futureDate + 'T14:30');
    assert.match(await page.locator('[data-step]:visible').textContent(), /Flight time is for records only/);
    assert.equal(await page.locator('[name=quick_action][value=send]').count(), 0);
    await page.locator('[data-step-next]').click();
    for (const record of records) {
        const card = page.locator('[data-parcel-option]').filter({ has: page.locator(`input[value="${record.id}"]`) });
        assert.match(await card.textContent(), /Full sender address/);
        assert.match(await card.textContent(), /Full recipient address/);
        await card.locator('input[type=checkbox]').check();
    }
    await page.locator('[data-selection-count]').filter({ hasText: 'Selected parcels: 2' }).waitFor();
    await page.locator('[data-step]:visible').screenshot({ path: join(tmpdir(), 'courier-flight-selection-en.png') });
    await page.locator('[data-step-next]').click();
    assert.equal(await page.locator('[data-selected-parcel-review] [data-parcel-preview]').count(), 2);
    await page.locator('[name=quick_action][value=draft]').click();
    await page.waitForURL(/\/dispatches\/\d+$/);
    const flightURL = page.url();
    assert.equal(await page.locator('.flight-parcel-card').count(), 2);
    await page.locator('[data-flight-record]').waitFor({ state: 'visible' });
    assert.equal(await page.locator('[data-flight-future], [data-flight-countdown]').count(),0);
    assert.equal(await page.locator('form[action$="/handover"] button').isDisabled(), false);
    assert.match(await page.locator('.trip-workflow').textContent(), /Parcels are still in origin storage/);
    for (const record of records) assert.match(await page.locator('main').textContent(), new RegExp(record.description));
    const imageURL = await page.locator('.parcel-summary-photos img').first().getAttribute('src');
    assert.equal((await context.request.get(imageURL)).status(), 200);
    await page.locator('.flight-parcel-card').first().screenshot({ path: join(tmpdir(), 'courier-flight-parcel-detail-en.png') });
    await page.locator('#edit-flight-time > summary').click();
    const edit = page.locator('form[action$="/flight-time"]');
    const revisedDate = new Date(futureDate + 'T00:00:00Z');revisedDate.setUTCDate(revisedDate.getUTCDate() + 1);
    await edit.locator('[name=flight_departure_at]').fill(revisedDate.toISOString().slice(0,10) + 'T17:45');
    await edit.locator('button').click();await page.waitForLoadState('networkidle');
    assert.match(await page.locator('.notice.success').first().textContent(),/Flight time saved/);
    assert.match(await page.locator('[data-flight-record]').textContent(),/17:45/);
    assert.match(await page.locator('.trip-workflow').textContent(),/Parcels are still in origin storage/);
    await page.locator('form[action$="/handover"] button').click();
    await page.locator('dialog.review-dialog[open] button:not(.secondary)').click();await page.waitForLoadState('networkidle');
    assert.match(await page.locator('.trip-workflow').textContent(),/Handed to carrier/);
    const manifest = await context.newPage();
    await manifest.goto(await page.locator('a[href$="/manifest"]').getAttribute('href'));
    assert.equal(await manifest.locator('[data-parcel-preview]').count(), 2);
    assert.match(await manifest.locator('main').textContent(), /Full sender address 1/);
    assert.match(await manifest.locator('main').textContent(), /Full recipient address 2/);
    await manifest.close();
    await page.locator('header .language-switch button[value=my]').click();
    await page.waitForLoadState('networkidle');
    for (const width of [1440, 768, 390, 375]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(flightURL);
        assert.equal(await page.locator('html').getAttribute('lang'), 'my');
        assert.match(await page.locator('[data-flight-record]').textContent(), /မှတ်တမ်းအတွက်သာ/);
        assert.equal(await page.locator('[data-flight-future], [data-flight-countdown]').count(),0);
        const geometry = await page.evaluate(() => ({ width: innerWidth, content: document.documentElement.scrollWidth }));
        assert.ok(geometry.content <= geometry.width + 1, JSON.stringify(geometry));
        assert.equal(await page.locator('.flight-parcel-card').count(), 2);
        if (width === 390) await page.screenshot({ path: join(tmpdir(), 'courier-flight-future-my-mobile.png'), fullPage: true });
    }
    // Historical lookup must identify the carrier and the exact flight containing this parcel.
    await page.setViewportSize({ width:1440, height:1000 });await page.goto(flightURL);
    await page.locator('header .language-switch button[value=en]').click();await page.waitForLoadState('networkidle');
    const tracking = (await page.locator('.flight-parcel-card .mono').first().textContent()).trim();
    await page.goto('/flight-records?q='+encodeURIComponent(tracking));
    assert.equal(await page.locator('[data-flight-record-id]').count(),1);
    assert.ok(Number.parseFloat(await page.locator('[data-flight-record-id] h2 a').evaluate(el=>getComputedStyle(el).fontSize))>=20);
    assert.match(await page.locator('[data-flight-record-id]').textContent(),/Flight QA carrier/);
    assert.match(await page.locator('[data-flight-record-id]').textContent(),/QA-FUTURE-123/);
    assert.match(await page.locator('[data-flight-record-id]').textContent(),/Actual carrier handover/);
    assert.match(await page.locator('[data-flight-record-id]').textContent(),/Blue suitcase with clothes/);
    await page.locator('select[name=status]').selectOption('planned');await page.locator('form.filters button').click();await page.waitForLoadState('networkidle');
    assert.equal(await page.locator('[data-flight-record-id]').count(),0);
    await page.goto('/flight-records?q='+encodeURIComponent(tracking)+'&status=handed_over');
    await page.getByRole('link',{name:'View flight and all parcels'}).click();await page.waitForURL(flightURL);
    assert.equal(await page.locator('.flight-parcel-card').count(),2);
    await page.setViewportSize({width:390,height:900});await page.goto('/flight-records?q='+encodeURIComponent(tracking));
    assert.equal(await page.locator('.mobile-nav > button[aria-current=page]').count(),1);
    await page.locator('[data-mobile-menu-open]').click();await page.locator('.mobile-menu .language-switch button[value=my]').click();await page.waitForLoadState('networkidle');
    assert.equal(await page.locator('html').getAttribute('lang'),'my');
    assert.match(await page.locator('main h1').textContent(),/လေယာဉ်ခရီးစဉ်မှတ်တမ်း/);
    const historyGeometry = await page.evaluate(()=>({width:innerWidth,content:document.documentElement.scrollWidth}));
    assert.ok(historyGeometry.content<=historyGeometry.width+1,JSON.stringify(historyGeometry));
    await page.screenshot({path:join(tmpdir(),'courier-flight-records-my-mobile.png'),fullPage:true});
    console.log('PASS: carrier/flight/parcel history search, planned vs handed-over filter, full trip links and Myanmar mobile navigation');
    assert.deepEqual(errors, []);
    console.log('PASS: manual flight-time editing, unrestricted future-date handover, distinct parcels, private photo, full manifest and bilingual responsive trip details');
} catch (error) {
    await page.screenshot({ path: join(tmpdir(), 'courier-flight-browser-failure.png'), fullPage: true });
    console.error('FAILED at', page.url());
    throw error;
} finally {
    await browser.close();
}
