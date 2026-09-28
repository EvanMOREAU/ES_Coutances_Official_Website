import { Controller } from '@hotwired/stimulus';

let openMenu = null;

/**
 * Petit menu contextuel (actions d'une ligne de tableau, choix des colonnes...).
 * Le panneau est positionné en "fixed" à côté du bouton : il n'est donc jamais
 * rogné par le tableau qui défile, et se retourne vers le haut en bas d'écran.
 */
export default class extends Controller {
    static targets = ['button', 'panel'];

    connect() {
        this.onOutside = (event) => {
            if (!this.element.contains(event.target)) {
                this.close();
            }
        };
        this.onKey = (event) => {
            if (event.key === 'Escape') {
                this.close();
                this.buttonTarget.focus();
            }
        };
        this.onDismiss = () => this.close();
        // Sur mobile, la barre d'adresse qui se replie déclenche un resize (hauteur seule) : on l'ignore.
        this.onResize = () => {
            if (window.innerWidth !== this.openWidth) {
                this.close();
            }
        };
    }

    disconnect() {
        this.close();
    }

    toggle(event) {
        event.stopPropagation();
        this.isOpen ? this.close() : this.open();
    }

    get isOpen() {
        return !this.panelTarget.classList.contains('hidden');
    }

    open() {
        if (openMenu && openMenu !== this) {
            openMenu.close();
        }
        openMenu = this;

        this.panelTarget.classList.remove('hidden');
        this.buttonTarget.setAttribute('aria-expanded', 'true');
        this.position();

        document.addEventListener('pointerdown', this.onOutside, true);
        document.addEventListener('keydown', this.onKey);
        this.openWidth = window.innerWidth;
        window.addEventListener('resize', this.onResize);
        window.addEventListener('scroll', this.onDismiss, true);
    }

    close() {
        if (!this.panelTarget.classList.contains('hidden')) {
            this.panelTarget.classList.add('hidden');
        }
        this.buttonTarget.setAttribute('aria-expanded', 'false');
        if (openMenu === this) {
            openMenu = null;
        }
        document.removeEventListener('pointerdown', this.onOutside, true);
        document.removeEventListener('keydown', this.onKey);
        window.removeEventListener('resize', this.onResize);
        window.removeEventListener('scroll', this.onDismiss, true);
    }

    position() {
        const rect = this.buttonTarget.getBoundingClientRect();
        const panel = this.panelTarget;
        const width = panel.offsetWidth;
        const height = panel.offsetHeight;

        const openUp = rect.bottom + 6 + height > window.innerHeight - 8 && rect.top - 6 - height > 8;
        panel.style.top = `${openUp ? rect.top - 6 - height : rect.bottom + 6}px`;
        panel.style.left = `${Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8))}px`;
    }
}
