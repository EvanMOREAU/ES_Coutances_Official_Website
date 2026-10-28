import { Controller } from '@hotwired/stimulus';

/** Réglage des notifications : « Tout activer / Tout désactiver » coche ou décoche toutes les cases du formulaire. */
export default class extends Controller {
    static targets = ['switch'];

    enableAll() {
        this.setAll(true);
    }

    disableAll() {
        this.setAll(false);
    }

    setAll(checked) {
        this.switchTargets.forEach((box) => {
            box.checked = checked;
        });
    }
}
