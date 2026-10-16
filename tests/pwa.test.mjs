import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../public/service-worker.js', import.meta.url), 'utf8');
function worker(fetcher, failWrites = false) {
    const handlers = new Map();
    const stored = new Map([['/offline.html', new Response('offline fallback')], ['/css/app.css', new Response('cached CSS')]]);
    const writes = [];
    const deleted = [];
    let precached = [];
    const cache = {
        match: async (path) => stored.get(path)?.clone(),
        put: async (path, response) => { if (failWrites) throw new Error('Quota exceeded'); writes.push(path); stored.set(path, response); },
        addAll: async (paths) => { precached = paths; },
    };
    vm.runInNewContext(source, {
        self: { location: { origin: 'https://courier.test' }, addEventListener: (name, fn) => handlers.set(name, fn), skipWaiting: async () => {}, clients: { claim: async () => {} } },
        caches: { open: async () => cache, keys: async () => ['standard-public-old', 'unrelated-app'], delete: async (key) => deleted.push(key) },
        fetch: fetcher, URL, Response,
    });
    const request = (path, options = {}) => {
        let response;
        handlers.get('fetch')({ request: { method: 'GET', mode: 'cors', url: new URL(path, 'https://courier.test').href, ...options }, respondWith: (value) => { response = value; } });
        return response;
    };
    return { request, stored, writes, deleted, handlers, get precached() { return precached; } };
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
    assert.deepEqual(app.writes, ['/css/app.css']);
    const offline = worker(async () => { throw new Error('Offline'); });
    assert.equal(await (await offline.request('/css/app.css?v=456')).text(), 'cached CSS');
});

test('server errors and redirects are not cached', async () => {
    const failed = worker(async () => new Response('error', { status: 500 }));
    assert.equal((await failed.request('/css/app.css')).status, 500);
    assert.equal(failed.writes.length, 0);
    const redirected = worker(async () => ({ ok: true, redirected: true }));
    await redirected.request('/css/app.css');
    assert.equal(redirected.writes.length, 0);
});

test('cache write failure does not discard a successful online response', async () => {
    const app = worker(async () => new Response('online CSS'), true);
    assert.equal(await (await app.request('/css/app.css')).text(), 'online CSS');
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
});

test('PWA client script parses', () => {
    new vm.Script(readFileSync(new URL('../public/js/pwa.js', import.meta.url), 'utf8'));
});
