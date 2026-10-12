import { Controller } from '@hotwired/stimulus';

/**
 * Création guidée d'un partenaire : navigation Suivant/Précédent entre les étapes
 * (partenaire, contrat, règlements, tâches, notes), plus ajout/retrait des lignes
 * de règlement et de tâche via le pattern standard data-prototype (comme le
 * formulaire de contrat, contrat-partenaire-form).
 */
export default class extends Controller {
    static targets = ['step', 'indicator', 'backButton', 'nextButton', 'submitButton',
        'reglements', 'reglementsEmpty', 'reglement', 'taches', 'tachesEmpty', 'tache', 'resteResume'];
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
     * La touche Entrée ne fait jamais avancer ni soumettre le wizard depuis un champ de saisie :
     * choisir une suggestion du navigateur (site web, nom…) la déclenche parfois, et faisait
     * sauter à l'étape « Contrat » sans l'avoir voulu. Seuls Précédent / Suivant font avancer.
     */
    onKeydown(event) {
        if (!(event instanceof KeyboardEvent) || 'Enter' !== event.key) {
            return;
        }
        const target = event.target;
        if (target instanceof HTMLInputElement && !['button', 'submit', 'checkbox', 'radio'].includes(target.type)) {
            event.preventDefault();
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

        if (this.stepTargets[index].contains(this.reglementsTarget)) {
            this.refreshResume();
        }
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
        this.refreshResume();
    }

    /** Répartit le montant du contrat (moins ce qui est déjà encaissé) en N échéances mensuelles. */
    split({ params: { parts } }) {
        const total = this.parse(this.contractAmountInput()?.value);
        if (total <= 0) {
            this.notify("Indiquez d'abord le montant du contrat (étape précédente).");

            return;
        }
        this.notify('');

        this.reglementTargets.forEach((row) => {
            if (!row.querySelector('input[type="checkbox"]')?.checked) {
                row.remove();
            }
        });
        const kept = this.reglementTargets.reduce((sum, row) => sum + this.rowAmount(row), 0);
        const cents = Math.round(Math.max(0, total - kept) * 100);
        if (cents <= 0) {
            this.notify('Le contrat est déjà entièrement couvert par les règlements encaissés.');
        } else {
            const each = Math.floor(cents / parts);
            const start = this.contractStart();
            for (let i = 0; i < parts; i++) {
                const row = this.addReglement();
                const value = i === parts - 1 ? cents - each * (parts - 1) : each;
                this.setField(row, 'mode', 'virement');
                this.setField(row, 'montantCentimes', (value / 100).toFixed(2).replace('.', ','));
                this.setField(row, 'dateEcheance', this.isoDate(new Date(start.getFullYear(), start.getMonth() + i, start.getDate())));
            }
        }
        this.refresh();
        this.refreshResume();
    }

    /** Rappel du montant du contrat et de ce qui est prévu, recalculé à chaque modification. */
    refreshResume() {
        if (!this.hasResteResumeTarget) {
            return;
        }
        const total = this.parse(this.contractAmountInput()?.value);
        const planned = this.reglementTargets.reduce((sum, row) => sum + this.rowAmount(row), 0);
        const ecart = total - planned;
        const format = (n) => `${n.toFixed(2).replace('.', ',')} €`;

        this.resteResumeTarget.innerHTML = `
            <span>Montant du contrat : <strong>${format(total)}</strong></span>
            <span>Règlements prévus : <strong>${format(planned)}</strong></span>
            <span>${ecart >= 0 ? 'Reste à planifier' : 'Dépassement'} : <strong>${format(Math.abs(ecart))}</strong></span>
        `;
        this.resteResumeTarget.classList.toggle('is-danger', planned > 0 && Math.abs(ecart) >= 0.005);
    }

    notify(message) {
        let note = this.element.querySelector('[data-split-note]');
        if (!note) {
            note = document.createElement('p');
            note.dataset.splitNote = '1';
            note.className = 'text-xs mb-3';
            note.style.color = 'var(--color-admin-danger)';
            this.reglementsTarget.before(note);
        }
        note.textContent = message;
    }

    /** Champ « montant du contrat » (étape 2), à ne pas confondre avec les montants des règlements. */
    contractAmountInput() {
        return this.stepTargets[1].querySelector('input[name$="[montantCentimes]"]');
    }

    /** Première échéance : début du contrat s'il est renseigné, sinon aujourd'hui. */
    contractStart() {
        const value = this.stepTargets[1].querySelector('input[name$="[dateDebut]"]')?.value;
        const date = value ? new Date(`${value}T00:00:00`) : new Date();

        return Number.isNaN(date.getTime()) ? new Date() : date;
    }

    isoDate(date) {
        const pad = (n) => String(n).padStart(2, '0');

        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }

    rowAmount(row) {
        return this.parse(row.querySelector('input[name$="[montantCentimes]"]')?.value);
    }

    parse(value) {
        const number = parseFloat(String(value ?? '').replace(/\s/g, '').replace(',', '.'));

        return Number.isNaN(number) ? 0 : number;
    }

    setField(row, field, value) {
        const input = row.querySelector(`[name$="[${field}]"]`);
        if (input) {
            input.value = value;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
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
