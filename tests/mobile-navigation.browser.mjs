import assert from 'node:assert/strict';

// Run against a disposable seeded local database with a dedicated admin account.
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const baseURL = process.env.UAT_BASE_URL || 'http://127.0.0.1:8016';
assert.ok(process.env.UAT_EMAIL && process.env.UAT_PASSWORD, 'Set dedicated UAT credentials');
const browser = await chromium.launch({ headless: true, channel: 'chrome' });
const context = await browser.newContext({ baseURL, viewport: { width: 390, height: 844 }, serviceWorkers: 'block' });
const page = await context.newPage();
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));
const verify = async (index) => {
    await page.waitForSelector('.mobile-nav[data-indicator-ready]');
    await page.waitForFunction((index) => {
        const nav = document.querySelector('.mobile-nav');
        const item = nav.querySelectorAll(':scope > a, :scope > button')[index];
        const indicator = nav.querySelector('.mobile-nav-indicator');
        const a = item.getBoundingClientRect();
        const b = indicator.getBoundingClientRect();
        return item.hasAttribute('data-mobile-selected') && ['left', 'top', 'width', 'height'].every((key) => Math.abs(a[key] - b[key]) < 1);
    }, index);
    assert.equal(await page.locator('.mobile-nav-indicator').count(), 1);
    assert.equal(await page.locator('.mobile-nav > [data-mobile-selected]').count(), 1);
    assert.equal(await page.locator('.mobile-nav > button').getAttribute('aria-expanded'), 'false');
    assert.equal(await page.locator('.mobile-nav > a[aria-current="page"]').count(), index === 4 ? 0 : 1);
    assert.equal(await page.locator('.mobile-nav > button').getAttribute('aria-current'), index === 4 ? 'page' : null);
};
try {
    await page.goto('/login');
    await page.locator('[name=email]').fill(process.env.UAT_EMAIL);
    await page.locator('[name=password]').fill(process.env.UAT_PASSWORD);
    await Promise.all([page.waitForURL((url) => url.pathname === '/'), page.locator('form:has([name=password]) button').click()]);
    const routes = [['/', 0], ['/dashboard/sg', 0], ['/flight-records', 4], ['/inventory', 4], ['/dispatches', 2], ['/reports', 4], ['/shipments', 3], ['/shipments/create', 1], ['/customers', 4], ['/customers/create', 4], ['/branches', 4], ['/settings', 4]];
    for (const [path, index] of routes) {
        assert.equal((await page.goto(path)).status(), 200, path);
        await verify(index);
        await page.reload();
        await verify(index);
    }
    for (const [listing, pattern, index] of [
        ['/shipments', /\/shipments\/\d+$/, 3],
        ['/customers', /\/customers\/\d+$/, 4],
        ['/dispatches', /\/dispatches\/\d+$/, 2],
        ['/inventory', /\/inventory\/\d+\/edit$/, 4],
    ]) {
        await page.goto(listing);
        const links = await page.locator('main a[href]').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
        const nested = links.find((href) => pattern.test(href));
        assert.ok(nested, `Seeded ${listing} must contain a nested route`);
        assert.equal((await page.goto(nested)).status(), 200);
        await verify(index);
        await page.reload();
        await verify(index);
    }
    await page.goto('/');
    await page.locator('.mobile-nav > a').nth(1).click();
    await page.waitForURL('**/shipments/create');
    await verify(1);
    await page.goBack();
    await verify(0);
    await page.goForward();
    await verify(1);
    await page.locator('[data-mobile-menu-open]').click();
    await page.waitForFunction(() => document.querySelector('[data-mobile-menu-open]').getAttribute('aria-expanded') === 'true');
    await page.waitForTimeout(320);
    assert.equal(await page.locator('.mobile-nav > button').getAttribute('data-mobile-selected'), '');
    await page.keyboard.press('Escape');
    await verify(1);
    for (const width of [498, 740, 1024, 390]) {
        await page.setViewportSize({ width, height: 844 });
        if (width <= 780) await verify(1);
    }
    // Unequal padding reproduces landscape safe-area geometry without a physical device.
    await page.addStyleTag({ content: '.mobile-nav { padding-left: 37px !important; padding-right: 19px !important; gap: 7px !important; }' });
    await verify(1);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.locator('[data-mobile-menu-open]').click();
    assert.equal(await page.locator('.mobile-nav-indicator').evaluate((el) => el.getAnimations().length), 0);
    await page.keyboard.press('Escape');
    await verify(1);
    // Hold native document requests so a newer click can replace a slow first navigation.
    await page.goto('/');
    console.log('Checking rapid native navigation');
    await page.route('**/shipments/create', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 2000));
        await route.abort().catch(() => {});
    });
    await page.evaluate(() => {
        document.querySelectorAll('.mobile-nav > a')[1].click();
        setTimeout(() => document.querySelectorAll('.mobile-nav > a')[2].click(), 80);
    });
    await page.waitForURL('**/dispatches', { timeout: 10000 });
    await verify(2);
    await page.unroute('**/shipments/create');
    let documentRequests = 0;
    page.on('request', (request) => { if (request.isNavigationRequest()) documentRequests++; });
    await page.locator('.mobile-nav > a').nth(2).click();
    await page.waitForTimeout(100);
    assert.equal(documentRequests, 0, 'Current destination must not reload');
    assert.deepEqual(errors, []);
    console.log('PASS: authenticated deep links, refresh, history, menu, responsive geometry, reduced motion, rapid native navigation, current-tab retap');
} finally {
    await browser.close();
}
