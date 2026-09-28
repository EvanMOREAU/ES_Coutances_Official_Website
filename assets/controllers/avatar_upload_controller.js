import { Controller } from '@hotwired/stimulus';

/** Aperçu instantané de l'avatar choisi, avant enregistrement. */
export default class extends Controller {
    static targets = ['input', 'image', 'initial', 'hint'];

    preview() {
        const file = this.inputTarget.files?.[0];
        if (!file) {
            return;
        }

        this.imageTarget.src = URL.createObjectURL(file);
        this.imageTarget.classList.remove('hidden');
        if (this.hasInitialTarget) {
            this.initialTarget.classList.add('hidden');
        }
        this.hintTarget.textContent = `${file.name} — pensez à enregistrer.`;
    }
}
