import { Controller } from '@hotwired/stimulus';

/**
 * Champ de code de vérification en cases séparées (ex. TOTP, code par e-mail).
 * Chaque case ne contient qu'un chiffre ; la saisie avance automatiquement au
 * champ suivant, le collage d'un code complet se répartit dans les cases, et
 * la valeur assemblée est recopiée dans le champ caché réellement soumis.
 */
export default class extends Controller {
    static targets = ['box', 'hidden', 'boxes', 'backup'];

    connect() {
        this.boxTargets[0]?.focus();
        this.sync();
    }

    toggleBackup(event) {
        event.preventDefault();
        this.boxesTarget.classList.toggle('hidden');
        this.backupTarget.classList.toggle('hidden');
        this.hiddenTarget.value = '';

        if (this.backupTarget.classList.contains('hidden')) {
            this.boxTargets[0]?.focus();
        } else {
            this.backupTarget.querySelector('input')?.focus();
        }
    }

    backupInput(event) {
        this.hiddenTarget.value = event.target.value.trim();
    }

    input(event) {
        const box = event.target;
        box.value = box.value.replace(/[^0-9]/g, '').slice(-1);

        if (box.value && box.dataset.index < this.boxTargets.length - 1) {
            this.boxTargets[Number(box.dataset.index) + 1].focus();
        }

        this.sync();
    }

    keydown(event) {
        const box = event.target;
        const index = Number(box.dataset.index);

        if (event.key === 'Backspace' && !box.value && index > 0) {
            this.boxTargets[index - 1].focus();
        }
    }

    paste(event) {
        event.preventDefault();
        const digits = (event.clipboardData?.getData('text') || '').replace(/[^0-9]/g, '').split('');

        this.boxTargets.forEach((box, i) => {
            box.value = digits[i] || '';
        });

        const next = this.boxTargets[Math.min(digits.length, this.boxTargets.length - 1)];
        next?.focus();

        this.sync();
    }

    sync() {
        this.hiddenTarget.value = this.boxTargets.map((box) => box.value).join('');
    }
}
