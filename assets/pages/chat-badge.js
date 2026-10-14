(() => {
    const url = document.currentScript.dataset.url;
    let timer = null;
    let failures = 0;
    const tick = async () => {
        clearTimeout(timer);
        if (document.querySelector('[data-controller~="chat"]')) {
            return; // la messagerie ouverte gère déjà ses pastilles
        }
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (response.status === 401 || response.status === 403) {
                return; // session terminée : on s'arrête
            }
            const { unread } = await response.json();
            document.querySelectorAll('[data-chat-badge]').forEach((badge) => {
                badge.textContent = unread > 99 ? '99+' : String(unread);
                badge.hidden = unread === 0;
            });
            failures = 0;
        } catch {
            failures += 1;
        }
        timer = setTimeout(tick, Math.min((document.hidden ? 60000 : 8000) * (1 + failures), 120000));
    };
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { tick(); } });
    timer = setTimeout(tick, 4000);
})();
