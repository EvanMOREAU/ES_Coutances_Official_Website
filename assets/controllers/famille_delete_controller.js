import { Controller } from '@hotwired/stimulus';

const escapeHtml = (text) => text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/**
 * Suppression d'une famille qui contient des licenciés : une fenêtre liste les
 * licenciés, qu'il faut supprimer un par un (chacun avec sa propre
 * confirmation). Le bouton "Supprimer la famille" ne s'active qu'une fois
 * la famille vide.
 */
export default class extends Controller {
    static values = {
        familleName: String,
        deleteUrl: String,
        deleteToken: String,
        licencies: Array, // [{ id, name, meta, url, token }]
    };

    open() {
        this.removedAny = false;
        this.remaining = this.licenciesValue.length;

        this.dialog = document.createElement('dialog');
        this.dialog.className = 'admin-modal admin-modal-wide';
        this.dialog.innerHTML = `
            <div class="admin-modal-body">
                <div class="admin-modal-icon is-danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <h2 class="admin-modal-title">Supprimer la famille « ${escapeHtml(this.familleNameValue)} »</h2>
                <p class="admin-modal-text" data-role="intro"></p>
                <ul class="fd-list" data-role="list"></ul>
                <p class="fd-message" data-role="message" role="status" hidden></p>
                <form method="post" action="${escapeHtml(this.deleteUrlValue)}" class="admin-modal-actions">
                    <input type="hidden" name="_token" value="${escapeHtml(this.deleteTokenValue)}">
                    <button type="button" data-role="close" class="admin-btn admin-btn-ghost">Fermer</button>
                    <button type="submit" data-role="submit" class="admin-btn admin-btn-danger" disabled>Supprimer la famille</button>
                </form>
            </div>`;

        const list = this.role('list');
        this.licenciesValue.forEach((licencie) => list.appendChild(this.buildRow(licencie)));

        this.role('close').addEventListener('click', () => this.closeDialog());
        this.dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            this.closeDialog();
        });
        this.dialog.addEventListener('click', (event) => {
            if (event.target === this.dialog) {
                this.closeDialog();
            }
        });

        document.body.appendChild(this.dialog);
        this.updateState();
        this.dialog.showModal();
    }

    closeDialog() {
        if (!this.dialog) {
            return;
        }
        this.dialog.close();
        this.dialog.remove();
        this.dialog = null;
        // La liste derrière la fenêtre est périmée si des licenciés ont été supprimés.
        if (this.removedAny) {
            window.location.reload();
        }
    }

    role(name) {
        return this.dialog.querySelector(`[data-role="${name}"]`);
    }

    buildRow(licencie) {
        const li = document.createElement('li');
        li.className = 'fd-row';
        li.innerHTML = `
            <div class="fd-row-main">
                <span class="fd-name">${escapeHtml(licencie.name)}</span>
                <span class="fd-meta">${escapeHtml(licencie.meta || '')}</span>
            </div>
            <div class="fd-row-actions">
                <button type="button" class="fd-trash" data-role="ask" title="Supprimer ce licencié" aria-label="Supprimer ${escapeHtml(licencie.name)}"><i class="fa-solid fa-trash"></i></button>
                <span class="fd-confirm" hidden>
                    <span class="fd-confirm-text">Supprimer définitivement&nbsp;?</span>
                    <button type="button" data-role="yes" class="admin-btn admin-btn-danger admin-btn-sm">Oui, supprimer</button>
                    <button type="button" data-role="no" class="admin-btn admin-btn-ghost admin-btn-sm">Annuler</button>
                </span>
            </div>`;

        const ask = li.querySelector('[data-role="ask"]');
        const confirm = li.querySelector('.fd-confirm');
        const yes = li.querySelector('[data-role="yes"]');

        ask.addEventListener('click', () => {
            ask.hidden = true;
            confirm.hidden = false;
            yes.focus();
        });
        li.querySelector('[data-role="no"]').addEventListener('click', () => {
            confirm.hidden = true;
            ask.hidden = false;
        });
        yes.addEventListener('click', () => this.deleteLicencie(licencie, li, yes));

        return li;
    }

    async deleteLicencie(licencie, li, button) {
        button.disabled = true;
        button.classList.add('is-loading');
        try {
            const body = new FormData();
            body.append('_token', licencie.token);
            const response = await fetch(licencie.url, {
                method: 'POST',
                body,
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.ok) {
                throw new Error(result.message || 'La suppression a échoué.');
            }

            this.removedAny = true;
            this.remaining -= 1;
            li.classList.add('is-removed');
            setTimeout(() => { li.remove(); this.updateState(); }, 220);
            this.say(result.message || 'Licencié supprimé.', false);
        } catch (error) {
            button.disabled = false;
            button.classList.remove('is-loading');
            this.say(error.message, true);
        }
    }

    say(text, isError) {
        const message = this.role('message');
        message.hidden = false;
        message.textContent = text;
        message.classList.toggle('is-error', isError);
    }

    updateState() {
        const empty = this.remaining === 0;
        this.role('submit').disabled = !empty;
        this.role('intro').textContent = empty
            ? 'Tous les licenciés ont été supprimés. Vous pouvez maintenant supprimer la famille.'
            : `Cette famille contient ${this.remaining} licencié${this.remaining > 1 ? 's' : ''}. Supprimez-les un par un pour pouvoir supprimer la famille.`;
    }
}
