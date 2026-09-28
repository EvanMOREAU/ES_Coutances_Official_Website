import { Controller } from '@hotwired/stimulus';

const COLLAPSE_STORAGE_KEY = 'admin-sidebar-collapsed';

/**
 * Gère :
 * - l'ouverture/fermeture de la sidebar en tiroir sur mobile
 * - le repli en rail d'icônes sur desktop (préférence mémorisée en local,
 *   propre à cet appareil/navigateur, pas besoin d'aller-retour serveur)
 */
export default class extends Controller {
    static targets = ['panel', 'backdrop', 'label', 'collapseIcon'];

    connect() {
        if (localStorage.getItem(COLLAPSE_STORAGE_KEY) === '1') {
            this.setCollapsed(true);
        }
    }

    toggle() {
        this.panelTarget.classList.toggle('-translate-x-full');
        if (this.hasBackdropTarget) {
            this.backdropTarget.classList.toggle('hidden');
        }
    }

    close() {
        this.panelTarget.classList.add('-translate-x-full');
        if (this.hasBackdropTarget) {
            this.backdropTarget.classList.add('hidden');
        }
    }

    toggleCollapse() {
        const collapsed = !this.panelTarget.classList.contains('lg:w-20');
        this.setCollapsed(collapsed);
        localStorage.setItem(COLLAPSE_STORAGE_KEY, collapsed ? '1' : '0');
    }

    setCollapsed(collapsed) {
        this.panelTarget.classList.toggle('lg:w-20', collapsed);
        this.panelTarget.classList.toggle('lg:w-64', !collapsed);
        this.labelTargets.forEach((el) => el.classList.toggle('lg:hidden', collapsed));
        if (this.hasCollapseIconTarget) {
            this.collapseIconTarget.classList.toggle('rotate-180', collapsed);
        }
    }
}
