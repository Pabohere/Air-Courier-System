(() => {
    const root = document.documentElement;
    const busyElements = new Map();
    let submittedForms = new WeakSet();
    let resetTimer;
    let pendingDestination;
    let updateIndicator = () => {};
    const reset = (realign = true) => {
        clearTimeout(resetTimer);
        root.removeAttribute('data-navigation-pending');
        busyElements.forEach((previous, element) => {
            element.removeAttribute('data-navigation-busy');
            if (previous === null) element.removeAttribute('aria-busy');
            else element.setAttribute('aria-busy', previous);
        });
        busyElements.clear();
        pendingDestination = null;
        if (realign) updateIndicator();
        submittedForms = new WeakSet();
    };
    const pending = (element) => {
        if (!busyElements.has(element)) busyElements.set(element, element.getAttribute('aria-busy'));
        element.setAttribute('data-navigation-busy', '');
        element.setAttribute('aria-busy', 'true');
        root.setAttribute('data-navigation-pending', '');
        clearTimeout(resetTimer);
        resetTimer = setTimeout(reset, 15000);
    };
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || !['http:', 'https:'].includes(url.protocol) || url.hash) return;
        if (!link.matches('.mobile-nav a, .mobile-menu-links a, .sidebar nav a, .mobile-brand, .brand, a.button, a.metric, .home-branch-picker a')) return;
        // Re-tapping the current mobile destination should not reload the page.
        if (!pendingDestination && url.href === location.href && (link.closest('.mobile-nav') || link.closest('.mobile-menu-links'))) {
            event.preventDefault();
            return;
        }
        if (pendingDestination === url.href || link.hasAttribute('data-navigation-busy')) {
            event.preventDefault();
            return;
        }
        queueMicrotask(() => {
            if (event.defaultPrevented) return;
            reset(false);
            pendingDestination = url.href;
            pending(link);
            updateIndicator(true);
        });
    });
    document.addEventListener('submit', (event) => {
        const form = event.target;
        const target = event.submitter?.formTarget || form.target;
        if (event.defaultPrevented || form.method.toLowerCase() === 'dialog' || (target && target !== '_self')) return;
        if (submittedForms.has(form)) {
            event.preventDefault();
            return;
        }
        // Run after confirmation/offline/custom validation handlers. Keep the submitter
        // enabled so its name/value (locale, payment action, etc.) reaches the server.
        queueMicrotask(() => {
            if (event.defaultPrevented) return;
            submittedForms.add(form);
            pending(event.submitter || form);
        });
    });
    const nav = document.querySelector('.mobile-nav');
    if (nav) {
        const items = [...nav.querySelectorAll(':scope > a, :scope > button')];
        const indicator = document.createElement('span');
        indicator.className = 'mobile-nav-indicator';
        indicator.setAttribute('aria-hidden', 'true');
        nav.append(indicator);
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let animation;
        let geometry;
        let frame;
        updateIndicator = (animate = false) => {
            const selected = items.find((item) => item.getAttribute('aria-expanded') === 'true')
                || items.find((item) => item.hasAttribute('data-navigation-busy'))
                || items.find((item) => item.getAttribute('aria-current') === 'page')
                || items.at(-1);
            if (!selected) return;
            const container = nav.getBoundingClientRect();
            const box = selected.getBoundingClientRect();
            if (!box.width || !box.height) return;
            root.style.setProperty('--tab-bar-height', `${Math.ceil(container.height)}px`);
            const next = { x: box.left - container.left - nav.clientLeft, y: box.top - container.top - nav.clientTop, width: box.width, height: box.height };
            items.forEach((item) => item.toggleAttribute('data-mobile-selected', item === selected));
            if (geometry && Object.keys(next).every((key) => next[key] === geometry[key])) return;
            const previous = indicator.getBoundingClientRect();
            animation?.cancel();
            Object.assign(indicator.style, {
                transform: `translateX(${next.x}px)`, top: `${next.y}px`,
                width: `${next.width}px`, height: `${next.height}px`,
            });
            if (animate && geometry && !reducedMotion.matches && indicator.animate) {
                const x = previous.left - container.left - nav.clientLeft;
                const stretch = Math.max(previous.width, next.width) * 1.18;
                animation = indicator.animate([
                    { transform: `translateX(${x}px)`, width: `${previous.width}px`, borderRadius: '14px' },
                    { transform: `translateX(${(x + next.x) / 2 - (stretch - next.width) / 2}px)`, width: `${stretch}px`, borderRadius: '20px', offset: .45 },
                    { transform: `translateX(${next.x}px)`, width: `${next.width}px`, borderRadius: '14px' },
                ], { duration: 280, easing: 'cubic-bezier(.22, 1, .36, 1)' });
            }
            geometry = next;
            nav.setAttribute('data-indicator-ready', '');
        };
        const schedule = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => updateIndicator());
        };
        new MutationObserver(() => updateIndicator(true)).observe(nav, {
            subtree: true, attributes: true, attributeFilter: ['aria-current', 'aria-expanded', 'data-navigation-busy'],
        });
        if (window.ResizeObserver) {
            const observer = new ResizeObserver(schedule);
            observer.observe(nav);
            items.forEach((item) => observer.observe(item));
        }
        window.addEventListener('resize', schedule);
        window.addEventListener('orientationchange', schedule);
        reducedMotion.addEventListener('change', () => {
            animation?.cancel();
            updateIndicator();
        });
        updateIndicator();
    }
    window.addEventListener('pageshow', reset);
    window.addEventListener('pagehide', reset);
})();
