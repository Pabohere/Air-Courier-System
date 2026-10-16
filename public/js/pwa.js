(() => {
    const script = document.currentScript;
    const buttons = document.querySelectorAll('[data-pwa-install]');
    const help = document.querySelector('[data-pwa-help]');
    const offline = document.querySelector('[data-pwa-offline]');
    const standalone = window.matchMedia('(display-mode: standalone)');
    let installPrompt = null;
    let workerReady = false;
    const updateButtons = () => {
        const installed = standalone.matches || navigator.standalone === true;
        buttons.forEach((button) => { button.hidden = installed || !window.isSecureContext || !workerReady; });
    };
    const updateConnection = () => { if (offline) offline.hidden = navigator.onLine; };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event;
        updateButtons();
    });
    window.addEventListener('appinstalled', () => {
        installPrompt = null;
        buttons.forEach((button) => { button.hidden = true; });
    });
    standalone.addEventListener('change', updateButtons);
    buttons.forEach((button) => button.addEventListener('click', async () => {
        if (!installPrompt) {
            help?.showModal();
            return;
        }
        const prompt = installPrompt;
        installPrompt = null;
        button.disabled = true;
        try {
            await prompt.prompt();
            const choice = await prompt.userChoice;
            if (choice.outcome === 'accepted') buttons.forEach((item) => { item.hidden = true; });
        } catch {
            help?.showModal();
        } finally {
            button.disabled = false;
        }
    }));
    document.querySelectorAll('[data-pwa-help-close]').forEach((button) => button.addEventListener('click', () => help.close()));
    help?.addEventListener('click', (event) => { if (event.target === help) help.close(); });
    window.addEventListener('online', updateConnection);
    window.addEventListener('offline', updateConnection);
    document.addEventListener('submit', (event) => {
        if (!navigator.onLine && event.target.method.toLowerCase() === 'post') {
            event.preventDefault();
            event.stopImmediatePropagation();
            updateConnection();
            offline?.scrollIntoView({ block: 'nearest' });
        }
    }, true);
    updateButtons();
    updateConnection();

    if ('serviceWorker' in navigator && window.isSecureContext && script?.dataset.workerUrl) {
        const worker = new URL(script.dataset.workerUrl, location.href);
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(worker.href, { scope: new URL('./', worker).pathname, updateViaCache: 'none' }).then(() => navigator.serviceWorker.ready).then(() => {
                workerReady = true;
                document.documentElement.dataset.pwaState = 'ready';
                updateButtons();
            }).catch(() => {
                document.documentElement.dataset.pwaState = 'unavailable';
                // The normal online app remains available if installation is unsupported.
            });
        });
    }
})();
