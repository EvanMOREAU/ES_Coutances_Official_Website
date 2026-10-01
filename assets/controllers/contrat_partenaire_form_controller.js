import { Controller } from '@hotwired/stimulus';

/**
 * Formulaire d'un contrat partenaire : ajout / retrait des règlements échelonnés et des
 * tâches à réaliser. Les gabarits des lignes viennent des prototypes Symfony
 * (« __name__ » = numéro unique), comme pour le formulaire de licence (adhesion-form).
 */
export default class extends Controller {
    static targets = ['reglements', 'reglementsEmpty', 'reglement', 'taches', 'tachesEmpty', 'tache'];
    static values = {
        reglementPrototype: String,
        tachePrototype: String,
        reglementIndex: Number,
        tacheIndex: Number,
    };

    addReglement() {
        return this.append('reglement', this.reglementsTarget);
    }

    addTache() {
        return this.append('tache', this.tachesTarget);
    }

    /** Règlement encaissé : la date d'encaissement est préremplie avec celle du jour. */
    received(event) {
        const input = event.target;
        if (!input.matches('input[name$="[recu]"]') || !input.checked) {
            return;
        }
        const date = input.closest('.ad-row')?.querySelector('input[name$="[dateRemise]"]');
        if (date && !date.value) {
            date.value = new Date().toISOString().slice(0, 10);
            date.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    remove(event) {
        event.currentTarget.closest('.ad-row')?.remove();
        this.refresh();
    }

    append(kind, holder) {
        const prototype = kind === 'reglement' ? this.reglementPrototypeValue : this.tachePrototypeValue;
        const indexKey = kind === 'reglement' ? 'reglementIndexValue' : 'tacheIndexValue';
        const wrapper = document.createElement('div');
        wrapper.innerHTML = prototype.replaceAll('__name__', String(this[indexKey])).trim();
        this[indexKey] += 1;
        const row = wrapper.firstElementChild;
        holder.appendChild(row);
        this.refresh();

        return row;
    }

    refresh() {
        if (this.hasReglementsEmptyTarget) {
            this.reglementsEmptyTarget.hidden = this.reglementTargets.length > 0;
        }
        if (this.hasTachesEmptyTarget) {
            this.tachesEmptyTarget.hidden = this.tacheTargets.length > 0;
        }
    }
}
