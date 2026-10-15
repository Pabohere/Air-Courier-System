(() => {
    document.querySelectorAll('[data-branch-clock]').forEach((clock) => {
        const initialTime = Date.parse(clock.dateTime);
        if (!Number.isFinite(initialTime)) return;
        const startedAt = performance.now();
        const formatter = new Intl.DateTimeFormat('en-GB', {
            timeZone: clock.dataset.timezone,
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
        });
        const update = () => {
            const current = new Date(initialTime + performance.now() - startedAt);
            clock.textContent = formatter.format(current);
            clock.dateTime = current.toISOString();
        };
        update();
        setInterval(update, 1000);
    });
})();
