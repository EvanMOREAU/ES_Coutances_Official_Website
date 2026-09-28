import { Controller } from '@hotwired/stimulus';
import { adminConfirm } from '../modal.js';

/**
 * Notifications du back-office (clochette et page « Notifications ») :
 * marquer comme lue, supprimer, tout marquer / tout supprimer.
 * Les notifications sont mises à jour sans recharger la page ; le serveur
 * renvoie le nombre de non lues, qui met à jour les pastilles de la clochette.
 */
export default class extends Controller {
    static targets = ['item', 'list', 'empty', 'bulk'];
    static values = { readUrl: String, dismissUrl: String, token: String };

    async read(event) {
        event.preventDefault();
        event.stopPropagation(); // garde la clochette ouverte
        const item = event.currentTarget.closest('[data-notifications-target="item"]');
        if (!item || item.dataset.read === '1') {
            return;
        }
        this.markItemRead(item);
        await this.send(this.readUrlValue, { keys: [item.dataset.key] });
    }

    async dismiss(event) {
        event.preventDefault();
        event.stopPropagation();
        const item = event.currentTarget.closest('[data-notifications-target="item"]');
        if (!item) {
            return;
        }
        item.classList.add('is-leaving');
        const result = await this.send(this.dismissUrlValue, { keys: [item.dataset.key] });
        if (result) {
            item.remove();
            this.refreshEmpty();
        } else {
            item.classList.remove('is-leaving');
        }
    }

    async readAll(event) {
        event?.stopPropagation();
        this.itemTargets.forEach((item) => this.markItemRead(item));
        await this.send(this.readUrlValue, { all: true });
    }

    async dismissAll() {
        const ok = await adminConfirm({
            title: 'Supprimer toutes les notifications ?',
            message: 'Elles disparaîtront de la liste. De nouvelles notifications apparaîtront au fil de l\'activité du club.',
            confirmLabel: 'Tout supprimer',
            danger: true,
        });
        if (!ok) {
            return;
        }
        const result = await this.send(this.dismissUrlValue, { all: true });
        if (result) {
            this.itemTargets.forEach((item) => item.remove());
            this.refreshEmpty();
        }
    }

    /** Clic sur une notification : on la marque comme lue puis le lien s'ouvre normalement. */
    open(event) {
        const item = event.currentTarget.closest('[data-notifications-target="item"]');
        if (item && item.dataset.read !== '1') {
            this.markItemRead(item);
            // keepalive : la requête part même si la page change aussitôt.
            this.send(this.readUrlValue, { keys: [item.dataset.key] }, true);
        }
    }

    markItemRead(item) {
        item.dataset.read = '1';
        item.classList.add('is-read');
    }

    refreshEmpty() {
        const empty = this.itemTargets.length === 0;
        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = !empty;
        }
        if (this.hasBulkTarget) {
            this.bulkTarget.hidden = empty;
        }
    }

    async send(url, body, keepalive = false) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.tokenValue, Accept: 'application/json' },
                body: JSON.stringify(body),
                keepalive,
            });
            if (!response.ok) {
                return null;
            }
            const result = await response.json();
            this.updateBadges(result.unread);

            return result;
        } catch (error) {
            return null;
        }
    }

    updateBadges(unread) {
        document.querySelectorAll('[data-notif-badge]').forEach((badge) => {
            badge.hidden = unread === 0;
            const value = badge.querySelector('[data-notif-badge-value]');
            if (value) {
                value.textContent = unread;
            }
        });
        document.querySelectorAll('[data-notif-count]').forEach((count) => {
            count.hidden = unread === 0;
            const value = count.querySelector('[data-notif-count-value]');
            if (value) {
                value.textContent = unread;
            }
            const plural = count.querySelector('[data-notif-plural]');
            if (plural) {
                plural.textContent = unread > 1 ? 's' : '';
            }
        });
    }
}
