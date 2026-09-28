import { Controller } from '@hotwired/stimulus';

/**
 * Wizard générique à étapes (utilisé pour la création famille + licenciés) :
 * navigation Suivant/Précédent, indicateur d'étapes, et gestion d'une
 * collection Symfony dynamique (ajout/suppression de lignes "licencié")
 * via le pattern standard data-prototype.
 */
export default class extends Controller {
    static targets = ['step', 'indicator', 'licenciesList', 'summary', 'nextButton', 'submitButton', 'backButton'];

    connect() {
        this.current = 0;
        this.nextIndex = 0;
        this.showStep(0);
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

        if (this.current === this.stepTargets.length - 1) {
            this.fillSummary();
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

        if (isLast) {
            this.fillSummary();
        }
    }

    addLicencie() {
        const prototype = this.licenciesListTarget.dataset.prototype;
        const html = prototype.replace(/__name__/g, this.nextIndex);
        this.licenciesListTarget.insertAdjacentHTML('beforeend', html);
        this.nextIndex += 1;
    }

    removeLicencie(event) {
        event.target.closest('.licencie-row-item').remove();
    }

    fillSummary() {
        if (!this.hasSummaryTarget) {
            return;
        }

        const val = (name) => this.element.querySelector(`[name="${name}"]`)?.value || '—';
        const familleNom = val('famille_wizard[nom]');
        const familleEmail = val('famille_wizard[email]');
        const familleVille = val('famille_wizard[ville]');

        const rows = [...this.licenciesListTarget.querySelectorAll('.licencie-row-item')];
        const licenciesHtml = rows.length
            ? rows.map((row) => {
                const rowInput = (field) => row.querySelector(`[name$="[${field}]"]`)?.value || '—';
                return `<li class="py-2 border-b border-admin-border last:border-0">
                    <span class="font-medium">${rowInput('prenom')} ${rowInput('nom')}</span>
                    <span class="text-admin-text-muted"> — ${rowInput('email')}</span>
                </li>`;
            }).join('')
            : '<li class="py-2 text-admin-text-muted">Aucun licencié ajouté pour le moment.</li>';

        this.summaryTarget.innerHTML = `
            <div class="mb-4">
                <p class="text-xs uppercase tracking-wide text-admin-text-muted mb-1">Famille</p>
                <p class="font-medium">${familleNom}</p>
                <p class="text-sm text-admin-text-muted">${familleEmail}${familleVille !== '—' ? ' · ' + familleVille : ''}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-admin-text-muted mb-1">Licenciés (${rows.length})</p>
                <ul>${licenciesHtml}</ul>
            </div>
        `;
    }
}
