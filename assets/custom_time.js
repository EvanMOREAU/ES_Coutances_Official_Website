/**
 * Remplace les <input type="time"> par un sélecteur d'heure personnalisé, dans
 * le même esprit que le sélecteur de date : un champ (icône horloge + heure
 * lisible) qui ouvre une fenêtre flottante avec deux colonnes, les heures puis
 * les minutes (par pas de 5). L'<input> natif reste dans le DOM (HH:MM) et porte
 * la valeur, la validation et l'envoi du formulaire.
 *
 * Attributs facultatifs : required, disabled, placeholder, data-native
 * (ne pas remplacer), data-minute-step="10" (pas des minutes, 5 par défaut).
 */

import { icon } from './ui_icons.js';

const PANEL_GAP = 6;
const pad = (n) => String(n).padStart(2, '0');

let openInstance = null;
let uid = 0;

class CustomTime {
    constructor(input) {
        this.input = input;
        this.id = `tp-${++uid}`;
        this.build();
    }

    build() {
        const input = this.input;

        this.wrapper = document.createElement('div');
        this.wrapper.className = 'cs dp tp';
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
        this.trigger.innerHTML = `<span class="dp-icon">${icon('clock', 15)}</span><span class="cs-value"></span>`;
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

    /** @returns {{h: number, m: number}|null} */
    get value() {
        const match = /^(\d{1,2}):(\d{2})/.exec(this.input.value ?? '');

        return match ? { h: Number(match[1]), m: Number(match[2]) } : null;
    }

    get minuteStep() {
        return Math.max(1, Number(this.input.dataset.minuteStep) || 5);
    }

    refresh() {
        const time = this.value;
        this.valueEl.textContent = time ? `${pad(time.h)}:${pad(time.m)}` : (this.input.placeholder || '--:--');
        this.valueEl.classList.toggle('is-placeholder', !time);
        this.trigger.disabled = this.input.disabled;
        this.wrapper.classList.toggle('is-disabled', this.input.disabled);
    }

    open() {
        if (this.input.disabled) {
            return;
        }
        openInstance?.close();
        openInstance = this;

        this.hour = this.value?.h ?? null;
        this.minute = this.value?.m ?? null;

        this.panel = document.createElement('div');
        this.panel.className = 'cs-panel dp-panel tp-panel';
        this.panel.setAttribute('role', 'dialog');
        this.panel.setAttribute('aria-label', 'Choisir une heure');
        this.panel.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                this.close();
                this.trigger.focus();
            }
        });
        // Dans une fenêtre modale (<dialog>), le panneau doit vivre dedans pour rester au premier plan.
        (this.input.closest('dialog') ?? document.body).appendChild(this.panel);

        this.render();
        this.trigger.setAttribute('aria-expanded', 'true');
        this.wrapper.classList.add('is-open');
        this.position();
        this.scrollToSelection();

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
        document.addEventListener('pointerdown', this.onOutside, true);
        window.addEventListener('scroll', this.onScroll, true);
    }

    render() {
        const step = this.minuteStep;
        const minutes = [];
        for (let m = 0; m < 60; m += step) {
            minutes.push(m);
        }
        // Une valeur existante hors pas (ex. 17:07) reste sélectionnable.
        if (this.minute !== null && !minutes.includes(this.minute)) {
            minutes.push(this.minute);
            minutes.sort((a, b) => a - b);
        }

        const cells = (values, current, kind) => values.map((v) =>
            `<button type="button" class="dp-cell tp-cell${v === current ? ' is-selected' : ''}" data-kind="${kind}" data-v="${v}" aria-pressed="${v === current}">${pad(v)}</button>`,
        ).join('');

        const hours = Array.from({ length: 24 }, (_, i) => i);
        this.panel.innerHTML = `
            <div class="tp-cols">
                <div class="tp-col"><p class="tp-col-title">Heures</p><div class="tp-list">${cells(hours, this.hour, 'h')}</div></div>
                <div class="tp-col"><p class="tp-col-title">Minutes</p><div class="tp-list">${cells(minutes, this.minute, 'm')}</div></div>
            </div>
            <div class="dp-footer">
                ${this.input.required ? '<span></span>' : '<button type="button" data-role="clear">Effacer</button>'}
                <button type="button" data-role="now">Maintenant</button>
            </div>`;

        this.panel.querySelectorAll('.tp-cell').forEach((button) => {
            button.addEventListener('click', () => this.pick(button.dataset.kind, Number(button.dataset.v)));
        });
        this.panel.querySelector('[data-role="clear"]')?.addEventListener('click', () => this.commit(null));
        this.panel.querySelector('[data-role="now"]').addEventListener('click', () => {
            const now = new Date();
            this.commit({ h: now.getHours(), m: (Math.round(now.getMinutes() / step) * step) % 60 });
        });
    }

    pick(kind, value) {
        if (kind === 'h') {
            this.hour = value;
            this.minute ??= 0;
            this.markSelected();

            return;
        }
        this.minute = value;
        this.commit({ h: this.hour ?? 0, m: value });
    }

    /** Met à jour la sélection sans reconstruire les listes (pas de saccade, le défilement est conservé). */
    markSelected() {
        this.panel.querySelectorAll('.tp-cell').forEach((cell) => {
            const current = cell.dataset.kind === 'h' ? this.hour : this.minute;
            const selected = Number(cell.dataset.v) === current;
            cell.classList.toggle('is-selected', selected);
            cell.setAttribute('aria-pressed', String(selected));
        });
    }

    scrollToSelection() {
        this.panel.querySelectorAll('.tp-list').forEach((list) => {
            const selected = list.querySelector('.is-selected');
            if (selected) {
                list.scrollTop = selected.offsetTop - list.clientHeight / 2 + selected.offsetHeight / 2;
            }
        });
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
        this.panel.remove();
        this.panel = null;
        this.trigger.setAttribute('aria-expanded', 'false');
        this.wrapper.classList.remove('is-open');
    }

    commit(time) {
        const value = time ? `${pad(time.h)}:${pad(time.m)}` : '';
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
}

function enhance(input) {
    if (!(input instanceof HTMLInputElement) || input.type !== 'time' || input.dataset.native !== undefined || input.dataset.timeReady) {
        return;
    }
    input.dataset.timeReady = '1';
    new CustomTime(input);
}

function enhanceWithin(root) {
    if (root instanceof HTMLInputElement) {
        enhance(root);
    } else if (root instanceof Element) {
        root.querySelectorAll('input[type="time"]').forEach(enhance);
    }
}

export function initCustomTimes() {
    enhanceWithin(document.body);

    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach(enhanceWithin);
        }
    }).observe(document.body, { childList: true, subtree: true });
}
