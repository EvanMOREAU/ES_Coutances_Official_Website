import { Controller } from '@hotwired/stimulus';

/**
 * Création guidée d'une licence, en 4 étapes : licence et montant, aides,
 * règlements, observations. Reprend la gestion des lignes « aide » / « règlement »
 * du formulaire classique (adhesion-form), et ajoute :
 *  - la navigation par étapes (comme le wizard partenaire) ;
 *  - le rappel du reste à régler (montant − aides) sur l'étape « Règlements »,
 *    recalculé à chaque passage sur cette étape (utile si on revient modifier
 *    une aide puis qu'on avance à nouveau) ;
 *  - le blocage de la progression si aides + règlements dépassent le montant dû ;
 *  - le choix entre un licencié existant et un simple nom (personne pas encore enregistrée).
 */
export default class extends Controller {
    static targets = ['step', 'indicator', 'backButton', 'nextButton', 'submitButton',
        'reglements', 'reglementsEmpty', 'reglement', 'aides', 'aidesEmpty', 'aide',
        'licencieField', 'licencieLabelField', 'resteResume'];
    static values = {
        reglementPrototype: String,
        aidePrototype: String,
        reglementIndex: Number,
        aideIndex: Number,
        reglementStep: Number,
    };

    connect() {
        this.current = 0;
        this.showStep(0);
        this.syncMode();
    }

    /** Empêche Entrée de soumettre le formulaire tant qu'on n'est pas sur la dernière étape. */
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
        if (0 === this.current) {
            const licencieOk = this.checkLicencieChosen(currentStepEl);
            const priceOk = this.checkLicencePrice(currentStepEl);
            if (!licencieOk || !priceOk) {
                return;
            }
        }
        if (this.current === this.reglementStepValue && !this.checkBudget()) {
            return;
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

        if (index === this.reglementStepValue) {
            this.updateResteResume();
        }
    }

    // --- Licencié existant / simple nom -----------------------------------

    toggleMode(event) {
        this.mode = event.params.mode;
        this.syncMode();
    }

    syncMode() {
        const mode = this.mode ?? (this.licencieLabelFieldTarget.querySelector('input').value ? 'label' : 'select');
        this.mode = mode;
        this.licencieFieldTarget.hidden = mode === 'label';
        this.licencieLabelFieldTarget.hidden = mode === 'select';
        this.element.querySelectorAll('[data-adhesion-wizard-mode-param]').forEach((button) => {
            button.classList.toggle('hidden', button.dataset.adhesionWizardModeParam === mode);
        });
        if (mode === 'label') {
            this.licencieFieldTarget.querySelector('select').value = '';
        } else {
            this.licencieLabelFieldTarget.querySelector('input').value = '';
        }
    }

    // --- Aides / règlements (lignes dynamiques) ---------------------------

    addReglement() {
        return this.append('reglement', this.reglementsTarget);
    }

    addAide() {
        return this.append('aide', this.aidesTarget);
    }

