import { Controller } from '@hotwired/stimulus';

const escapeHtml = (text) => text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/**
 * Ouvre la gestion d'une rubrique du site vitrine dans une grande fenêtre.
 * Le contenu est la page d'admin habituelle (liste, formulaire...) affichée
 * dans une iframe : elle se masque menu et barre du haut (classe is-embedded,
 * voir _layout.html.twig) et toute la navigation (créer, modifier, enregistrer,
 * supprimer) reste dans la fenêtre.
 *
 * Fluidité : la page est chargée dès que le pointeur survole la carte (ou au
 * premier contact tactile / focus clavier), donc quand on clique elle est déjà
 * prête et la fenêtre s'ouvre instantanément. Une fenêtre déjà ouverte puis
 * fermée sans modification est simplement masquée, pas détruite.
 *
 * À la fermeture, si quelque chose a pu changer (l'iframe a navigué), la page
 * est rechargée pour rafraîchir les chiffres et graphiques.
 */
export default class extends Controller {
    static values = { url: String, title: String, newUrl: String, autoopen: String };

    connect() {
        this.entries = new Map();
        this.current = null;

        // ?ouvrir=<rubrique>[&vue=new] : ouverture directe depuis un lien de conseil, de la cloche...
        if (this.autoopenValue) {
            requestAnimationFrame(() => {
                const wantsNew = 'new' === this.autoopenValue && this.newUrlValue;
                this.open(wantsNew ? { params: { url: this.newUrlValue, title: `${this.titleValue} — Nouveau` } } : undefined);
            });
        }
    }

    disconnect() {
        this.entries.forEach((entry) => entry.dialog.remove());
        this.entries.clear();
    }

    /** Précharge la page (survol, focus, toucher). Sans effet visible. */
    warm(event) {
        const { url, title } = this.resolve(event);
        this.entry(url, title);
    }

    /** Clic n'importe où sur la carte (le bouton « + » a sa propre action). */
    openCard(event) {
        if (event.target.closest('[data-section-modal-url-param]')) {
            return;
        }
        this.open(event);
    }

    open(event) {
        if (this.current) {
            return;
        }
        const { url, title } = this.resolve(event);
        if (!url) {
            return;
        }

        const entry = this.entry(url, title);
        this.current = entry;
        entry.dialog.showModal();
    }

    close() {
        const entry = this.current;
        if (!entry) {
            return;
        }
        this.current = null;
        entry.dialog.close();

        if (entry.loads > 1) {
            window.location.reload();
        }
    }

    resolve(event) {
        // Le bouton « + » fournit son propre URL et titre via les paramètres de l'action.
        return {
            url: event?.params?.url || this.urlValue,
            title: event?.params?.title || this.titleValue,
        };
    }

    entry(url, title) {
        if (!this.entries.has(url)) {
            this.entries.set(url, this.build(url, title));
        }

        return this.entries.get(url);
    }

    build(url, title) {
        const dialog = document.createElement('dialog');
        dialog.className = 'section-modal';
        dialog.innerHTML = `
            <div class="section-modal-bar">
                <h2 class="section-modal-title">${escapeHtml(title)}</h2>
                <a href="${escapeHtml(url)}" data-role="full" class="admin-btn admin-btn-ghost admin-btn-sm"><i class="fa-solid fa-up-right-and-down-left-from-center"></i> Pleine page</a>
                <button type="button" data-role="close" class="section-modal-close" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="section-modal-frame">
                <iframe title="${escapeHtml(title)}" src="${escapeHtml(url)}"></iframe>
            </div>`;

        const entry = { dialog, loads: 0 };
        const iframe = dialog.querySelector('iframe');
        const frame = dialog.querySelector('.section-modal-frame');
        const fullLink = dialog.querySelector('[data-role="full"]');

        iframe.addEventListener('load', () => {
            entry.loads += 1;
            frame.classList.add('is-ready');
            try {
                fullLink.href = iframe.contentWindow.location.href; // même origine
            } catch {
                /* on garde l'URL d'origine */
            }
        });
        dialog.querySelector('[data-role="close"]').addEventListener('click', () => this.close());
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            this.close();
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                this.close();
            }
        });

        document.body.appendChild(dialog);

        return entry;
    }
}
