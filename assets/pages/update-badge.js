(() => {
    const url = document.currentScript.dataset.url;
    let timer = null;
    let failures = 0;
    const tick = async () => {
        clearTimeout(timer);
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (response.status === 401 || response.status === 403) {
                return; // session terminée : on s'arrête
            }
            const { pending } = await response.json();
            document.querySelectorAll('[data-update-badge]').forEach((badge) => {
                badge.textContent = pending > 99 ? '99+' : String(pending);
                badge.hidden = pending === 0;
            });
            document.querySelectorAll('[data-update-dot]').forEach((dot) => {
                dot.hidden = pending === 0;
            });
            failures = 0;
        } catch {
            failures += 1;
        }
        timer = setTimeout(tick, Math.min((document.hidden ? 300000 : 60000) * (1 + failures), 900000));
    };
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { tick(); } });
    timer = setTimeout(tick, 5000);
})();
