import { Controller } from '@hotwired/stimulus';

const STORAGE_KEY = 'admin-nav-sections';

/** Replie/déplie une catégorie de la sidebar admin, mémorisé dans le navigateur (pas de backend). */
export default class extends Controller {
    static targets = ['links', 'icon'];
    static values = { id: String };

    connect() {
        this.setCollapsed(this.readState()[this.idValue] === true);
    }

    toggle() {
        const state = this.readState();
        const collapsed = !this.linksTarget.hidden;
        state[this.idValue] = collapsed;
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        this.setCollapsed(collapsed);
    }

    setCollapsed(collapsed) {
        this.linksTarget.hidden = collapsed;
        if (this.hasIconTarget) {
            this.iconTarget.classList.toggle('-rotate-90', collapsed);
        }
    }

    readState() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
        } catch {
            return {};
        }
    }
}