    received(event) {
        const input = event.target;
        if (input.matches('input[name$="[recu]"]') && input.checked) {
            const date = input.closest('.ad-row')?.querySelector('input[name$="[dateRemise]"]');
            if (date && !date.value) {
                date.value = new Date().toISOString().slice(0, 10);
                date.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
        this.updateResteResume();
    }

    remove(event) {
        event.currentTarget.closest('.ad-row')?.remove();
        this.refresh();
        this.updateResteResume();
    }

    /** Répartit ce que la famille doit payer elle-même (prix − réduction − aides) en N échéances mensuelles. */
    split({ params: { parts } }) {
        const base = this.amount('montantBaseCentimes');
        const reduction = this.amount('reductionCentimes');
        const aides = this.aideTargets.reduce((sum, row) => sum + this.rowAmount(row), 0);
        const rest = Math.max(0, base - reduction - aides);
        if (base <= 0) {
            this.notify("Indiquez d'abord le prix de la licence.");

            return;
        }
        if (rest <= 0) {
            this.notify('Il ne reste rien à payer : la réduction et les aides couvrent déjà le prix.');

            return;
        }
        this.notify('');

        this.reglementTargets.forEach((row) => {
            if (!row.querySelector('input[type="checkbox"]')?.checked) {
                row.remove();
            }
        });
        const kept = this.reglementTargets.reduce((sum, row) => sum + this.rowAmount(row), 0);
        const toSplit = Math.max(0, rest - kept);
        const cents = Math.round(toSplit * 100);
        const each = Math.floor(cents / parts);

        for (let i = 0; i < parts; i++) {
            const row = this.addReglement();
            const value = i === parts - 1 ? cents - each * (parts - 1) : each;
            this.setField(row, 'mode', 'cheque');
            this.setField(row, 'montantCentimes', (value / 100).toFixed(2).replace('.', ','));
            const due = new Date();
            due.setMonth(due.getMonth() + i);
            this.setField(row, 'dateEcheance', due.toISOString().slice(0, 10));
        }
        this.updateResteResume();
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

    append(kind, holder) {
        const prototype = kind === 'reglement' ? this.reglementPrototypeValue : this.aidePrototypeValue;
        const indexKey = kind === 'reglement' ? 'reglementIndexValue' : 'aideIndexValue';
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
        if (this.hasAidesEmptyTarget) {
            this.aidesEmptyTarget.hidden = this.aideTargets.length > 0;
        }
    }

    // --- Reste à régler (montant − aides), recalculé à chaque retour sur l'étape ---

    /**
     * Le total prévu (aides + règlements) doit couvrir exactement le montant dû : ni
     * manquant (message indiquant la somme qu'il reste à prévoir), ni excédentaire.
     */
    updateResteResume() {
        if (!this.hasResteResumeTarget) {
            return;
        }
        const base = this.amount('montantBaseCentimes');
        const reduction = this.amount('reductionCentimes');
        const du = Math.max(0, base - reduction);
        const aides = this.aideTargets.reduce((sum, row) => sum + this.rowAmount(row), 0);
        const reglements = this.reglementTargets.reduce((sum, row) => sum + this.rowAmount(row), 0);
        const reste = du - aides;
        const ecart = du - (aides + reglements);
        const ok = du <= 0 || Math.abs(ecart) <= 0.001;

        this.resteResumeTarget.innerHTML = `
            <span>Montant dû : <strong>${du.toFixed(2).replace('.', ',')} €</strong></span>
            <span>Aides : <strong>${aides.toFixed(2).replace('.', ',')} €</strong></span>
            <span>Reste à régler après aides : <strong>${Math.max(0, reste).toFixed(2).replace('.', ',')} €</strong></span>
        `;
        this.resteResumeTarget.classList.toggle('is-danger', !ok);
        let error = this.element.querySelector('[data-budget-error]');
        if (!ok) {
            if (!error) {
                error = document.createElement('p');
                error.dataset.budgetError = '1';
                error.className = 'text-xs mt-2';
                error.style.color = 'var(--color-admin-danger)';
                this.resteResumeTarget.after(error);
            }
            error.textContent = ecart > 0
                ? `Il manque ${ecart.toFixed(2).replace('.', ',')} € pour régler complètement la licence : ajoutez une aide ou un règlement.`
                : `Le total des aides et des règlements dépasse le montant de la licence de ${Math.abs(ecart).toFixed(2).replace('.', ',')} €.`;
        } else {
            error?.remove();
        }
    }

    checkBudget() {
        this.updateResteResume();

        return !this.element.querySelector('[data-budget-error]');
    }

    checkLicencieChosen(stepEl) {
        const select = this.licencieFieldTarget.querySelector('select');
        const label = this.licencieLabelFieldTarget.querySelector('input');
        const chosen = (select && select.value) || (label && label.value.trim());
        let error = stepEl.querySelector('[data-licencie-error]');
        if (!chosen) {
            if (!error) {
                error = document.createElement('p');
                error.dataset.licencieError = '1';
                error.className = 'text-xs mt-2';
                error.style.color = 'var(--color-admin-danger)';
                stepEl.appendChild(error);
            }
            error.textContent = "Choisissez un licencié, ou indiquez au moins un nom si la personne n'est pas encore enregistrée.";

            return false;
        }
        error?.remove();

        return true;
    }

    checkLicencePrice(stepEl) {
        const base = this.amount('montantBaseCentimes');
        let error = stepEl.querySelector('[data-price-error]');
        if (base <= 0) {
            if (!error) {
                error = document.createElement('p');
                error.dataset.priceError = '1';
                error.className = 'text-xs mt-2';
                error.style.color = 'var(--color-admin-danger)';
                stepEl.appendChild(error);
            }
            error.textContent = 'Le prix de la licence doit être supérieur à 0 €.';

            return false;
        }
        error?.remove();

        return true;
    }

    amount(field) {
        const input = this.element.querySelector(`[name$="[${field}]"]`);

        return this.parse(input?.value);
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
}
