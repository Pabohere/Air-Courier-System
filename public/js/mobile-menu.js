(() => {
    const menu = document.querySelector('.mobile-menu');
    if (!menu) return;
    const triggers = document.querySelectorAll('[data-mobile-menu-open]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let trigger;
    let animation;
    let closing = false;
    let restoreFocus = true;

    const expanded = (value) => triggers.forEach((button) => button.setAttribute('aria-expanded', String(value)));
    const cancelAnimation = () => {
        animation?.cancel();
        animation = null;
    };
    const close = (immediate = false, focus = true) => {
        if (!menu.open) return;
        if (closing && !immediate) return;
        restoreFocus = focus;
        cancelAnimation();
        expanded(false);
        if (immediate || reducedMotion.matches || !menu.animate) {
            menu.close();
            return;
        }
        closing = true;
        menu.setAttribute('data-closing', '');
        const exit = menu.animate([
            { transform: 'translateY(0)', opacity: 1 },
            { transform: 'translateY(28px)', opacity: 0 },
        ], { duration: 160, easing: 'cubic-bezier(.4, 0, 1, 1)', fill: 'forwards' });
        animation = exit;
        exit.finished.then(() => {
            if (animation === exit) menu.close();
        }).catch(() => {});
    };
    triggers.forEach((button) => button.addEventListener('click', () => {
        if (menu.open) return;
        trigger = button;
        restoreFocus = true;
        closing = false;
        menu.removeAttribute('data-closing');
        menu.showModal();
        expanded(true);
        if (!reducedMotion.matches && menu.animate) {
            animation = menu.animate([
                { transform: 'translateY(28px)', opacity: 0 },
                { transform: 'translateY(0)', opacity: 1 },
            ], { duration: 220, easing: 'cubic-bezier(.22, 1, .36, 1)' });
            animation.finished.catch(() => {});
        }
    }));
    document.querySelector('[data-mobile-menu-close]')?.addEventListener('click', () => close());
    menu.addEventListener('cancel', (event) => {
        event.preventDefault();
        close();
    });
    menu.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (link && !event.defaultPrevented && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey && !link.hasAttribute('download') && (!link.target || link.target === '_self')) {
            const url = new URL(link.href, location.href);
            if (url.origin === location.origin && !url.hash) {
                if (url.href === location.href) {
                    event.preventDefault();
                    close();
                } else {
                    close(true, false);
                }
            }
        }
        if (event.target !== menu) return;
        const box = menu.getBoundingClientRect();
        if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) close();
    });
    menu.addEventListener('close', () => {
        cancelAnimation();
        closing = false;
        menu.removeAttribute('data-closing');
        expanded(false);
        if (restoreFocus) trigger?.focus({ preventScroll: true });
    });
    const reset = () => close(true, false);
    window.addEventListener('pagehide', reset);
    window.addEventListener('pageshow', reset);
    window.matchMedia('(min-width: 781px)').addEventListener('change', (event) => {
        if (event.matches) reset();
    });
    reducedMotion.addEventListener('change', () => {
        if (reducedMotion.matches && closing) close(true);
        else if (reducedMotion.matches) cancelAnimation();
    });
})();
