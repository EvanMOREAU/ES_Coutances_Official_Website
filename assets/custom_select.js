/**
 * Remplace visuellement tous les <select> par une liste déroulante
 * personnalisée : panneau arrondi, champ de recherche (dès 6 choix), survol
 * coloré. Les <select multiple> deviennent une liste à cases rondes où l'on
 * coche plusieurs choix sans que le panneau se referme.
 *
 * Le <select> natif reste dans le DOM : c'est lui qui porte la valeur, la
 * validation et l'envoi du formulaire. Le composant ne fait que l'afficher
 * autrement et lui transmettre les changements (événement "change").
 *
 * Attributs facultatifs sur le <select> :
 *   data-native              ne pas remplacer ce select
 *   data-compact             version étroite (pagination, filtres)
 *   data-search="true|false" forcer / masquer la recherche
 *   data-placeholder="…"     texte affiché quand rien n'est choisi
 * Une <option data-dim> est estompée (suggestion "hors catégorie", etc.).
 */

import { icon } from './ui_icons.js';

const PANEL_GAP = 6;
const PANEL_MAX_HEIGHT = 320;
const SEARCH_THRESHOLD = 6;
const MAX_TAGS = 3;

let openInstance = null;
let uid = 0;

/** Minuscules sans accents, pour une recherche "tolérante". */
const fold = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

class CustomSelect {
    constructor(select) {
        this.select = select;
        this.multiple = select.multiple;
        this.id = `cs-${++uid}`;
        this.typeahead = '';
        this.typeaheadTimer = null;
        this.build();
    }

