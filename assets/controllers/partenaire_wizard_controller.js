import { Controller } from '@hotwired/stimulus';

/**
 * Création guidée d'un partenaire : navigation Suivant/Précédent entre les étapes
 * (partenaire, contrat, règlements, tâches, notes), plus ajout/retrait des lignes
 * de règlement et de tâche via le pattern standard data-prototype (comme le
 * formulaire de contrat, contrat-partenaire-form).
 */
export default class extends Controller {
    static targets = ['step', 'indicator', 'backButton', 'nextButton', 'submitButton',
        'reglements', 'reglementsEmpty', 'reglement', 'taches', 'tachesEmpty', 'tache'];
    static values = {
        reglementPrototype: String,
        tachePrototype: String,
        reglementIndex: Number,
        tacheIndex: Number,
    };

    connect() {
        this.current = 0;
        this.showStep(0);
    }

    /**
     * Empêche la touche Entrée de soumettre le formulaire (donc de terminer la
     * création) tant qu'on n'est pas sur la dernière étape : elle passe à
     * l'étape suivante à la place, comme le ferait le bouton « Suivant ».
     */
    onEnter(event) {
        if ('TEXTAREA' === event.target.tagName) {
            return;
        }
        if (this.current < this.stepTargets.length - 1) {
            event.preventDefault();
            this.next();
        }
    }

    next() {
        const currentStepEl = this.stepTargets[this.current];
        const inputs = currentStepEl.querySelectorAll('input, select, textarea');
        for (const input of inputs) {
            if (!input.reportValidity()) {
                return;
            }
        }

        if (this.current < this.stepTargets.length - 1) {
            this.showStep(this.current + 1);
        }
    }

    back() {
        if (this.current > 0) {
            this.showStep(this.current - 1);
        }
    }

    showStep(index) {
        this.current = index;
        const isLast = index === this.stepTargets.length - 1;

        this.stepTargets.forEach((el, i) => el.classList.toggle('hidden', i !== index));
        this.indicatorTargets.forEach((el, i) => {
            el.classList.toggle('admin-gradient', i <= index);
            el.classList.toggle('text-white', i <= index);
            el.classList.toggle('bg-admin-surface', i > index);
            el.classList.toggle('text-admin-text-muted', i > index);
        });

        this.backButtonTarget.classList.toggle('invisible', index === 0);
        this.nextButtonTarget.classList.toggle('hidden', isLast);
        this.submitButtonTarget.classList.toggle('hidden', !isLast);
    }

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
