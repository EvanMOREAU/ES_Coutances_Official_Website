import { Controller } from '@hotwired/stimulus';

/**
 * Réorganisation des lignes d'un tableau par glisser-déposer (poignée
 * .dt-drag-handle) : l'ordre visuel après le dépôt est envoyé à l'URL
 * indiquée, puis la page est rechargée pour resynchroniser data-table
 * (qui fige l'ordre initial des lignes à sa connexion).
 */
export default class extends Controller {
    static targets = ['row'];
    static values = { url: String, token: String };

    start(event) {
        this.dragging = event.target.closest('tr');
        this.dragging?.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
    }

    end() {
        this.dragging?.classList.remove('is-dragging');
        this.dragging = null;
    }

    over(event) {
        event.preventDefault();
        const row = event.currentTarget;
        if (!this.dragging || this.dragging === row) {
            return;
        }
        const rect = row.getBoundingClientRect();
        const before = event.clientY - rect.top < rect.height / 2;
        row.parentNode.insertBefore(this.dragging, before ? row : row.nextSibling);
    }

    drop(event) {
        event.preventDefault();
        this.save();
    }

    save() {
        const ids = this.rowTargets.map((row) => row.dataset.id);

        fetch(this.urlValue, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _token: this.tokenValue, ids }),
            credentials: 'same-origin',
        }).then(() => window.location.reload());
    }
}
