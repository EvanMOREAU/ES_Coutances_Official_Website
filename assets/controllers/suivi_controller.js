import { Controller } from '@hotwired/stimulus';

const euros = (cents) => `${(cents / 100).toFixed(2).replace('.', ',')} €`;
const today = () => new Date().toISOString().slice(0, 10);

/**
 * Suivi du paiement d'une licence, en trois étapes (règlements, aides, récapitulatif).
 * Cocher un règlement ou une aide pré-remplit la date du jour ; le récapitulatif
 * recalcule ce qui a été encaissé et ce qu'il reste à recevoir.
 */
export default class extends Controller {
    static targets = ['step', 'indicator', 'reglement', 'aide', 'back', 'next', 'save', 'sumReglements', 'sumAides', 'sumReste'];
    static values = { du: Number };

    connect() {
        this.current = 0;
        this.show(0);
    }

    next() {
        this.show(Math.min(this.current + 1, this.stepTargets.length - 1));
    }

    back() {
        this.show(Math.max(this.current - 1, 0));
    }

    show(index) {
        this.current = index;
        const last = index === this.stepTargets.length - 1;
        this.stepTargets.forEach((step, i) => { step.hidden = i !== index; });
        this.indicatorTargets.forEach((item, i) => {
            item.classList.toggle('is-current', i === index);
            item.classList.toggle('is-done', i < index);
        });
        this.backTarget.hidden = index === 0;
        this.nextTarget.hidden = last;
        this.saveTarget.hidden = !last;
        if (last) {
            this.summarize();
        }
    }

    toggle(event) {
        const card = event.currentTarget.closest('.sv-card');
        const checked = event.currentTarget.checked;
        card.classList.toggle('is-done', checked);
        const date = card.querySelector('input[type="date"]');
        if (checked && date && !date.value) {
            date.value = today();
            date.dispatchEvent(new Event('change', { bubbles: true })); // met à jour le sélecteur de date personnalisé
        }
        const small = card.querySelector('.sv-toggle small');
        if (small && card.dataset.suiviTarget === 'aide') {
            small.textContent = checked ? 'Reçue' : 'Attendue';
        }
    }

    sum(targets) {
        return targets
            .filter((card) => card.querySelector('input[type="checkbox"]').checked)
            .reduce((total, card) => total + Number(card.dataset.amount), 0);
    }

    summarize() {
        const reglements = this.sum(this.reglementTargets);
        const aides = this.sum(this.aideTargets);
        this.sumReglementsTarget.textContent = euros(reglements);
        this.sumAidesTarget.textContent = euros(aides);
        this.sumResteTarget.textContent = euros(Math.max(0, this.duValue - reglements - aides));
    }
}