    build() {
        const select = this.select;

        this.wrapper = document.createElement('div');
        this.wrapper.className = 'cs';
        if (this.multiple) {
            this.wrapper.classList.add('cs-multi');
        }
        if (select.hasAttribute('data-compact')) {
            this.wrapper.classList.add('cs-compact');
        }
        select.parentNode.insertBefore(this.wrapper, select);
        this.wrapper.appendChild(select);

        select.classList.add('cs-native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        this.trigger = document.createElement('div');
        this.trigger.tabIndex = 0;
        this.trigger.className = 'cs-trigger';
        this.trigger.setAttribute('role', 'combobox');
        this.trigger.setAttribute('aria-haspopup', 'listbox');
        this.trigger.setAttribute('aria-expanded', 'false');
        this.trigger.innerHTML = `<span class="cs-value"></span><span class="cs-chevron">${icon('chevrons-up-down')}</span>`;
        this.valueEl = this.trigger.querySelector('.cs-value');
        this.wrapper.appendChild(this.trigger);

        if (select.id) {
            const label = document.querySelector(`label[for="${CSS.escape(select.id)}"]`);
            if (label) {
                if (!label.id) {
                    label.id = `${this.id}-label`;
                }
                this.trigger.setAttribute('aria-labelledby', label.id);
            }
        }

        this.trigger.addEventListener('click', (event) => {
            if (event.target.closest('.cs-tag-remove')) {
                return;
            }
            this.isOpen ? this.close() : this.open();
        });
        this.trigger.addEventListener('keydown', (event) => this.onKey(event));

        // Un <label for> ou un script qui focalise le select natif atterrit sur le déclencheur.
        select.addEventListener('focus', () => this.trigger.focus());
        // Validation HTML5 (champ requis vide) : on met le déclencheur en évidence.
        select.addEventListener('invalid', () => {
            this.trigger.classList.add('is-invalid');
            this.trigger.focus();
        });
        select.addEventListener('change', () => {
            this.trigger.classList.remove('is-invalid');
            this.refresh();
        });
        select.form?.addEventListener('reset', () => setTimeout(() => this.refresh(), 0));

        this.refresh();
    }

    get isOpen() {
        return openInstance === this;
    }

    get placeholder() {
        if (this.select.dataset.placeholder) {
            return this.select.dataset.placeholder;
        }
        const first = this.select.options[0];
        if (!this.multiple && first && first.value === '' && first.textContent.trim() !== '') {
            return first.textContent.trim();
        }

        return this.multiple ? 'Sélectionner…' : 'Choisir…';
    }

    /** Aplatit <option>/<optgroup> en une liste d'entrées (groupes = en-têtes non cliquables). */
    entries() {
        const entries = [];
        for (const child of this.select.children) {
            if (child.tagName === 'OPTGROUP') {
                entries.push({ group: child.label });
                for (const option of child.children) {
                    entries.push({ option });
                }
            } else if (child.tagName === 'OPTION') {
                entries.push({ option: child });
            }
        }

        return entries;
    }

    /** Une option vide en tête de liste sert de "placeholder" en sélection simple : on ne la propose pas. */
    isPlaceholderOption(option) {
        return !this.multiple && option.value === '' && this.select.options[0] === option;
    }

    refresh() {
        this.trigger.setAttribute('aria-disabled', this.select.disabled ? 'true' : 'false');
        this.trigger.tabIndex = this.select.disabled ? -1 : 0;
        this.wrapper.classList.toggle('is-disabled', this.select.disabled);

        if (this.multiple) {
            this.renderTags();

            return;
        }

        const selected = this.select.selectedOptions[0];
        const isPlaceholder = !selected || this.isPlaceholderOption(selected);
        this.valueEl.textContent = isPlaceholder ? this.placeholder : selected.textContent.trim();
        this.valueEl.classList.toggle('is-placeholder', isPlaceholder);
    }

    renderTags() {
        const selected = [...this.select.selectedOptions];
        this.valueEl.replaceChildren();
        this.valueEl.classList.toggle('is-placeholder', selected.length === 0);

        if (selected.length === 0) {
            this.valueEl.textContent = this.placeholder;

            return;
        }

        selected.slice(0, MAX_TAGS).forEach((option) => {
            const tag = document.createElement('span');
            tag.className = 'cs-tag';
            const label = document.createElement('span');
            label.textContent = option.textContent.trim();
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'cs-tag-remove';
            remove.setAttribute('aria-label', `Retirer ${label.textContent}`);
            remove.innerHTML = icon('x', 12);
            remove.addEventListener('click', (event) => {
                event.stopPropagation();
                if (!this.select.disabled) {
                    this.toggle(option);
                }
            });
            tag.append(label, remove);
            this.valueEl.appendChild(tag);
        });

        if (selected.length > MAX_TAGS) {
            const more = document.createElement('span');
            more.className = 'cs-tag cs-tag-more';
            more.textContent = `+${selected.length - MAX_TAGS}`;
            this.valueEl.appendChild(more);
        }
    }

    open() {
        if (this.select.disabled) {
            return;
        }
        openInstance?.close();
        openInstance = this;

        this.panel = document.createElement('div');
        this.panel.className = 'cs-panel';
        if (this.multiple) {
            this.panel.classList.add('cs-panel-multi');
        }

        const entries = this.entries().filter((entry) => entry.group !== undefined || !this.isPlaceholderOption(entry.option));
        const optionCount = entries.filter((entry) => entry.option).length;
        const searchAttr = this.select.dataset.search;
        this.searchable = searchAttr === 'true' || (searchAttr !== 'false' && optionCount >= SEARCH_THRESHOLD);

        if (this.searchable) {
            const search = document.createElement('div');
            search.className = 'cs-search';
            search.innerHTML = icon('search', 15);
            this.searchInput = document.createElement('input');
            this.searchInput.type = 'text';
            this.searchInput.placeholder = 'Rechercher…';
            this.searchInput.autocomplete = 'off';
            this.searchInput.setAttribute('aria-label', 'Rechercher');
            this.searchInput.addEventListener('input', () => this.filter(this.searchInput.value));
            this.searchInput.addEventListener('keydown', (event) => this.onKey(event));
            search.appendChild(this.searchInput);
            this.panel.appendChild(search);
        } else {
            this.searchInput = null;
        }

        this.list = document.createElement('div');
        this.list.className = 'cs-list';
        this.list.setAttribute('role', 'listbox');
        this.list.id = `${this.id}-list`;
        if (this.multiple) {
            this.list.setAttribute('aria-multiselectable', 'true');
        }
        this.trigger.setAttribute('aria-controls', this.list.id);

        this.items = [];
        this.headings = [];
        let currentHeading = null;
        for (const entry of entries) {
            if (entry.group !== undefined) {
                currentHeading = document.createElement('div');
                currentHeading.className = 'cs-group';
                currentHeading.textContent = entry.group;
                this.headings.push({ el: currentHeading, items: [] });
                this.list.appendChild(currentHeading);
                continue;
            }

            const option = entry.option;
            const item = document.createElement('div');
            item.className = 'cs-option';
            item.setAttribute('role', 'option');
            item.id = `${this.id}-opt-${this.items.length}`;
            item.innerHTML = this.multiple
                ? `<span class="cs-box">${icon('check', 12)}</span><span class="cs-option-label"></span>`
                : '<span class="cs-option-label"></span>';
            item.querySelector('.cs-option-label').textContent = option.textContent.trim();
            item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
            if (option.disabled) {
                item.classList.add('is-disabled');
                item.setAttribute('aria-disabled', 'true');
            }
            if (option.hasAttribute('data-dim')) {
                item.classList.add('is-dim');
            }
            const record = { option, item, text: fold(option.textContent), visible: true };
            item.addEventListener('pointermove', () => this.setActive(this.items.indexOf(record)));
            item.addEventListener('click', () => {
                if (!option.disabled) {
                    this.multiple ? this.toggle(option) : this.choose(option);
                }
            });
            this.items.push(record);
            currentHeading && this.headings[this.headings.length - 1].items.push(record);
            this.list.appendChild(item);
        }
        this.panel.appendChild(this.list);

        this.empty = document.createElement('div');
        this.empty.className = 'cs-empty';
        this.empty.textContent = 'Aucun résultat';
        this.empty.hidden = true;
        this.panel.appendChild(this.empty);

        // Dans une fenêtre modale (<dialog>), le panneau doit vivre dedans pour rester au premier plan.
        (this.select.closest('dialog') ?? document.body).appendChild(this.panel);
        this.trigger.setAttribute('aria-expanded', 'true');
        this.wrapper.classList.add('is-open');
        this.position();

        this.onOutside = (event) => {
            if (!this.panel.contains(event.target) && !this.trigger.contains(event.target)) {
                this.close();
            }
        };
        this.onScroll = (event) => {
            if (!this.panel.contains(event.target)) {
                this.close();
            }
        };
        const openWidth = window.innerWidth;
        // Un resize en hauteur seule (barre d'adresse mobile qui se replie) ne ferme pas la liste.
        this.onResize = () => {
            if (window.innerWidth !== openWidth) {
                this.close();
            }
        };
        document.addEventListener('pointerdown', this.onOutside, true);
        window.addEventListener('scroll', this.onScroll, true);
        window.addEventListener('resize', this.onResize);

        const current = this.items.findIndex(({ option }) => option.selected);
        this.setActive(current >= 0 ? current : this.nextEnabled(-1, 1));
        this.scrollActiveIntoView();
        this.searchInput?.focus({ preventScroll: true });
    }

    position() {
        const rect = this.trigger.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom - PANEL_GAP - 8;
        const spaceAbove = rect.top - PANEL_GAP - 8;
        const contentHeight = this.panel.scrollHeight;
        const openUp = contentHeight > spaceBelow && spaceAbove > spaceBelow;
        const maxHeight = Math.max(160, Math.min(PANEL_MAX_HEIGHT, openUp ? spaceAbove : spaceBelow));

        this.panel.style.minWidth = `${rect.width}px`;
        this.panel.style.maxHeight = `${maxHeight}px`;
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

    close() {
        if (!this.isOpen) {
            return;
        }
        openInstance = null;
        document.removeEventListener('pointerdown', this.onOutside, true);
        window.removeEventListener('scroll', this.onScroll, true);
        window.removeEventListener('resize', this.onResize);
        this.panel.remove();
        this.panel = null;
        this.trigger.setAttribute('aria-expanded', 'false');
        this.trigger.removeAttribute('aria-activedescendant');
        this.wrapper.classList.remove('is-open');
    }

    emitChange() {
        this.select.dispatchEvent(new Event('input', { bubbles: true }));
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    choose(option) {
        const changed = this.select.value !== option.value;
        this.select.value = option.value;
        this.refresh();
        this.close();
        this.trigger.focus();
        if (changed) {
            this.emitChange();
        }
    }

    toggle(option) {
        option.selected = !option.selected;
        const record = this.items?.find((entry) => entry.option === option);
        record?.item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
        this.refresh();
        if (this.isOpen) {
            this.position();
        }
        this.emitChange();
    }

    filter(query) {
        const needle = fold(query.trim());
        this.items.forEach((record) => {
            record.visible = needle === '' || record.text.includes(needle);
            record.item.hidden = !record.visible;
        });
        this.headings.forEach(({ el, items }) => {
            el.hidden = !items.some((record) => record.visible);
        });
        this.empty.hidden = this.items.some((record) => record.visible);
        this.setActive(this.nextEnabled(-1, 1));
        this.scrollActiveIntoView();
    }

    setActive(index) {
        if (index < 0 || !this.items[index]) {
            return;
        }
        this.activeIndex = index;
        this.items.forEach(({ item }, i) => item.classList.toggle('is-active', i === index));
        this.trigger.setAttribute('aria-activedescendant', this.items[index].item.id);
    }

    scrollActiveIntoView() {
        this.items[this.activeIndex]?.item.scrollIntoView({ block: 'nearest' });
    }

    usable(record) {
        return record.visible && !record.option.disabled;
    }

    nextEnabled(from, step) {
        let i = from + step;
        while (i >= 0 && i < this.items.length) {
            if (this.usable(this.items[i])) {
                return i;
            }
            i += step;
        }

        return from >= 0 && from < this.items.length ? from : -1;
    }

    onKey(event) {
        const { key } = event;

        if (!this.isOpen) {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(key)) {
                event.preventDefault();
                this.open();
            } else if (!this.multiple && key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                this.open();
                if (this.searchInput) {
                    this.searchInput.value = key;
                    event.preventDefault();
                    this.filter(key);
                } else {
                    this.searchByTyping(key);
                }
            }

            return;
        }

        switch (key) {
            case 'ArrowDown':
                event.preventDefault();
                this.setActive(this.nextEnabled(this.activeIndex, 1));
                this.scrollActiveIntoView();
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.setActive(this.nextEnabled(this.activeIndex, -1));
                this.scrollActiveIntoView();
                break;
            case 'Home':
                if (!this.searchInput) {
                    event.preventDefault();
                    this.setActive(this.nextEnabled(-1, 1));
                    this.scrollActiveIntoView();
                }
                break;
            case 'End':
                if (!this.searchInput) {
                    event.preventDefault();
                    this.setActive(this.nextEnabled(this.items.length, -1));
                    this.scrollActiveIntoView();
                }
                break;
            case 'Enter':
            case ' ': {
                if (key === ' ' && this.searchInput) {
                    break; // l'espace fait partie de la recherche
                }
                event.preventDefault();
                const record = this.items[this.activeIndex];
                if (record && this.usable(record)) {
                    this.multiple ? this.toggle(record.option) : this.choose(record.option);
                }
                break;
            }
            case 'Escape':
                event.preventDefault();
                event.stopPropagation();
                this.close();
                this.trigger.focus();
                break;
            case 'Tab':
                this.close();
                break;
            default:
                if (!this.searchInput && key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                    this.searchByTyping(key);
                }
        }
    }

    /** Sans champ de recherche : taper "se" saute à la première option qui commence par "se". */
    searchByTyping(char) {
        clearTimeout(this.typeaheadTimer);
        this.typeahead += fold(char);
        this.typeaheadTimer = setTimeout(() => { this.typeahead = ''; }, 600);

        const start = this.typeahead.length === 1 ? this.activeIndex + 1 : this.activeIndex;
        const ordered = [...this.items.keys()];
        const rotated = [...ordered.slice(start), ...ordered.slice(0, start)];
        const match = rotated.find((i) => this.usable(this.items[i]) && this.items[i].text.startsWith(this.typeahead));
        if (match !== undefined) {
            this.setActive(match);
            this.scrollActiveIntoView();
        }
    }
}

function eligible(select) {
    return select instanceof HTMLSelectElement
        && !(select.size > 1 && !select.multiple)
        && !select.hasAttribute('data-native')
        && !select.classList.contains('cs-native');
}

function enhance(select) {
    if (eligible(select)) {
        new CustomSelect(select);
    }
}

export function enhanceWithin(root) {
    if (root instanceof HTMLSelectElement) {
        enhance(root);
    } else if (root instanceof Element) {
        root.querySelectorAll('select').forEach(enhance);
    }
}

export function initCustomSelects() {
    enhanceWithin(document.body);

    // Sélecteurs ajoutés après coup (lignes "licencié" du wizard, tableaux, Turbo...).
    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach(enhanceWithin);
        }
    }).observe(document.body, { childList: true, subtree: true });
}
