import { Controller } from '@hotwired/stimulus';

const SVG_NS = 'http://www.w3.org/2000/svg';
const MIN_WIDTH = 240;

const el = (name, attrs = {}) => {
    const node = document.createElementNS(SVG_NS, name);
    Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, String(value)));

    return node;
};

const html = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) {
        node.className = className;
    }
    if (text !== undefined) {
        node.textContent = text;
    }

    return node;
};

/**
 * Graphiques du tableau de bord, dessinés en SVG/HTML sans bibliothèque (aucun style en ligne : la CSP les interdit).
 *
 *   type="bar"   histogramme vertical, une ou plusieurs séries côte à côte
 *   type="hbar"  classement horizontal (une série)
 *   type="donut" répartition en parts
 *
 * Données : labels = ['Jan', ...], series = [{ name, values: [...] }]. Les montants (format="money") arrivent en centimes.
 * Les couleurs viennent des classes .chart-c1 … .chart-c6 (voir admin-components.css).
 */
export default class extends Controller {
    static targets = ['plot', 'legend', 'tooltip'];
    static values = {
        type: { type: String, default: 'bar' },
        labels: Array,
        series: Array,
        format: { type: String, default: 'number' },
        height: { type: Number, default: 220 },
        centerLabel: String,
        empty: String,
    };

    connect() {
        this.lang = document.documentElement.lang || 'fr';
        this.numberFormat = new Intl.NumberFormat(this.lang, { maximumFractionDigits: this.formatValue === 'money' ? 0 : 1 });
        this.total = this.seriesValue.reduce((sum, s) => sum + s.values.reduce((a, b) => a + b, 0), 0);

        if (this.total === 0) {
            this.plotTarget.replaceChildren(html('p', 'chart-empty', this.emptyValue));

            return;
        }

        if (this.typeValue === 'bar') {
            this.resizeObserver = new ResizeObserver(() => {
                const width = Math.max(MIN_WIDTH, Math.round(this.plotTarget.clientWidth));
                if (width !== this.width) {
                    this.width = width;
                    this.drawBars();
                }
            });
            this.resizeObserver.observe(this.plotTarget);
        } else if (this.typeValue === 'hbar') {
            this.drawHBars();
        } else {
            this.drawDonut();
        }
    }

    disconnect() {
        this.resizeObserver?.disconnect();
    }

    format(value) {
        if (this.formatValue === 'money') {
            return `${this.numberFormat.format(value / 100)} €`;
        }

        return this.numberFormat.format(value);
    }

    // ---------------------------------------------------------------- histogramme

    drawBars() {
        const series = this.seriesValue;
        const labels = this.labelsValue;
        const pad = { top: 12, right: 8, bottom: 28, left: 40 };
        const height = this.heightValue;
        const innerW = this.width - pad.left - pad.right;
        const innerH = height - pad.top - pad.bottom;

        const max = Math.max(...series.flatMap((s) => s.values), 1);
        const step = this.niceStep(max);
        const yMax = Math.ceil(max / step) * step;
        const y = (value) => pad.top + innerH - (value / yMax) * innerH;

        const svg = el('svg', { viewBox: `0 0 ${this.width} ${height}`, width: this.width, height, role: 'img', 'aria-hidden': 'true' });
        for (let tick = 0; tick <= yMax; tick += step) {
            svg.append(el('line', { class: 'chart-grid', x1: pad.left, x2: this.width - pad.right, y1: y(tick), y2: y(tick) }));
            const text = el('text', { class: 'chart-axis', x: pad.left - 6, y: y(tick) + 4, 'text-anchor': 'end' });
            text.textContent = this.formatValue === 'money' ? this.compact(tick) : this.numberFormat.format(tick);
            svg.append(text);
        }

        const band = innerW / labels.length;
        const gap = Math.min(10, band * 0.25);
        // Barres plafonnées : avec une seule saison, la barre ne doit pas occuper toute la largeur.
        const barW = Math.min((band - gap) / series.length, 44);
        const groupW = barW * series.length;
        const every = Math.max(1, Math.ceil(34 / band));

        labels.forEach((label, i) => {
            const x0 = pad.left + i * band + (band - groupW) / 2;
            series.forEach((s, j) => {
                const value = s.values[i] ?? 0;
                const h = (value / yMax) * innerH;
                svg.append(el('rect', {
                    class: `chart-bar chart-c${j + 1}`, x: x0 + j * barW + (series.length > 1 ? 1 : 0), y: y(value), width: Math.max(1, barW - (series.length > 1 ? 2 : 0)), height: Math.max(h, value > 0 ? 1 : 0), rx: 2,
                }));
            });
            if (i % every === 0 || i === labels.length - 1) {
                const text = el('text', { class: 'chart-axis', x: x0 + groupW / 2, y: height - 8, 'text-anchor': 'middle' });
                text.textContent = label;
                svg.append(text);
            }
            const hit = el('rect', { x: pad.left + i * band, y: pad.top, width: band, height: innerH, fill: 'transparent' });
            hit.addEventListener('pointermove', () => this.showTip(label, series.map((s) => ({ name: s.name, value: s.values[i] ?? 0 })), x0 + groupW / 2, pad.top));
            hit.addEventListener('pointerleave', () => this.hideTip());
            svg.append(hit);
        });

        this.plotTarget.replaceChildren(svg);
        this.drawLegend(series.length > 1 ? series.map((s, j) => ({ name: s.name, index: j })) : []);
    }

