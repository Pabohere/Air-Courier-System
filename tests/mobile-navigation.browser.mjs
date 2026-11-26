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
    for (const width of [320, 498, 740, 820, 1024, 1100]) {
        await page.setViewportSize({ width, height: 844 });
        await verify(1);
        assert.equal(await page.locator('.sidebar').isVisible(), false);
        const bar = await page.locator('.mobile-nav').boundingBox();
        assert.ok(Math.abs(bar.y + bar.height - 844) < 1, 'Tab bar stays at the bottom');
    }
    await page.locator('[data-mobile-menu-open]').click();
    await page.setViewportSize({ width: 1280, height: 844 });
    await page.waitForFunction(() => !document.querySelector('.mobile-menu').open);
    assert.equal(await page.locator('.sidebar').isVisible(), true);
    assert.equal(await page.locator('.mobile-nav').isVisible(), false);

    const tablet = await browser.newContext({
        baseURL, hasTouch: true, isMobile: true, viewport: { width: 1366, height: 1024 },
        storageState: await context.storageState(),
    });
    const tabletPage = await tablet.newPage();
    try {
        await tabletPage.goto('/');
        await tabletPage.waitForFunction(() => Boolean(navigator.serviceWorker.controller));
        for (const [width, height, columns] of [[1366, 1024, 4], [1024, 1366, 4], [820, 1180, 2]]) {
            await tabletPage.setViewportSize({ width, height });
            await tabletPage.evaluate(() => document.fonts.ready);
            assert.equal(await tabletPage.locator('.sidebar').isVisible(), false);
            const layout = await tabletPage.evaluate(() => {
                const bounds = (selector) => document.querySelector(selector).getBoundingClientRect();
                const { height: barHeight, top: barTop } = bounds('.mobile-nav');
                const header = bounds('.workspace > header');
                const heading = bounds('.daily-heading');
                const picker = bounds('.home-branch-picker');
                const cards = [...document.querySelectorAll('.daily-task')];
                const labels = [...document.querySelectorAll('.mobile-nav .workspace-link-label')];
                return {
                    barHeight,
                    labelLines: labels.map((label) => {
                        const range = document.createRange();
                        range.selectNodeContents(label);
                        return new Set([...range.getClientRects()].map((rect) => rect.top)).size;
                    }),
                    headingVisible: heading.top >= header.bottom && picker.bottom <= barTop,
                    balanced: Math.abs(heading.left - (innerWidth - heading.right)) < 2,
                    columns: cards.filter((card) => card.offsetTop === cards[0].offsetTop).length,
                    cardsFit: cards.every((card) => {
                        const text = card.querySelector('.daily-task-copy').getBoundingClientRect();
                        return text.bottom <= card.getBoundingClientRect().bottom - 8;
                    }),
                    scrollWidth: document.documentElement.scrollWidth,
                };
            });
            assert.ok(layout.barHeight > 0 && layout.barHeight <= 100, `Compact tab bar at ${width}px`);
            assert.deepEqual(layout.labelLines, [1, 1, 1, 1, 1], `Single-line tab labels at ${width}px`);
            assert.ok(layout.headingVisible && layout.balanced, `Home heading and branch switcher fit at ${width}px`);
            assert.equal(layout.columns, columns);
            assert.ok(layout.cardsFit, `Task copy fits at ${width}px`);
            assert.ok(layout.scrollWidth <= width, `No horizontal page overflow at ${width}px`);
        }
        await tabletPage.setViewportSize({ width: 1366, height: 1024 });
        await tabletPage.locator('[data-mobile-search]').tap();
        const tabletSearch = tabletPage.locator('#workspace-search');
        assert.equal(await tabletSearch.evaluate((input) => getComputedStyle(input).fontSize), '16px');
        await tabletSearch.press('Escape');
        assert.equal(await tabletSearch.isVisible(), false);
        await Promise.all([
            tabletPage.waitForURL('**/dispatches'),
            tabletPage.locator('.mobile-nav > a').nth(2).tap(),
        ]);
        assert.equal(await tabletPage.locator('.mobile-nav > a').nth(2).getAttribute('aria-current'), 'page');
        const tabClearance = await tabletPage.evaluate(() => {
            const nav = document.querySelector('.mobile-nav');
            const workspace = document.querySelector('.workspace');
            window.scrollTo(0, document.documentElement.scrollHeight);
            return {
                barBottom: nav.getBoundingClientRect().bottom,
                height: window.innerHeight,
                clearance: parseFloat(getComputedStyle(workspace).paddingBottom),
                barHeight: nav.getBoundingClientRect().height,
            };
        });
        assert.ok(Math.abs(tabClearance.barBottom - tabClearance.height) < 1);
        assert.ok(tabClearance.clearance >= tabClearance.barHeight);
        for (const width of [1024, 390]) {
            await tabletPage.setViewportSize({ width, height: 844 });
            for (const [path, names] of [
                ['/dispatches', ['.guided-form [name="flight"]', '.guided-form [name="flight_departure_at"]']],
                ['/flight-records', ['.filters [name="from"]', '.filters [name="to"]']],
                ['/reports', ['[name="date"]', '[name="payment_method_id"]']],
            ]) {
                await tabletPage.goto(path);
                await tabletPage.locator(names[0]).waitFor();
                const boxes = await tabletPage.evaluate((selectors) => selectors.map((selector) => {
                    const field = document.querySelector(selector);
                    const { left, right, top, bottom } = field.getBoundingClientRect();
                    const label = field.closest('label').getBoundingClientRect();
                    return { left, right, top, bottom, labelLeft: label.left, labelRight: label.right };
                }), names);
                const [first, second] = boxes;
                assert.ok(boxes.every(({ left, right, labelLeft, labelRight }) => left >= labelLeft - 1 && right <= labelRight + 1), `${path} fields fit their labels at ${width}px`);
                assert.ok(first.right <= second.left + 1 || first.bottom <= second.top + 1, `${path} fields do not overlap at ${width}px`);
            }
        }
    } finally {
        await tablet.close();
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await verify(1);
    // Unequal padding reproduces landscape safe-area geometry without a physical device.
    await page.addStyleTag({ content: '.mobile-nav { padding-left: 37px !important; padding-right: 19px !important; gap: 7px !important; }' });
    await verify(1);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.locator('[data-mobile-menu-open]').click();
    assert.equal(await page.locator('.mobile-nav-indicator').evaluate((el) => el.getAnimations().length), 0);
    await page.keyboard.press('Escape');
    await verify(1);
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    // Hold native document requests so a newer click can replace a slow first navigation.
    await page.goto('/');
    console.log('Checking rapid native navigation');
    await page.route('**/shipments/create', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 2000));
        await route.abort().catch(() => {});
    });
    const animated = await page.evaluate(async () => {
        document.querySelectorAll('.mobile-nav > a')[1].click();
        await Promise.resolve();
        setTimeout(() => document.querySelectorAll('.mobile-nav > a')[2].click(), 80);
        return document.querySelector('.mobile-nav-indicator').getAnimations().length;
    });
    assert.equal(animated, 1, 'Tab selection starts moving before the server responds');
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
