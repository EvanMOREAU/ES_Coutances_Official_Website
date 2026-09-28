import { Controller } from '@hotwired/stimulus';

/**
 * Formulaire d'une licence : ajout / retrait des règlements et des aides, et
 * échelonnement du reste à payer en 1, 2 ou 3 fois. Les gabarits des lignes
 * viennent des prototypes Symfony (« __name__ » = numéro unique).
 */
export default class extends Controller {
    static targets = ['reglements', 'reglementsEmpty', 'reglement', 'aides', 'aidesEmpty', 'aide'];
    static values = {
        reglementPrototype: String,
        aidePrototype: String,
        reglementIndex: Number,
        aideIndex: Number,
    };

    addReglement() {
        return this.append('reglement', this.reglementsTarget);
    }

    addAide() {
        return this.append('aide', this.aidesTarget);
    }

    /** Règlement encaissé : la date de remise est préremplie avec celle du jour. */
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

        // On garde les règlements déjà encaissés ; on remplace les autres.
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
    }

    /** Message sous le bouton d'échelonnement (rien ne se passait en silence quand le prix manquait). */
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

    /** Montant (en euros) d'un champ du formulaire principal. */
    amount(field) {
        const input = this.element.closest('form').querySelector(`[name$="[${field}]"]`);

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
