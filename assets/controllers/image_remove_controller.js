import { Controller } from '@hotwired/stimulus';

/**
 * Retrait de la photo d'un article : une petite croix sur l'aperçu remplace la case
 * « Supprimer la photo ». La case d'origine (masquée) est cochée en coulisse ; le retrait
 * est appliqué à l'enregistrement, et reste annulable d'ici là.
 */
export default class extends Controller {
    static targets = ['preview', 'notice', 'checkbox'];

    remove() {
        this.set(true);
    }

    undo() {
        this.set(false);
    }

    set(removed) {
        if (this.hasCheckboxTarget) {
            this.checkboxTarget.checked = removed;
        }
        this.previewTarget.classList.toggle('is-removed', removed);
        this.noticeTarget.hidden = !removed;
    }
}
