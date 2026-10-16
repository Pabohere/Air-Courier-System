(() => {
    const storageKey = 'courier-theme-calm';
    let theme = 'light';
    try {
        const saved = localStorage.getItem(storageKey);
        if (saved === 'light' || saved === 'dark') theme = saved;
    } catch {}

    const applyTheme = (value) => {
        document.documentElement.dataset.theme = value;
        document.querySelectorAll('[data-theme-choice]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.themeChoice === value));
        });
    };
    applyTheme(theme);
    document.addEventListener('DOMContentLoaded', () => {
        applyTheme(theme);
        document.querySelectorAll('[data-theme-choice]').forEach((button) => {
            button.addEventListener('click', () => {
                theme = button.dataset.themeChoice;
                applyTheme(theme);
                try { localStorage.setItem(storageKey, theme); } catch {}
            });
        });
    });
    window.addEventListener('storage', (event) => {
        if (event.key === storageKey) {
            theme = event.newValue === 'dark' ? 'dark' : 'light';
            applyTheme(theme);
        }
    });
})();
