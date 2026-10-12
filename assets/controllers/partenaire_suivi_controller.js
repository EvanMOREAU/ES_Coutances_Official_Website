import { Controller } from '@hotwired/stimulus';

const euros = (cents) => `${(cents / 100).toFixed(2).replace('.', ',')} €`;
const today = () => new Date().toISOString().slice(0, 10);

/**
 * Suivi d'un partenaire, en trois étapes (règlements, tâches, récapitulatif), sur le modèle
 * du suivi de paiement d'une licence (suivi). Cocher un règlement pré-remplit la date du jour ;
 * le récapitulatif recalcule ce qui a été encaissé et ce qu'il reste à faire.
 */
export default class extends Controller {
    static targets = ['step', 'indicator', 'reglement', 'tache', 'back', 'next', 'save', 'sumReglements', 'sumReste', 'sumTaches'];
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
    }

    summarize() {
        const checked = (card) => card.querySelector('input[type="checkbox"]').checked;
        const received = this.reglementTargets
            .filter(checked)
            .reduce((total, card) => total + Number(card.dataset.amount), 0);
        this.sumReglementsTarget.textContent = euros(received);
        this.sumResteTarget.textContent = euros(Math.max(0, this.duValue - received));
        const remaining = this.tacheTargets.filter((card) => !checked(card)).length;
        this.sumTachesTarget.textContent = `${remaining} / ${this.tacheTargets.length}`;
    }
}
