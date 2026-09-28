import { Controller } from '@hotwired/stimulus';

/**
 * Liste de sous-formulaires Symfony (CollectionType) : ajout et retrait de
 * lignes. Le gabarit d'une nouvelle ligne vient de data-collection-prototype-value,
 * où « __name__ » est remplacé par un numéro unique.
 */
export default class extends Controller {
    static targets = ['list', 'item'];
    static values = { prototype: String, index: Number };

    add() {
        const html = this.prototypeValue.replaceAll('__name__', String(this.indexValue));
        this.indexValue += 1;

        const holder = document.createElement('div');
        holder.innerHTML = html.trim();
        const row = holder.firstElementChild;
        this.listTarget.appendChild(row);
        row.querySelector('input[type="text"]')?.focus();
    }

    remove(event) {
        event.target.closest('[data-collection-target="item"]')?.remove();
    }
}
