import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../public/service-worker.js', import.meta.url), 'utf8');
function worker(fetcher, failWrites = false, writeDelay = Promise.resolve()) {
    const handlers = new Map();
    const stored = new Map([['/offline.html', new Response('offline fallback')], ['/css/app.css', new Response('cached CSS')], ['/css/app.css?v=456', new Response('cached version 456')]]);
    const writes = [];
    const deleted = [];
    let precached = [];
    let preloadEnabled = false;
    const pending = [];
    const cache = {
        match: async (path) => stored.get(path)?.clone(),
        put: async (path, response) => { await writeDelay; if (failWrites) throw new Error('Quota exceeded'); writes.push(path); stored.set(path, response); },
        addAll: async (paths) => { precached = paths; },
    };
    vm.runInNewContext(source, {
        self: { location: { origin: 'https://courier.test' }, addEventListener: (name, fn) => handlers.set(name, fn), skipWaiting: async () => {}, clients: { claim: async () => {} }, registration: { navigationPreload: { enable: async () => { preloadEnabled = true; } } } },
        caches: { open: async () => cache, keys: async () => ['standard-public-old', 'unrelated-app'], delete: async (key) => deleted.push(key) },
        fetch: fetcher, URL, Response,
    });
    const request = (path, options = {}, preloadResponse) => {
        let response;
        handlers.get('fetch')({ preloadResponse, waitUntil: (promise) => pending.push(promise), request: { method: 'GET', mode: 'cors', url: new URL(path, 'https://courier.test').href, ...options }, respondWith: (value) => { response = value; } });
        return response;
    };
    return { request, stored, writes, deleted, handlers, pending, get preloadEnabled() { return preloadEnabled; }, get precached() { return precached; } };
}

test('online private navigation is served without caching its contents', async () => {
    const app = worker(async () => new Response('private shipment and balance'));
    assert.equal(await (await app.request('/shipments/42', { mode: 'navigate' })).text(), 'private shipment and balance');
    assert.equal(app.writes.length, 0);
});

test('offline navigations show the generic fallback', async () => {
    const app = worker(async () => { throw new Error('Offline'); });
    assert.equal(await (await app.request('/reports', { mode: 'navigate' })).text(), 'offline fallback');
    assert.equal(app.writes.length, 0);
});

test('writes, private photos, exports and other origins bypass the worker', () => {
    const app = worker(() => { throw new Error('Must not intercept'); });
    for (const path of ['/shipments/42/photos/private-id', '/reports/export', '/app.webmanifest', '/api/parcels']) {
        assert.equal(app.request(path), undefined);
    }
    assert.equal(app.request('/shipments', { method: 'POST', mode: 'navigate' }), undefined);
    assert.equal(app.request('https://another.test/css/app.css'), undefined);
});

test('only allowlisted static paths are cached and remain available offline', async () => {
    const app = worker(async () => new Response('fresh CSS'));
    assert.equal(await (await app.request('/css/app.css?v=123')).text(), 'fresh CSS');
    await Promise.all(app.pending);
    assert.deepEqual(app.writes, ['/css/app.css?v=123']);
    const offline = worker(async () => { throw new Error('Offline'); });
    assert.equal(await (await offline.request('/css/app.css?v=456')).text(), 'cached version 456');
});

test('server errors and redirects are not cached', async () => {
    const failed = worker(async () => new Response('error', { status: 500 }));
    assert.equal((await failed.request('/js/app.js')).status, 500);
    assert.equal(failed.writes.length, 0);
    const redirected = worker(async () => ({ ok: true, redirected: true }));
    await redirected.request('/js/app.js');
    assert.equal(redirected.writes.length, 0);
});

test('cache write failure does not discard a successful online response', async () => {
    const app = worker(async () => new Response('online CSS'), true);
    assert.equal(await (await app.request('/js/app.js')).text(), 'online CSS');
});

test('installation caches public assets and activation leaves unrelated caches intact', async () => {
    const app = worker(async () => new Response('static'));
    let completion;
    app.handlers.get('install')({ waitUntil: (promise) => { completion = promise; } });
    await completion;
    assert.ok(app.precached.includes('/offline.html'));
    assert.ok(app.precached.every((path) => path === '/offline.html' || /^\/(css|js|images)\//.test(path)));
    app.handlers.get('activate')({ waitUntil: (promise) => { completion = promise; } });
    await completion;
    assert.deepEqual(app.deleted, ['standard-public-old']);
    assert.equal(app.preloadEnabled, true);
});

test('PWA client script parses', () => {
    new vm.Script(readFileSync(new URL('../public/js/pwa.js', import.meta.url), 'utf8'));
});


test('a cached versioned asset renders without any network request', async () => {
    let requests = 0;
    const app = worker(async () => { requests++; throw new Error('Slow network'); });
    assert.equal(await (await app.request('/css/app.css?v=456')).text(), 'cached version 456');
    assert.equal(requests, 0);
});

test('a new asset version never falls back to the old or unversioned cached code', async () => {
    const app = worker(async () => { throw new Error('Offline'); });
    await assert.rejects(app.request('/css/app.css?v=789'), /Offline/);
});

test('unversioned cached assets render while a stalled network refresh runs in the background', async () => {
    let finish;
    const app = worker(() => new Promise((resolve) => { finish = resolve; }));
    assert.equal(await (await app.request('/css/app.css')).text(), 'cached CSS');
    finish(new Response('refreshed CSS'));
    await Promise.all(app.pending);
    assert.equal(await app.stored.get('/css/app.css').text(), 'refreshed CSS');
});

test('navigation uses preload without making a duplicate private-page request', async () => {
    const app = worker(() => { throw new Error('Duplicate fetch'); });
    const response = await app.request('/reports', { mode: 'navigate' }, Promise.resolve(new Response('preloaded report')));
    assert.equal(await response.text(), 'preloaded report');
    assert.equal(app.writes.length, 0);
});


test('a slow cache write does not hold up the successful network response', async () => {
    let finish;
    const delayedWrite = new Promise((resolve) => { finish = resolve; });
    const app = worker(async () => new Response('new script'), false, delayedWrite);
    assert.equal(await (await app.request('/js/app.js?v=new')).text(), 'new script');
    assert.equal(app.writes.length, 0);
    finish();
    await Promise.all(app.pending);
    assert.deepEqual(app.writes, ['/js/app.js?v=new']);
});
