import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../public/js/navigation.js', import.meta.url), 'utf8');
const element = (extra = {}) => {
    const attributes = new Map();
    return { setAttribute: (key, value) => attributes.set(key, value), getAttribute: (key) => attributes.get(key) ?? null, hasAttribute: (key) => attributes.has(key), removeAttribute: (key) => attributes.delete(key), ...extra };
};
function client() {
    const handlers = new Map();
    const lifecycle = new Map();
    const root = element();
    const timers = new Map();
    vm.runInNewContext(source, {
        document: { documentElement: root, addEventListener: (name, fn) => handlers.set(name, fn) },
        window: { addEventListener: (name, fn) => lifecycle.set(name, fn) },
        location: { href: 'https://courier.test/', origin: 'https://courier.test' }, URL, queueMicrotask,
        setTimeout: (fn) => { const id = timers.size + 1; timers.set(id, fn); return id; }, clearTimeout: (id) => timers.delete(id),
    });
    const dispatch = async (type, target, overrides = {}, afterDispatch) => {
        const event = { target, button: 0, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...overrides };
        handlers.get(type)(event);
        afterDispatch?.(event);
        await Promise.resolve();
        return event;
    };
    return { root, lifecycle, timers, dispatch };
}
const navLink = (href = 'https://courier.test/inventory') => element({ href, target: '', matches: () => true, closest(selector) { return selector === '.mobile-nav' || selector === 'a[href]' ? this : null; } });

test('navigation has immediate feedback and repeated taps are suppressed', async () => {
    const app = client();
    const link = navLink();
    await app.dispatch('click', link);
    assert.equal(app.root.hasAttribute('data-navigation-pending'), true);
    assert.equal(link.getAttribute('aria-busy'), 'true');
    assert.equal((await app.dispatch('click', link)).defaultPrevented, true);
});

test('current mobile tab taps skip a redundant reload', async () => {
    const app = client();
    assert.equal((await app.dispatch('click', navLink('https://courier.test/'))).defaultPrevented, true);
    assert.equal(app.root.hasAttribute('data-navigation-pending'), false);
});

test('modified, external and download links retain their native behavior', async () => {
    const app = client();
    await app.dispatch('click', navLink(), { ctrlKey: true });
    await app.dispatch('click', navLink('https://another.test/inventory'));
    const download = navLink(); download.setAttribute('download', '');
    await app.dispatch('click', download);
    assert.equal(app.root.hasAttribute('data-navigation-pending'), false);
});

test('duplicate form submission is blocked without disabling or losing the submitter', async () => {
    const app = client();
    const form = element({ method: 'post', target: '' });
    const button = element({ name: 'locale', value: 'my' });
    await app.dispatch('submit', form, { submitter: button });
    assert.equal(button.getAttribute('aria-busy'), 'true');
    assert.equal(button.disabled, undefined);
    assert.equal(button.value, 'my');
    assert.equal((await app.dispatch('submit', form, { submitter: button })).defaultPrevented, true);
    app.lifecycle.get('pageshow')();
    assert.equal(button.getAttribute('aria-busy'), null);
    assert.equal((await app.dispatch('submit', form, { submitter: button })).defaultPrevented, false);
});

test('confirmation, validation and offline handlers can cancel without leaving a busy form', async () => {
    const app = client();
    const form = element({ method: 'post', target: '' });
    await app.dispatch('submit', form, {}, (event) => event.preventDefault());
    assert.equal(app.root.hasAttribute('data-navigation-pending'), false);
    await app.dispatch('submit', form, { defaultPrevented: true });
    assert.equal(app.root.hasAttribute('data-navigation-pending'), false);
    assert.equal((await app.dispatch('submit', form)).defaultPrevented, false);
    assert.equal(app.root.hasAttribute('data-navigation-pending'), true);
});

test('back navigation and a stalled request clear all pending indicators', async () => {
    const app = client();
    const link = navLink();
    link.setAttribute('aria-busy', 'false');
    await app.dispatch('click', link);
    app.lifecycle.get('pagehide')();
    assert.equal(app.root.hasAttribute('data-navigation-pending'), false);
    assert.equal(link.getAttribute('aria-busy'), 'false');
    await app.dispatch('click', link);
    [...app.timers.values()][0]();
    assert.equal(link.hasAttribute('data-navigation-busy'), false);
});
