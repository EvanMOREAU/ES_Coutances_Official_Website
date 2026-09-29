import { Controller } from '@hotwired/stimulus';

const SVG_NS = 'http://www.w3.org/2000/svg';
const MIN_WIDTH = 260;
const PAD_FULL = { top: 16, right: 16, bottom: 30, left: 34 };
const PAD_COMPACT = { top: 8, right: 8, bottom: 8, left: 8 };

const el = (name, attrs = {}) => {
    const node = document.createElementNS(SVG_NS, name);
    Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, String(value)));

    return node;
};

/**
 * Courbe d'évolution mensuelle (une seule série) dessinée en SVG, avec
 * repère vertical et infobulle au survol. Données : [{ month: 'YYYY-MM', total, new }].
 */
export default class extends Controller {
    static targets = ['plot', 'tooltip'];
    // compact : mini-courbe sans axes (cartes du tableau de bord), hauteur réglable.
    // unitPlural : forme au pluriel quand elle ne s'obtient pas en ajoutant un simple "s" (ex. une unité monétaire).
    static values = {
        points: Array, unit: { type: String, default: 'élément' }, unitPlural: String,
        compact: Boolean, height: { type: Number, default: 240 },
    };

    connect() {
        const points = this.pointsValue;
        if (points.length === 0) {
            return;
        }

        const monthFormat = new Intl.DateTimeFormat('fr-FR', { month: 'short' });
        const longFormat = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' });
        this.series = points.map((point) => {
            const [year, month] = point.month.split('-').map(Number);
            const date = new Date(year, month - 1, 1);

            return { ...point, short: monthFormat.format(date).replace('.', ''), long: longFormat.format(date) };
        });

        const maxTotal = Math.max(...this.series.map((p) => p.total), 1);
        const step = maxTotal <= 5 ? 1 : Math.ceil(maxTotal / 5);
        // Mini-courbe : de la marge au-dessus pour qu'une série plate ne colle pas au bord.
        this.yMax = this.compactValue ? maxTotal * 1.35 : Math.ceil(maxTotal / step) * step;
        this.ticks = Array.from({ length: Math.round(this.yMax / step) + 1 }, (_, i) => i * step);

        this.pad = this.compactValue ? PAD_COMPACT : PAD_FULL;
        this.innerH = this.heightValue - this.pad.top - this.pad.bottom;
        this.y = (value) => this.pad.top + this.innerH - (value / this.yMax) * this.innerH;

        // Le SVG est dessiné à la largeur réelle (pas de mise à l'échelle) : les textes gardent leur taille.
        this.width = 0;
        this.resizeObserver = new ResizeObserver(() => {
            const width = Math.max(MIN_WIDTH, Math.round(this.plotTarget.clientWidth));
            if (width !== this.width) {
                this.width = width;
                this.draw();
            }
        });
        this.resizeObserver.observe(this.plotTarget);
    }

    disconnect() {
        this.resizeObserver?.disconnect();
    }

    draw() {
        this.innerW = this.width - this.pad.left - this.pad.right;
        this.x = (i) => this.pad.left + (this.series.length === 1 ? this.innerW / 2 : (i / (this.series.length - 1)) * this.innerW);
        const svg = el('svg', { viewBox: `0 0 ${this.width} ${this.heightValue}`, width: this.width, height: this.heightValue, role: 'img', 'aria-hidden': 'true' });

        if (this.compactValue) {
            svg.append(el('line', { class: 'chart-grid', x1: this.pad.left, x2: this.width - this.pad.right, y1: this.y(0), y2: this.y(0) }));
        } else {
            this.ticks.forEach((tick) => {
                svg.append(el('line', { class: 'chart-grid', x1: this.pad.left, x2: this.width - this.pad.right, y1: this.y(tick), y2: this.y(tick) }));
                const label = el('text', { class: 'chart-axis', x: this.pad.left - 8, y: this.y(tick) + 4, 'text-anchor': 'end' });
                label.textContent = tick;
                svg.append(label);
            });

            // Un libellé de mois sur n quand la place manque (~38 px par libellé).
            const spacing = this.series.length > 1 ? this.innerW / (this.series.length - 1) : this.innerW;
            const every = Math.max(1, Math.ceil(38 / spacing));
            this.series.forEach((point, i) => {
                if (i % every !== 0 && i !== this.series.length - 1) {
                    return;
                }
                const label = el('text', { class: 'chart-axis', x: this.x(i), y: this.heightValue - 8, 'text-anchor': 'middle' });
                label.textContent = point.short;
                svg.append(label);
            });
        }

        const line = this.series.map((point, i) => `${i === 0 ? 'M' : 'L'}${this.x(i)},${this.y(point.total)}`).join(' ');
        const baseline = this.y(0);
        svg.append(el('path', { class: 'chart-area', d: `${line} L${this.x(this.series.length - 1)},${baseline} L${this.x(0)},${baseline} Z` }));
        svg.append(el('path', { class: 'chart-line', d: line }));

        this.cursor = el('line', { class: 'chart-cursor', y1: this.pad.top, y2: baseline, visibility: 'hidden' });
        svg.append(this.cursor);

        this.dots = this.series.map((point, i) => {
            const dot = el('circle', { class: 'chart-dot', cx: this.x(i), cy: this.y(point.total), r: this.compactValue ? 0 : 3.5 });
            svg.append(dot);

            return dot;
        });

        // Zone de survol plus large que la courbe elle-même.
        const hit = el('rect', { x: this.pad.left, y: this.pad.top, width: this.innerW, height: this.innerH, fill: 'transparent' });
        hit.addEventListener('pointermove', (event) => this.hover(event, svg));
        hit.addEventListener('pointerleave', () => this.leave());
        svg.append(hit);

        this.plotTarget.replaceChildren(svg);
    }

    hover(event, svg) {
        const rect = svg.getBoundingClientRect();
        const px = event.clientX - rect.left;
        const ratio = (px - this.pad.left) / this.innerW;
        const index = Math.min(this.series.length - 1, Math.max(0, Math.round(ratio * (this.series.length - 1))));
        const point = this.series[index];

        this.cursor.setAttribute('x1', this.x(index));
        this.cursor.setAttribute('x2', this.x(index));
        this.cursor.setAttribute('visibility', 'visible');
        this.dots.forEach((dot, i) => dot.setAttribute('r', i === index ? 5 : (this.compactValue ? 0 : 3.5)));

        const unit = point.total > 1 ? (this.unitPluralValue || `${this.unitValue}s`) : this.unitValue;
        const gain = point.new > 0 ? `<span>+${point.new} ce mois-ci</span>` : '<span>aucun ajout ce mois-ci</span>';
        this.tooltipTarget.innerHTML = `<strong>${point.long}</strong>${point.total} ${unit}<br>${gain}`;
        this.tooltipTarget.hidden = false;

        const box = this.plotTarget.getBoundingClientRect();
        const left = this.x(index);
        const halfTip = this.tooltipTarget.offsetWidth / 2;
        this.tooltipTarget.style.left = `${Math.max(halfTip, Math.min(box.width - halfTip, left))}px`;
        this.tooltipTarget.style.top = `${this.y(point.total)}px`;
    }

    leave() {
        this.cursor.setAttribute('visibility', 'hidden');
        this.dots.forEach((dot) => dot.setAttribute('r', this.compactValue ? 0 : 3.5));
        this.tooltipTarget.hidden = true;
    }
}
