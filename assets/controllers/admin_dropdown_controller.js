import { Controller } from '@hotwired/stimulus';

/**
 * Menu déroulant générique (menu utilisateur, notifications...).
 * Un seul menu est ouvert à la fois : en ouvrir un ferme les autres.
 */
export default class extends Controller {
    static targets = ['menu'];

    connect() {
        this.close = this.close.bind(this);
        this.onOtherOpened = (event) => {
            if (event.detail !== this) {
                this.close();
            }
        };
        window.addEventListener('admin-dropdown:opened', this.onOtherOpened);
    }

    toggle(event) {
        event.stopPropagation();
        const isHidden = this.menuTarget.classList.contains('hidden');
        if (isHidden) {
            window.dispatchEvent(new CustomEvent('admin-dropdown:opened', { detail: this }));
            this.menuTarget.classList.remove('hidden');
            document.addEventListener('click', this.close);
        } else {
            this.close();
        }
    }

    close() {
        this.menuTarget.classList.add('hidden');
        document.removeEventListener('click', this.close);
    }

    disconnect() {
        window.removeEventListener('admin-dropdown:opened', this.onOtherOpened);
        document.removeEventListener('click', this.close);
    }
}
