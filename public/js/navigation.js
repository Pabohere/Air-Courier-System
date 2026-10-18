(() => {
    const root = document.documentElement;
    const busyElements = new Map();
    let submittedForms = new WeakSet();
    let resetTimer;
    const reset = () => {
        clearTimeout(resetTimer);
        root.removeAttribute('data-navigation-pending');
        busyElements.forEach((previous, element) => {
            element.removeAttribute('data-navigation-busy');
            if (previous === null) element.removeAttribute('aria-busy');
            else element.setAttribute('aria-busy', previous);
        });
        busyElements.clear();
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
        if (url.href === location.href && (link.closest('.mobile-nav') || link.closest('.mobile-menu-links'))) {
            event.preventDefault();
            return;
        }
        const mobileLink = link.closest('.mobile-nav') || link.closest('.mobile-menu-links');
        if (link.hasAttribute('data-navigation-busy') || (mobileLink && root.hasAttribute('data-navigation-pending'))) {
            event.preventDefault();
            return;
        }
        queueMicrotask(() => { if (!event.defaultPrevented) pending(link); });
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
    window.addEventListener('pageshow', reset);
    window.addEventListener('pagehide', reset);
})();
