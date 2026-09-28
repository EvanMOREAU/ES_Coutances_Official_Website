/**
 * Remplace les <input type="date"> par un sélecteur de date personnalisé :
 * un champ (icône calendrier + date lisible) qui ouvre un calendrier en
 * fenêtre flottante. Cliquer sur le titre du mois ouvre la vue "mois", puis
 * "années" : pratique pour une date de naissance.
 *
 * L'<input> natif reste dans le DOM (au format AAAA-MM-JJ) : il porte la
 * valeur, la validation et l'envoi du formulaire.
 *
 * Attributs facultatifs : min, max, required, disabled, placeholder,
 * data-native (ne pas remplacer), data-start-view="years" (ouvre directement
 * le choix de l'année quand le champ est vide).
 */

import { icon } from './ui_icons.js';

const PANEL_GAP = 6;
const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const SHORT_MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
const WEEKDAYS = ['Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa', 'Di'];
const cap = (text) => text.charAt(0).toUpperCase() + text.slice(1);
const pad = (n) => String(n).padStart(2, '0');
const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

function parseIso(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value ?? '');
    if (!match) {
        return null;
    }
    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));

    return Number.isNaN(date.getTime()) ? null : date;
}

const sameDay = (a, b) => a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());

let openInstance = null;
let uid = 0;

class CustomDate {
    constructor(input) {
        this.input = input;
        this.id = `dp-${++uid}`;
        this.build();
    }