    niceStep(max) {
        if (max <= 5) {
            return 1;
        }
        const raw = max / 5;
        const pow = 10 ** Math.floor(Math.log10(raw));
        const base = raw / pow;

        return (base <= 1 ? 1 : base <= 2 ? 2 : base <= 5 ? 5 : 10) * pow;
    }

    /** Étiquette d'axe courte : 12 k€, 1,2 M€ (montants en centimes). */
    compact(cents) {
        const euros = cents / 100;
        if (euros >= 1_000_000) {
            return `${this.numberFormat.format(euros / 1_000_000)} M€`;
        }
        if (euros >= 1000) {
            return `${this.numberFormat.format(euros / 1000)} k€`;
        }

        return `${this.numberFormat.format(euros)} €`;
    }

    // ---------------------------------------------------------- classement horizontal

    drawHBars() {
        const values = this.seriesValue[0].values;
        const max = Math.max(...values, 1);
        const list = html('div', 'chart-hbars');
        this.labelsValue.forEach((label, i) => {
            const row = html('div', 'chart-hrow');
            row.append(html('span', 'chart-hlabel', label));
            const track = html('span', 'chart-htrack');
            const fill = html('span', `chart-hfill chart-bg${(i % 6) + 1}`);
            fill.style.width = `${Math.max(2, (values[i] / max) * 100)}%`;
            track.append(fill);
            row.append(track, html('span', 'chart-hvalue', this.format(values[i])));
            list.append(row);
        });
        this.plotTarget.replaceChildren(list);
    }

    // ------------------------------------------------------------------- anneau

    drawDonut() {
        const values = this.seriesValue[0].values;
        const size = 168;
        const radius = 64;
        const circumference = 2 * Math.PI * radius;
        const svg = el('svg', { viewBox: `0 0 ${size} ${size}`, width: size, height: size, role: 'img', 'aria-hidden': 'true' });
        const parts = [];
        let offset = 0;
        values.forEach((value, i) => {
            if (value <= 0) {
                return;
            }
            const length = (value / this.total) * circumference;
            const arc = el('circle', {
                class: `chart-arc chart-s${(i % 6) + 1}`, cx: size / 2, cy: size / 2, r: radius, fill: 'none', 'stroke-width': 26,
                'stroke-dasharray': `${Math.max(length - 1.5, 0.5)} ${circumference}`, 'stroke-dashoffset': -offset, transform: `rotate(-90 ${size / 2} ${size / 2})`,
            });
            arc.addEventListener('pointermove', () => this.showTip(this.labelsValue[i], [{ name: null, value }], size / 2, size / 2 - radius - 14, `${Math.round((value / this.total) * 100)} %`));
            arc.addEventListener('pointerleave', () => this.hideTip());
            svg.append(arc);
            parts.push(arc);
            offset += length;
        });
        const total = el('text', { class: 'chart-center', x: size / 2, y: size / 2 + 2, 'text-anchor': 'middle' });
        total.textContent = this.format(this.total);
        svg.append(total);
        if (this.centerLabelValue) {
            const caption = el('text', { class: 'chart-axis', x: size / 2, y: size / 2 + 20, 'text-anchor': 'middle' });
            caption.textContent = this.centerLabelValue;
            svg.append(caption);
        }
        this.plotTarget.replaceChildren(svg);

        const legend = this.labelsValue.map((label, i) => ({ name: label, index: i, value: values[i] })).filter((item) => item.value > 0);
        this.drawLegend(legend, true);
    }

    // ------------------------------------------------------------ légende, infobulle

    drawLegend(items, withValues = false) {
        if (!this.hasLegendTarget) {
            return;
        }
        const list = html('ul', 'chart-legend');
        items.forEach((item) => {
            const li = html('li');
            li.append(html('span', `chart-sw chart-bg${(item.index % 6) + 1}`));
            li.append(html('span', 'chart-legend-name', item.name));
            if (withValues) {
                const percent = this.total > 0 ? Math.round((item.value / this.total) * 100) : 0;
                li.append(html('span', 'chart-legend-value', `${this.format(item.value)} · ${percent} %`));
            }
            list.append(li);
        });
        this.legendTarget.replaceChildren(list);
    }

    showTip(title, rows, left, top, extra = '') {
        if (!this.hasTooltipTarget) {
            return;
        }
        const box = document.createDocumentFragment();
        box.append(html('strong', '', title));
        rows.forEach((row) => {
            box.append(html('span', 'chart-tip-row', `${row.name ? `${row.name} : ` : ''}${this.format(row.value)}${extra ? ` · ${extra}` : ''}`));
        });
        this.tooltipTarget.replaceChildren(box);
        this.tooltipTarget.hidden = false;
        const width = this.plotTarget.getBoundingClientRect().width;
        const half = this.tooltipTarget.offsetWidth / 2;
        this.tooltipTarget.style.left = `${Math.max(half, Math.min(width - half, left))}px`;
        this.tooltipTarget.style.top = `${top}px`;
    }

    hideTip() {
        if (this.hasTooltipTarget) {
            this.tooltipTarget.hidden = true;
        }
    }
}
