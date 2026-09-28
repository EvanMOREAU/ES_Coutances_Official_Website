/**
 * Combobox « choisir ou créer » : un champ texte simple qui propose, au focus
 * et à la frappe, les valeurs existantes d'une <datalist> associée (attribut
 * list="..."), avec une entrée « Créer « … » » quand le texte tapé ne
 * correspond à aucune valeur existante. Même esprit que custom_select.js :
 * l'<input> natif reste la source de vérité (valeur, envoi du formulaire),
 * le composant n'ajoute qu'un panneau de suggestions au-dessus.
 *
 * Attribut requis sur l'<input> : data-combobox (+ list="id-de-la-datalist").
 */

import { icon } from './ui_icons.js';

const PANEL_GAP = 6;
const fold = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

let openInstance = null;
let uid = 0;

class Combobox {
    constructor(input) {
        this.input = input;
        this.id = `cb-${++uid}`;
        this.items = [];
        this.activeIndex = -1;
        this.build();
    }

    build() {
        const input = this.input;
        this.datalist = input.list;
        // On retire list= pour éviter que le navigateur affiche sa propre popup en plus de la nôtre.
        input.removeAttribute('list');

        this.wrapper = document.createElement('div');
        this.wrapper.className = 'cs cb';
        input.parentNode.insertBefore(this.wrapper, input);
        this.wrapper.appendChild(input);

        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.autocomplete = 'off';

        input.addEventListener('focus', () => this.open());
        input.addEventListener('input', () => { this.open(); this.renderItems(); });
        input.addEventListener('keydown', (event) => this.onKey(event));

        this.onOutside = (event) => {
            if (!this.wrapper.contains(event.target) && !this.panel?.contains(event.target)) {
                this.close();
            }
        };
    }

    get isOpen() {
        return openInstance === this;
    }

    options() {
        return this.datalist ? [...this.datalist.options].map((o) => o.value).filter(Boolean) : [];
    }

    open() {
        if (this.isOpen || this.input.disabled || this.input.readOnly) {
            return;
        }
        openInstance?.close();
        openInstance = this;

        this.panel = document.createElement('div');
        this.panel.className = 'cs-panel';
        this.list = document.createElement('div');
        this.list.className = 'cs-list';
        this.list.setAttribute('role', 'listbox');
        this.panel.appendChild(this.list);
        this.empty = document.createElement('div');
        this.empty.className = 'cs-empty';
        this.empty.textContent = 'Aucune catégorie';
        this.panel.appendChild(this.empty);

        document.body.appendChild(this.panel);
        this.input.setAttribute('aria-expanded', 'true');
        this.wrapper.classList.add('is-open');
        this.position();
        this.renderItems();

        this.onScroll = (event) => { if (!this.panel.contains(event.target)) this.close(); };
        this.onResize = () => this.position();
        document.addEventListener('pointerdown', this.onOutside, true);
        window.addEventListener('scroll', this.onScroll, true);
        window.addEventListener('resize', this.onResize);
    }

    position() {
        const rect = this.input.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom - PANEL_GAP - 8;
        const spaceAbove = rect.top - PANEL_GAP - 8;
        const openUp = this.panel.scrollHeight > spaceBelow && spaceAbove > spaceBelow;

        this.panel.style.minWidth = `${rect.width}px`;
        this.panel.style.maxHeight = `${Math.max(160, Math.min(280, openUp ? spaceAbove : spaceBelow))}px`;
        this.panel.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - this.panel.offsetWidth - 8))}px`;
        this.panel.classList.toggle('is-up', openUp);
        if (openUp) {
            this.panel.style.top = 'auto';
            this.panel.style.bottom = `${window.innerHeight - rect.top + PANEL_GAP}px`;
        } else {
            this.panel.style.bottom = 'auto';
            this.panel.style.top = `${rect.bottom + PANEL_GAP}px`;
        }
    }

    renderItems() {
        const value = this.input.value.trim();
        const needle = fold(value);
        const all = this.options();
        const matches = all.filter((option) => needle === '' || fold(option).includes(needle));
        const exact = all.some((option) => fold(option) === needle);

        this.list.replaceChildren();
        this.items = [];

        if (value !== '' && !exact) {
            const create = document.createElement('div');
            create.className = 'cs-option cb-create';
            create.setAttribute('role', 'option');
            create.innerHTML = `<span class="cb-create-icon">${icon('plus', 13)}</span><span class="cs-option-label">Créer « ${value.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]))} »</span>`;
            create.addEventListener('pointerdown', (event) => { event.preventDefault(); this.choose(value); });
            this.list.appendChild(create);
            this.items.push({ el: create, value });
        }

        matches.forEach((option) => {
            const item = document.createElement('div');
            item.className = 'cs-option';
            item.setAttribute('role', 'option');
            item.innerHTML = '<span class="cs-option-label"></span>';
            item.querySelector('.cs-option-label').textContent = option;
            item.addEventListener('pointerdown', (event) => { event.preventDefault(); this.choose(option); });
            this.list.appendChild(item);
            this.items.push({ el: item, value: option });
        });

        this.empty.hidden = this.items.length > 0;
        this.setActive(this.items.length > 0 ? 0 : -1);
        this.position();
    }

    setActive(index) {
        this.activeIndex = index;
        this.items.forEach(({ el }, i) => el.classList.toggle('is-active', i === index));
        this.items[index]?.el.scrollIntoView({ block: 'nearest' });
    }

    choose(value) {
        const changed = this.input.value !== value;
        this.input.value = value;
        this.close();
        if (changed) {
            this.input.dispatchEvent(new Event('input', { bubbles: true }));
            this.input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    onKey(event) {
        if (!this.isOpen) {
            if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault();
                this.open();
            }

            return;
        }

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                this.setActive(Math.min(this.activeIndex + 1, this.items.length - 1));
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.setActive(Math.max(this.activeIndex - 1, 0));
                break;
            case 'Enter':
                if (this.items[this.activeIndex]) {
                    event.preventDefault();
                    this.choose(this.items[this.activeIndex].value);
                }
                break;
            case 'Escape':
                event.preventDefault();
                event.stopPropagation();
                this.close();
                break;
            case 'Tab':
                this.close();
                break;
        }
    }

    close() {
        if (!this.isOpen) {
            return;
        }
        openInstance = null;
        document.removeEventListener('pointerdown', this.onOutside, true);
        window.removeEventListener('scroll', this.onScroll, true);
        window.removeEventListener('resize', this.onResize);
        this.panel?.remove();
        this.panel = null;
        this.input.setAttribute('aria-expanded', 'false');
        this.wrapper.classList.remove('is-open');
    }
}

function enhance(input) {
    if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-combobox') || input.dataset.comboboxReady) {
        return;
    }
    input.dataset.comboboxReady = '1';
    new Combobox(input);
}

function enhanceWithin(root) {
    if (root instanceof HTMLInputElement) {
        enhance(root);
    } else if (root instanceof Element) {
        root.querySelectorAll('input[data-combobox]').forEach(enhance);
    }
}

export function initCustomCombobox() {
    enhanceWithin(document.body);

    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach(enhanceWithin);
        }
    }).observe(document.body, { childList: true, subtree: true });
}
