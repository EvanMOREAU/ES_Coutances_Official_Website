/**
 * Fenêtre de confirmation du back-office (remplace window.confirm).
 *
 *   const ok = await adminConfirm({ title, message, confirmLabel, danger });
 *
 * Les formulaires portant data-confirm="..." passent automatiquement par elle
 * (data-confirm-title, data-confirm-label pour personnaliser).
 */
export function adminConfirm({ title = 'Confirmer', message = '', confirmLabel = 'Confirmer', cancelLabel = 'Annuler', danger = false } = {}) {
    return new Promise((resolve) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'admin-modal';
        dialog.innerHTML = `
            <div class="admin-modal-body">
                <div class="admin-modal-icon ${danger ? 'is-danger' : ''}"><i class="fa-solid ${danger ? 'fa-triangle-exclamation' : 'fa-circle-question'}"></i></div>
                <h2 class="admin-modal-title"></h2>
                <p class="admin-modal-text"></p>
                <div class="admin-modal-actions">
                    <button type="button" data-role="cancel" class="admin-btn admin-btn-ghost"></button>
                    <button type="button" data-role="ok" class="admin-btn ${danger ? 'admin-btn-danger' : 'admin-btn-primary'}"></button>
                </div>
            </div>`;
        dialog.querySelector('.admin-modal-title').textContent = title;
        dialog.querySelector('.admin-modal-text').textContent = message;
        dialog.querySelector('[data-role="cancel"]').textContent = cancelLabel;
        dialog.querySelector('[data-role="ok"]').textContent = confirmLabel;

        let done = false;
        const finish = (result) => {
            if (done) {
                return;
            }
            done = true;
            dialog.close();
            dialog.remove();
            resolve(result);
        };

        dialog.querySelector('[data-role="ok"]').addEventListener('click', () => finish(true));
        dialog.querySelector('[data-role="cancel"]').addEventListener('click', () => finish(false));
        // Échap : on gère la fermeture nous-mêmes pour toujours résoudre la promesse.
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            finish(false);
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                finish(false);
            }
        });

        document.body.appendChild(dialog);
        dialog.showModal();
        dialog.querySelector('[data-role="ok"]').focus();
    });
}

/**
 * Fenêtre de saisie d'un texte (nom de dossier, nouveau nom…).
 *
 *   const name = await adminPrompt({ title, label, value, confirmLabel });   // null si annulé
 */
export function adminPrompt({ title = 'Saisie', label = '', value = '', placeholder = '', confirmLabel = 'Valider', cancelLabel = 'Annuler' } = {}) {
    return new Promise((resolve) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'admin-modal';
        dialog.innerHTML = `
            <form method="dialog" class="admin-modal-body" novalidate>
                <h2 class="admin-modal-title"></h2>
                <label class="admin-modal-field">
                    <span class="admin-modal-label"></span>
                    <input type="text" class="admin-modal-input" maxlength="150" autocomplete="off">
                </label>
                <div class="admin-modal-actions">
                    <button type="button" data-role="cancel" class="admin-btn admin-btn-ghost"></button>
                    <button type="submit" data-role="ok" class="admin-btn admin-btn-primary"></button>
                </div>
            </form>`;
        dialog.querySelector('.admin-modal-title').textContent = title;
        dialog.querySelector('.admin-modal-label').textContent = label;
        dialog.querySelector('[data-role="cancel"]').textContent = cancelLabel;
        dialog.querySelector('[data-role="ok"]').textContent = confirmLabel;
        const input = dialog.querySelector('input');
        input.value = value;
        input.placeholder = placeholder;

        let done = false;
        const finish = (result) => {
            if (done) {
                return;
            }
            done = true;
            dialog.close();
            dialog.remove();
            resolve(result);
        };

        dialog.querySelector('form').addEventListener('submit', (event) => {
            event.preventDefault();
            const text = input.value.trim();
            finish(text === '' ? null : text);
        });
        dialog.querySelector('[data-role="cancel"]').addEventListener('click', () => finish(null));
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            finish(null);
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                finish(null);
            }
        });

        document.body.appendChild(dialog);
        dialog.showModal();
        input.focus();
        // Pour un renommage, on sélectionne le nom sans l'extension.
        const dot = value.lastIndexOf('.');
        input.setSelectionRange(0, dot > 0 ? dot : value.length);
    });
}

export function initConfirmForms() {
    // Phase de capture : on passe avant le retour visuel "chargement" des boutons.
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') {
            return;
        }

        event.preventDefault();
        const ok = await adminConfirm({
            title: form.dataset.confirmTitle || 'Confirmer la suppression',
            message: form.dataset.confirm,
            confirmLabel: form.dataset.confirmLabel || 'Supprimer',
            danger: !form.hasAttribute('data-confirm-neutral'),
        });
        if (ok) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    }, true);
}