    build() {
        const input = this.input;

        this.wrapper = document.createElement('div');
        this.wrapper.className = 'cs dp';
        input.parentNode.insertBefore(this.wrapper, input);
        this.wrapper.appendChild(input);

        input.classList.add('cs-native');
        input.tabIndex = -1;
        input.setAttribute('aria-hidden', 'true');

        this.trigger = document.createElement('button');
        this.trigger.type = 'button';
        this.trigger.className = 'cs-trigger dp-trigger';
        this.trigger.setAttribute('aria-haspopup', 'dialog');
        this.trigger.setAttribute('aria-expanded', 'false');
        this.trigger.innerHTML = `<span class="dp-icon">${icon('calendar', 15)}</span><span class="cs-value"></span>`;
        this.valueEl = this.trigger.querySelector('.cs-value');
        this.wrapper.appendChild(this.trigger);

        if (input.id) {
            const label = document.querySelector(`label[for="${CSS.escape(input.id)}"]`);
            if (label) {
                if (!label.id) {
                    label.id = `${this.id}-label`;
                }
                this.trigger.setAttribute('aria-labelledby', label.id);
            }
        }

        this.trigger.addEventListener('click', () => (this.isOpen ? this.close() : this.open()));
        this.trigger.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' && !this.isOpen) {
                event.preventDefault();
                this.open();
            }
        });
        input.addEventListener('focus', () => this.trigger.focus());
        input.addEventListener('invalid', () => {
            this.trigger.classList.add('is-invalid');
            this.trigger.focus();
        });
        input.addEventListener('change', () => {
            this.trigger.classList.remove('is-invalid');
            this.refresh();
        });
        input.form?.addEventListener('reset', () => setTimeout(() => this.refresh(), 0));

        this.refresh();
    }

    get isOpen() {
        return openInstance === this;
    }

    get value() {
        return parseIso(this.input.value);
    }

    get min() {
        return parseIso(this.input.min);
    }

    get max() {
        return parseIso(this.input.max);
    }

    isDisabledDay(date) {
        return (this.min && date < this.min) || (this.max && date > this.max);
    }

    refresh() {
        const date = this.value;
        this.valueEl.textContent = date
            ? `${date.getDate()} ${MONTHS[date.getMonth()]} ${date.getFullYear()}`
            : (this.input.placeholder || 'Sélectionner une date');
        this.valueEl.classList.toggle('is-placeholder', !date);
        this.trigger.disabled = this.input.disabled;
        this.wrapper.classList.toggle('is-disabled', this.input.disabled);
    }

    open() {
        if (this.input.disabled) {
            return;
        }
        openInstance?.close();
        openInstance = this;

        const today = startOfDay(new Date());
        this.selected = this.value;
        this.cursor = this.selected ?? this.clamp(today); // jour "focalisé" au clavier
        this.view = !this.selected && this.input.dataset.startView === 'years' ? 'years' : 'days';
        this.yearPage = this.cursor.getFullYear() - (this.cursor.getFullYear() % 12);

        this.panel = document.createElement('div');
        this.panel.className = 'cs-panel dp-panel';
        this.panel.setAttribute('role', 'dialog');
        this.panel.setAttribute('aria-label', 'Choisir une date');
        this.panel.addEventListener('keydown', (event) => this.onKey(event));
        // Dans une fenêtre modale (<dialog>), le panneau doit vivre dedans pour rester au premier plan.
        (this.input.closest('dialog') ?? document.body).appendChild(this.panel);

        this.render();
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
        this.onResize = () => {
            if (window.innerWidth !== openWidth) {
                this.close();
            }
        };
        document.addEventListener('pointerdown', this.onOutside, true);
        window.addEventListener('scroll', this.onScroll, true);
        window.addEventListener('resize', this.onResize);
        this.focusCursor();
    }

    clamp(date) {
        if (this.min && date < this.min) {
            return this.min;
        }
        if (this.max && date > this.max) {
            return this.max;
        }

        return date;
    }

    position() {
        const rect = this.trigger.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom - PANEL_GAP - 8;
        const spaceAbove = rect.top - PANEL_GAP - 8;
        const height = this.panel.offsetHeight;
        const openUp = height > spaceBelow && spaceAbove > spaceBelow;

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
        this.wrapper.classList.remove('is-open');
    }

    commit(date) {
        const value = date ? iso(date) : '';
        const changed = this.input.value !== value;
        this.input.value = value;
        this.refresh();
        this.close();
        this.trigger.focus();
        if (changed) {
            this.input.dispatchEvent(new Event('input', { bubbles: true }));
            this.input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // -- Rendu ---------------------------------------------------------------

    render() {
        const focusWasInside = this.panel.contains(document.activeElement);
        this.panel.replaceChildren();

        const header = document.createElement('div');
        header.className = 'dp-header';
        const prev = this.navButton('chevron-left', 'Précédent', () => this.shift(-1));
        const next = this.navButton('chevron-right', 'Suivant', () => this.shift(1));
        const title = document.createElement('button');
        title.type = 'button';
        title.className = 'dp-title';
        title.addEventListener('click', () => {
            this.view = this.view === 'days' ? 'months' : 'years';
            this.yearPage = this.cursor.getFullYear() - (this.cursor.getFullYear() % 12);
            this.render();
            this.focusCursor();
        });

        if (this.view === 'days') {
            title.textContent = `${cap(MONTHS[this.cursor.getMonth()])} ${this.cursor.getFullYear()}`;
        } else if (this.view === 'months') {
            title.textContent = String(this.cursor.getFullYear());
        } else {
            title.textContent = `${this.yearPage} – ${this.yearPage + 11}`;
        }
        header.append(prev, title, next);
        this.panel.appendChild(header);

        const body = document.createElement('div');
        body.className = 'dp-body';
        this.panel.appendChild(body);
        this.view === 'days' ? this.renderDays(body) : (this.view === 'months' ? this.renderMonths(body) : this.renderYears(body));

        if (this.view === 'days') {
            const footer = document.createElement('div');
            footer.className = 'dp-footer';
            const today = document.createElement('button');
            today.type = 'button';
            today.textContent = "Aujourd'hui";
            const todayDate = startOfDay(new Date());
            today.disabled = !!this.isDisabledDay(todayDate);
            today.addEventListener('click', () => this.commit(todayDate));
            footer.appendChild(today);
            if (!this.input.required && this.value) {
                const clear = document.createElement('button');
                clear.type = 'button';
                clear.textContent = 'Effacer';
                clear.addEventListener('click', () => this.commit(null));
                footer.appendChild(clear);
            }
            this.panel.appendChild(footer);
        }

        if (focusWasInside) {
            this.focusCursor();
        }
    }

    navButton(name, label, onClick) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'dp-nav';
        button.setAttribute('aria-label', label);
        button.innerHTML = icon(name, 15);
        button.addEventListener('click', onClick);

        return button;
    }

    renderDays(body) {
        const head = document.createElement('div');
        head.className = 'dp-grid dp-weekdays';
        WEEKDAYS.forEach((day) => {
            const cell = document.createElement('span');
            cell.textContent = day;
            head.appendChild(cell);
        });
        body.appendChild(head);

        const grid = document.createElement('div');
        grid.className = 'dp-grid';
        grid.setAttribute('role', 'grid');
        const year = this.cursor.getFullYear();
        const month = this.cursor.getMonth();
        const offset = (new Date(year, month, 1).getDay() + 6) % 7; // semaine commençant le lundi
        const today = startOfDay(new Date());

        for (let i = 0; i < 42; i++) {
            const date = new Date(year, month, 1 - offset + i);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'dp-day';
            button.textContent = String(date.getDate());
            button.dataset.date = iso(date);
            button.tabIndex = -1;
            if (date.getMonth() !== month) {
                button.classList.add('is-outside');
            }
            if (sameDay(date, today)) {
                button.classList.add('is-today');
            }
            if (sameDay(date, this.selected)) {
                button.classList.add('is-selected');
                button.setAttribute('aria-pressed', 'true');
            }
            if (this.isDisabledDay(date)) {
                button.disabled = true;
            }
            button.addEventListener('click', () => this.commit(date));
            grid.appendChild(button);
        }
        body.appendChild(grid);
    }

    renderMonths(body) {
        const grid = document.createElement('div');
        grid.className = 'dp-grid dp-cells';
        const year = this.cursor.getFullYear();
        const now = new Date();
        MONTHS.forEach((name, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'dp-cell';
            button.textContent = cap(SHORT_MONTHS[index]);
            button.dataset.month = String(index);
            button.tabIndex = -1;
            if (this.selected && this.selected.getFullYear() === year && this.selected.getMonth() === index) {
                button.classList.add('is-selected');
            }
            if (now.getFullYear() === year && now.getMonth() === index) {
                button.classList.add('is-today');
            }
            button.addEventListener('click', () => {
                this.cursor = this.clamp(new Date(year, index, Math.min(this.cursor.getDate(), 28)));
                this.view = 'days';
                this.render();
                this.focusCursor();
            });
            grid.appendChild(button);
        });
        body.appendChild(grid);
    }

    renderYears(body) {
        const grid = document.createElement('div');
        grid.className = 'dp-grid dp-cells';
        const nowYear = new Date().getFullYear();
        for (let year = this.yearPage; year < this.yearPage + 12; year++) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'dp-cell';
            button.textContent = String(year);
            button.dataset.year = String(year);
            button.tabIndex = -1;
            if (this.selected && this.selected.getFullYear() === year) {
                button.classList.add('is-selected');
            }
            if (year === nowYear) {
                button.classList.add('is-today');
            }
            if ((this.min && year < this.min.getFullYear()) || (this.max && year > this.max.getFullYear())) {
                button.disabled = true;
            }
            button.addEventListener('click', () => {
                this.cursor = this.clamp(new Date(year, this.cursor.getMonth(), Math.min(this.cursor.getDate(), 28)));
                this.view = 'months';
                this.render();
                this.focusCursor();
            });
            grid.appendChild(button);
        }
        body.appendChild(grid);
    }

    // -- Navigation ----------------------------------------------------------

    shift(direction) {
        if (this.view === 'days') {
            this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + direction, 1);
        } else if (this.view === 'months') {
            this.cursor = new Date(this.cursor.getFullYear() + direction, this.cursor.getMonth(), 1);
        } else {
            this.yearPage += direction * 12;
        }
        this.render();
    }

    /** Donne le focus au jour (ou mois / année) courant pour la navigation clavier. */
    focusCursor() {
        let selector = `[data-date="${iso(this.cursor)}"]`;
        if (this.view === 'months') {
            selector = `[data-month="${this.cursor.getMonth()}"]`;
        } else if (this.view === 'years') {
            selector = `[data-year="${this.cursor.getFullYear()}"]`;
        }
        const target = this.panel?.querySelector(selector)
            ?? this.panel?.querySelector('.dp-day:not(.is-outside):not(:disabled), .dp-cell:not(:disabled)');
        if (target) {
            this.panel.querySelectorAll('[tabindex="0"]').forEach((el) => { el.tabIndex = -1; });
            target.tabIndex = 0;
            target.focus({ preventScroll: true });
        }
    }

    onKey(event) {
        const { key } = event;
        if (key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            this.close();
            this.trigger.focus();

            return;
        }
        if (!event.target.matches('.dp-day, .dp-cell')) {
            return;
        }

        const deltas = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
        if (this.view === 'days' && key in deltas) {
            event.preventDefault();
            this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth(), this.cursor.getDate() + deltas[key]);
            this.render();
            this.focusCursor();
        } else if (this.view === 'days' && (key === 'PageUp' || key === 'PageDown')) {
            event.preventDefault();
            const step = key === 'PageUp' ? -1 : 1;
            this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + step, Math.min(this.cursor.getDate(), 28));
            this.render();
            this.focusCursor();
        } else if (this.view !== 'days' && key in deltas) {
            event.preventDefault();
            const cells = [...this.panel.querySelectorAll('.dp-cell:not(:disabled)')];
            const index = cells.indexOf(event.target);
            const step = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -3, ArrowDown: 3 }[key];
            const next = cells[index + step];
            if (next) {
                this.panel.querySelectorAll('[tabindex="0"]').forEach((el) => { el.tabIndex = -1; });
                next.tabIndex = 0;
                next.focus();
                const value = Number(next.dataset.month ?? next.dataset.year);
                this.cursor = next.dataset.month !== undefined
                    ? new Date(this.cursor.getFullYear(), value, 1)
                    : new Date(value, this.cursor.getMonth(), 1);
            }
        }
    }
}

function eligible(input) {
    return input instanceof HTMLInputElement
        && input.type === 'date'
        && !input.hasAttribute('data-native')
        && !input.classList.contains('cs-native');
}

function enhance(input) {
    if (eligible(input)) {
        new CustomDate(input);
    }
}

function enhanceWithin(root) {
    if (root instanceof HTMLInputElement) {
        enhance(root);
    } else if (root instanceof Element) {
        root.querySelectorAll('input[type="date"]').forEach(enhance);
    }
}

export function initCustomDates() {
    enhanceWithin(document.body);

    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach(enhanceWithin);
        }
    }).observe(document.body, { childList: true, subtree: true });
}
