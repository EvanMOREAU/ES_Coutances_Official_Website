import { Controller } from '@hotwired/stimulus';

/**
 * Tailles d'un article (formulaire boutique) : ajout d'une taille, raccourcis
 * « S → XXXL » / « Taille unique », retrait. Le gabarit d'une ligne vient de
 * data-article-variantes-prototype-value (« __name__ » = numéro unique).
 */
export default class extends Controller {
    static targets = ['list', 'item', 'empty'];
    static values = { prototype: String, index: Number };

    add() {
        const row = this.append('', 0);
        row.querySelector('input[name$="[libelle]"]')?.focus();
    }

    /** Ajoute les tailles proposées qui n'existent pas déjà (stock à 0, à renseigner ensuite). */
    preset({ params: { sizes } }) {
        const existing = this.itemTargets.map((item) => item.querySelector('input[name$="[libelle]"]').value.trim().toLowerCase());
        let first = null;
        sizes.forEach((size) => {
            if (!existing.includes(size.toLowerCase())) {
                const row = this.append(size, 0);
                first ??= row;
            }
        });
        first?.querySelector('input[name$="[stock]"]')?.focus();
    }

    remove(event) {
        event.currentTarget.closest('[data-article-variantes-target="item"]')?.remove();
        this.refresh();
    }

    append(label, stock) {
        const holder = document.createElement('div');
        holder.innerHTML = this.prototypeValue.replaceAll('__name__', String(this.indexValue)).trim();
        this.indexValue += 1;

        const row = holder.firstElementChild;
        row.querySelector('input[name$="[libelle]"]').value = label;
        row.querySelector('input[name$="[stock]"]').value = String(stock);
        this.listTarget.appendChild(row);
        this.refresh();

        return row;
    }

    refresh() {
        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = this.itemTargets.length > 0;
        }
    }
}
