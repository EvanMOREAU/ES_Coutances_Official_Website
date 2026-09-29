import { Controller } from '@hotwired/stimulus';

/**
 * Personnalisation du tableau de bord : chaque module est rendu par le serveur (avec ses
 * données) mais peut être masqué par l'utilisateur — bascule instantanée côté client, mémorisée
 * en base via la même route que les préférences de tableau (TablePreferencesController), sous la
 * clé "dashboard". Calqué sur data_table_controller.js (toggleColumn/applyColumns/savePreferences).
 */
export default class extends Controller {
    static targets = ['widget'];
    static values = { hidden: Array, prefsUrl: String, prefsToken: String };

    connect() {
        this.hidden = new Set(this.hiddenValue);
        this.apply();
    }

    toggle(event) {
        const { widget } = event.currentTarget.dataset;
        if (event.currentTarget.checked) {
            this.hidden.delete(widget);
        } else {
            this.hidden.add(widget);
        }
        this.apply();
        this.save();
    }

    apply() {
        this.widgetTargets.forEach((element) => {
            element.hidden = this.hidden.has(element.dataset.widget);
        });
    }

    save() {
        if (!this.prefsUrlValue) {
            return;
        }
        clearTimeout(this.saveTimer);
        this.saveTimer = setTimeout(() => {
            fetch(this.prefsUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.prefsTokenValue, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ hidden: [...this.hidden] }),
                credentials: 'same-origin',
            }).catch(() => { /* préférence non critique */ });
        }, 400);
    }
}
