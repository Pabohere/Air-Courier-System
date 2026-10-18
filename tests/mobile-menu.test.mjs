import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../public/js/mobile-menu.js', import.meta.url), 'utf8');
function client({ reduced = false, animated = true } = {}) {
    const handlers = new Map();
    const events = new Map();
    const animations = [];
    const queries = new Map();
    const attributes = new Map();
    let focused = 0;
    const trigger = { addEventListener: (name, fn) => handlers.set(`trigger:${name}`, fn), setAttribute: (name, value) => attributes.set(name, value), focus: (options) => { assert.equal(options.preventScroll, true); focused++; } };
    const closeButton = { addEventListener: (name, fn) => handlers.set(`close:${name}`, fn) };
    const menu = {
        open: false,
        addEventListener: (name, fn) => handlers.set(name, fn),
        setAttribute: (name, value) => attributes.set(name, value),
        removeAttribute: (name) => attributes.delete(name),
        showModal() { this.open = true; },
        close() { this.open = false; handlers.get('close')(); },
        getBoundingClientRect: () => ({ left: 0, right: 390, top: 200, bottom: 844 }),
    };
    if (animated) menu.animate = (frames, options) => {
        let resolve, reject;
        const finished = new Promise((yes, no) => { resolve = yes; reject = no; });
        const animation = { frames, options, finished, finish: resolve, cancel: () => reject(new Error('cancelled')) };
        animations.push(animation);
        return animation;
    };
    vm.runInNewContext(source, {
        document: { querySelector: (selector) => selector === '.mobile-menu' ? menu : closeButton, querySelectorAll: () => [trigger] },
        window: { addEventListener: (name, fn) => events.set(name, fn), matchMedia: (query) => {
            const media = { matches: query.includes('reduced-motion') && reduced, addEventListener: (name, fn) => queries.set(query, fn) };
            return media;
        } },
        URL, location: { href: 'https://courier.test/', origin: 'https://courier.test' },
    });
    return { menu, attributes, animations, queries, events, focused: () => focused,
        open: () => handlers.get('trigger:click')(),
        close: () => handlers.get('close:click')(),
        click: (link, overrides = {}) => handlers.get('click')({ target: { closest: () => link }, button: 0, ...overrides }),
        escape: () => { let prevented = false; handlers.get('cancel')({ preventDefault: () => { prevented = true; } }); return prevented; },
    };
}
const flush = async () => { await Promise.resolve(); await Promise.resolve(); };

test('menu opens once and closes after its animation, restoring focus without scrolling', async () => {
    const app = client();
    app.open(); app.open();
    assert.equal(app.animations.length, 1);
    assert.equal(app.menu.open, true);
    assert.equal(app.attributes.get('aria-expanded'), 'true');
    app.close(); app.close();
    assert.equal(app.animations.length, 2);
    assert.equal(app.menu.open, true);
    app.animations.at(-1).finish(); await flush();
    assert.equal(app.menu.open, false);
    assert.equal(app.focused(), 1);
    app.open();
    assert.equal(app.attributes.get('aria-expanded'), 'true');
});

test('Escape animates closure and reduced motion or missing animation support closes immediately', async () => {
    const app = client(); app.open();
    assert.equal(app.escape(), true);
    app.animations.at(-1).finish(); await flush();
    assert.equal(app.menu.open, false);
    for (const options of [{ reduced: true }, { animated: false }]) {
        const fallback = client(options); fallback.open(); fallback.close();
        assert.equal(fallback.menu.open, false);
        assert.equal(fallback.animations.length, 0);
    }
});

test('menu navigation closes before page capture without stealing focus; modified links stay native', () => {
    const app = client(); app.open();
    const link = { href: 'https://courier.test/customers', hasAttribute: () => false, target: '' };
    app.click(link, { ctrlKey: true });
    assert.equal(app.menu.open, true);
    app.click(link);
    assert.equal(app.menu.open, false);
    assert.equal(app.focused(), 0);
});

test('back-forward cache and resizing discard an in-flight closing animation', async () => {
    for (const name of ['pagehide', 'pageshow', 'resize']) {
        const app = client(); app.open(); app.close();
        if (name === 'resize') app.queries.get('(min-width: 781px)')({ matches: true });
        else app.events.get(name)();
        app.animations.at(-1).finish(); await flush();
        assert.equal(app.menu.open, false);
        assert.equal(app.attributes.get('aria-expanded'), 'false');
        assert.equal(app.attributes.has('data-closing'), false);
        assert.equal(app.focused(), 0);
        app.open();
        assert.equal(app.menu.open, true);
    }
});
