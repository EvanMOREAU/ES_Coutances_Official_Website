import { Controller } from '@hotwired/stimulus';

/**
 * Horaires d'un jour, sans saisie de texte : interrupteur « Ouvert / Fermé » et
 * une ou deux plages choisies avec le sélecteur d'heure. Le champ texte d'origine
 * (masqué) garde le résultat au format lu par le site : « 14h00 – 18h00 » ou « Fermé »,
 * plusieurs plages séparées par « / ».
 */
const RANGE = /(\d{1,2})\s*[h:]\s*(\d{2})?\s*[–\-àa]+\s*(\d{1,2})\s*[h:]\s*(\d{2})?/gi;
const pad = (n) => String(n).padStart(2, '0');
const toTime = (h, m) => `${pad(Number(h))}:${pad(Number(m ?? 0))}`;
const label = (time) => `${time.slice(0, 2)}h${time.slice(3)}`;
const MAX_RANGES = 2;

export default class extends Controller {
    connect() {
        this.input = this.element;
        this.ranges = this.parse(this.input.value);
        this.open = this.ranges.length > 0;
        if (!this.open && this.input.value.trim() !== '' && !/^ferm/i.test(this.input.value.trim())) {
            this.original = this.input.value.trim();
        }

        this.input.type = 'hidden';
        this.ui = document.createElement('div');
        this.ui.className = 'hr';
        this.input.after(this.ui);
        this.render();
        this.sync();
    }

    disconnect() {
        this.ui?.remove();
    }

    parse(text) {
        const ranges = [];
        for (const match of String(text).matchAll(RANGE)) {
            ranges.push([toTime(match[1], match[2]), toTime(match[3], match[4])]);
        }

        return ranges.slice(0, MAX_RANGES);
    }

    render() {
        const rows = this.ranges.map((range, i) => `
            <div class="hr-range">
                <input type="time" data-i="${i}" data-edge="0" value="${range[0]}" aria-label="Ouverture">
                <span class="hr-sep">–</span>
                <input type="time" data-i="${i}" data-edge="1" value="${range[1]}" aria-label="Fermeture">
                ${this.ranges.length > 1 ? `<button type="button" class="hr-btn" data-remove="${i}" aria-label="Retirer cette plage"><i class="fa-solid fa-xmark"></i></button>` : ''}
            </div>`).join('');

        this.ui.innerHTML = `
            <label class="hr-switch"><input type="checkbox" ${this.open ? 'checked' : ''}> <span>${this.open ? 'Ouvert' : 'Fermé'}</span></label>
            ${this.open ? `<div class="hr-ranges">${rows}${this.ranges.length < MAX_RANGES ? '<button type="button" class="hr-add" data-add><i class="fa-solid fa-plus"></i> Ajouter une plage</button>' : ''}</div>` : ''}
            ${!this.open && this.original ? `<p class="hr-note">Ancien texte : « ${this.original.replace(/[<>&]/g, '')} » — ouvrez le jour pour choisir des horaires.</p>` : ''}`;

        this.ui.querySelector('.hr-switch input').addEventListener('change', (event) => {
            this.open = event.target.checked;
            if (this.open && this.ranges.length === 0) {
                this.ranges = [['14:00', '18:00']];
            }
            this.render();
            this.sync();
        });
        this.ui.querySelectorAll('.hr-range input').forEach((field) => {
            field.addEventListener('change', () => {
                this.ranges[Number(field.dataset.i)][Number(field.dataset.edge)] = field.value;
                this.sync();
            });
        });
        this.ui.querySelector('[data-add]')?.addEventListener('click', () => {
            // Nouvelle plage : elle commence à la fermeture de la précédente et dure quatre heures (23h00 au plus tard).
            const last = this.ranges[this.ranges.length - 1];
            const start = last ? last[1] : '14:00';
            const end = Math.min(23, Number(start.slice(0, 2)) + 4);
            this.ranges.push([start, `${pad(end)}:${start.slice(3)}`]);
            this.render();
            this.sync();
        });
        this.ui.querySelectorAll('[data-remove]').forEach((button) => {
            button.addEventListener('click', () => {
                this.ranges.splice(Number(button.dataset.remove), 1);
                this.render();
                this.sync();
            });
        });
    }

    /** Écrit la valeur texte lue par le site ; « Fermé » quand le jour est fermé. */
    sync() {
        const complete = this.ranges.filter((range) => range[0] && range[1]);
        this.input.value = this.open && complete.length
            ? complete.map((range) => `${label(range[0])} – ${label(range[1])}`).join(' / ')
            : 'Fermé';
    }
}
