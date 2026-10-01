import { Controller } from '@hotwired/stimulus';
import { adminConfirm } from '../modal.js';

const STATUS_LABELS = { active: 'actif', draft: 'brouillon', archived: 'archivé' };

const normalize = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

/**
 * Tableau de données du back-office : onglets de statut, recherche, tri par
 * colonne, pagination, sélection multiple avec actions groupées, et
 * affichage/masquage des colonnes mémorisé par utilisateur.
 *
 * Toutes les lignes sont rendues côté serveur ; le filtrage, le tri et la
 * pagination se font dans le navigateur (volumes d'un club : quelques
 * centaines de lignes au plus).
 */
export default class extends Controller {
    static targets = [
        'body', 'row', 'search', 'tab', 'sortHeader', 'selectAll', 'bulkBar', 'bulkCount',
        'info', 'pager', 'perPage', 'empty', 'columnToggle', 'table',
    ];

    static values = {
        key: String,
        hidden: Array,
        perPage: { type: Number, default: 10 },
        prefsUrl: String,
        prefsToken: String,
        bulkUrl: String,
        bulkToken: String,
        plural: { type: String, default: 'éléments' },
        defaultStatus: { type: String, default: 'all' },
    };

    connect() {
        this.rows = this.rowTargets.map((element, index) => ({
            element,
            index,
            id: element.dataset.id,
            status: element.dataset.status || '',
            // Écart transverse au statut (ex. "email invalide") : l'onglet spécial "non_conforme"
            // affiche ces lignes quel que soit leur statut réel (actif, brouillon, archivé…).
            problem: element.dataset.problem === '1',
            text: normalize(element.dataset.search || element.textContent),
        }));

        this.state = {
            status: this.defaultStatusValue,
            query: '',
            sortCol: null,
            sortDir: 'asc',
            page: 1,
            perPage: this.perPageValue,
            hidden: new Set(this.hiddenValue),
        };
        this.selected = new Set();

        this.columnToggleTargets.forEach((box) => {
            box.checked = !this.state.hidden.has(box.dataset.col);
        });

        this.applyColumns();
        this.render();

        // Ligne cliquable : data-href sur le <tr> ouvre la fiche, sauf clic sur un contrôle de la ligne.
        this.openRow = (event) => {
            const row = event.target.closest('tr[data-href]');
            if (!row || !row.dataset.href || event.target.closest('a, button, input, label, select, form, .dt-actions-col, .dt-check-col')) {
                return;
            }
            window.location.href = row.dataset.href;
        };
        this.element.addEventListener('click', this.openRow);
    }

    disconnect() {
        this.element.removeEventListener('click', this.openRow);
        clearTimeout(this.saveTimer);
    }

    // ---------------------------------------------------------------- filtres

    filterStatus(event) {
        this.state.status = event.currentTarget.dataset.status;
        this.state.page = 1;
        this.render();
    }

    search() {
        this.state.query = normalize(this.searchTarget.value);
        this.state.page = 1;
        this.render();
    }

    sort(event) {
        const col = event.currentTarget.dataset.col;
        if (this.state.sortCol !== col) {
            this.state.sortCol = col;
            this.state.sortDir = 'asc';
        } else if (this.state.sortDir === 'asc') {
            this.state.sortDir = 'desc';
        } else {
            this.state.sortCol = null; // retour à l'ordre du serveur
        }
        this.render();
    }

    changePerPage() {
        this.state.perPage = parseInt(this.perPageTarget.value, 10) || 10;
        this.state.page = 1;
        this.render();
        this.savePreferences();
    }

    goTo(event) {
        this.state.page = parseInt(event.currentTarget.dataset.page, 10);
        this.render();
    }

    // ---------------------------------------------------------------- colonnes

    toggleColumn(event) {
        const { col } = event.currentTarget.dataset;
        if (event.currentTarget.checked) {
            this.state.hidden.delete(col);
        } else {
            this.state.hidden.add(col);
        }
        this.applyColumns();
        this.savePreferences();
    }

    applyColumns() {
        this.tableTarget.querySelectorAll('[data-col]').forEach((cell) => {
            cell.hidden = this.state.hidden.has(cell.dataset.col);
        });
        // Garde la ligne "aucun résultat" alignée sur le nombre de colonnes visibles.
        if (this.hasEmptyTarget) {
            const visible = this.tableTarget.querySelectorAll('thead th:not([hidden])').length;
            this.emptyTarget.querySelector('td').colSpan = visible;
        }
    }

    savePreferences() {
        if (!this.prefsUrlValue) {
            return;
        }
        clearTimeout(this.saveTimer);
        this.saveTimer = setTimeout(() => {
            fetch(this.prefsUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.prefsTokenValue, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ hidden: [...this.state.hidden], perPage: this.state.perPage }),
                credentials: 'same-origin',
            }).catch(() => { /* préférence non critique */ });
        }, 400);
    }

    // --------------------------------------------------------------- sélection

    toggleAll(event) {
        const checked = event.currentTarget.checked;
        this.filtered.forEach((row) => {
            checked ? this.selected.add(row.id) : this.selected.delete(row.id);
        });
        this.renderSelection();
    }

    toggleRow(event) {
        const id = event.currentTarget.value;
        event.currentTarget.checked ? this.selected.add(id) : this.selected.delete(id);
        this.renderSelection();
    }

    clearSelection() {
        this.selected.clear();
        this.renderSelection();
    }

    async bulk(event) {
        const action = event.currentTarget.dataset.bulkAction;
        const count = this.selected.size;
        if (count === 0) {
            return;
        }

        if (action === 'delete') {
            const ok = await adminConfirm({
                title: `Supprimer ${count} élément${count > 1 ? 's' : ''} ?`,
                message: `Les ${count} ${this.pluralValue} sélectionnés seront supprimés définitivement. Cette action est irréversible.`,
                confirmLabel: 'Supprimer',
                danger: true,
            });
            if (!ok) {
                return;
            }
        } else if (action.startsWith('statut:')) {
            const label = STATUS_LABELS[action.slice(7)] ?? action;
            const ok = await adminConfirm({
                title: `Passer ${count} élément${count > 1 ? 's' : ''} en « ${label} » ?`,
                message: `Le statut des ${count} ${this.pluralValue} sélectionnés sera modifié.`,
                confirmLabel: 'Appliquer',
            });
            if (!ok) {
                return;
            }
        }

        const form = document.createElement('form');
        form.method = 'post';
        form.action = this.bulkUrlValue;
        const fields = { _token: this.bulkTokenValue, action };
        Object.entries(fields).forEach(([name, value]) => form.append(this.hiddenInput(name, value)));
        this.selected.forEach((id) => form.append(this.hiddenInput('ids[]', id)));
        document.body.appendChild(form);
        form.submit();
    }

    hiddenInput(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;

        return input;
    }

    // ------------------------------------------------------------------ rendu

    compute() {
        const { status, query, sortCol, sortDir } = this.state;

        let rows = this.rows.filter((row) => (status === 'all' || (status === 'non_conforme' ? row.problem : row.status === status))
            && (query === '' || row.text.includes(query)));

        if (sortCol) {
            const value = (row) => {
                const cell = row.element.querySelector(`[data-col="${sortCol}"]`);
                const raw = cell?.dataset.sort ?? cell?.textContent ?? '';

                return raw.trim();
            };
            const numeric = this.tableTarget.querySelector(`th[data-col="${sortCol}"]`)?.dataset.type === 'number';
            const factor = sortDir === 'asc' ? 1 : -1;
            rows = [...rows].sort((a, b) => {
                const va = value(a);
                const vb = value(b);
                const cmp = numeric
                    ? (parseFloat(va) || 0) - (parseFloat(vb) || 0)
                    : va.localeCompare(vb, 'fr', { numeric: true, sensitivity: 'base' });

                return (cmp || a.index - b.index) * factor;
            });
        }

        return rows;
    }

    render() {
        this.filtered = this.compute();
        const { perPage } = this.state;
        const pages = Math.max(1, Math.ceil(this.filtered.length / perPage));
        this.state.page = Math.min(this.state.page, pages);
        const start = (this.state.page - 1) * perPage;
        const visible = new Set(this.filtered.slice(start, start + perPage));

        // Ne garde en sélection que les lignes encore dans le filtre courant.
        const filteredIds = new Set(this.filtered.map((row) => row.id));
        this.selected.forEach((id) => { if (!filteredIds.has(id)) { this.selected.delete(id); } });

        // Réordonne le DOM selon le tri puis affiche uniquement la page courante.
        const order = this.state.sortCol ? this.filtered : [...this.rows].sort((a, b) => a.index - b.index);
        const fragment = document.createDocumentFragment();
        order.forEach((row) => fragment.appendChild(row.element));
        this.rows.filter((row) => !order.includes(row)).forEach((row) => fragment.appendChild(row.element));
        if (this.hasEmptyTarget) {
            fragment.appendChild(this.emptyTarget);
        }
        this.bodyTarget.appendChild(fragment);

        this.rows.forEach((row) => { row.element.hidden = !visible.has(row); });
        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = this.filtered.length > 0;
        }

        this.renderTabs();
        this.renderSortHeaders();
        this.renderPager(pages);
        this.renderInfo(start, visible.size);
        this.renderSelection();
    }

    renderTabs() {
        this.tabTargets.forEach((tab) => {
            const status = tab.dataset.status;
            const count = status === 'all' ? this.rows.length
                : status === 'non_conforme' ? this.rows.filter((row) => row.problem).length
                : this.rows.filter((row) => row.status === status).length;
            tab.querySelector('[data-count]').textContent = String(count);
            const active = status === this.state.status;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    renderSortHeaders() {
        this.sortHeaderTargets.forEach((th) => {
            const active = th.dataset.col === this.state.sortCol;
            th.setAttribute('aria-sort', active ? (this.state.sortDir === 'asc' ? 'ascending' : 'descending') : 'none');
            const icon = th.querySelector('[data-sort-icon]');
            if (icon) {
                icon.className = `fa-solid ${active ? (this.state.sortDir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down'} dt-sort-icon ${active ? 'is-active' : ''}`;
            }
        });
    }

    renderInfo(start, shown) {
        if (!this.hasInfoTarget) {
            return;
        }
        const total = this.filtered.length;
        this.infoTarget.textContent = total === 0
            ? '0 résultat'
            : `Affichage de ${start + 1} à ${start + shown} sur ${total} résultat${total > 1 ? 's' : ''}`;
    }

    renderPager(pages) {
        if (!this.hasPagerTarget) {
            return;
        }
        const current = this.state.page;
        const button = (label, page, { disabled = false, active = false, aria = null } = {}) => {
            const el = document.createElement('button');
            el.type = 'button';
            el.className = `dt-page ${active ? 'is-active' : ''}`;
            el.textContent = label;
            el.disabled = disabled;
            if (aria) {
                el.setAttribute('aria-label', aria);
            }
            if (active) {
                el.setAttribute('aria-current', 'page');
            }
            el.dataset.page = String(page);
            el.dataset.action = 'click->data-table#goTo';

            return el;
        };

        const items = [button('Précédent', current - 1, { disabled: current <= 1 })];
        const numbers = new Set([1, pages, current - 1, current, current + 1]);
        let previous = 0;
        [...numbers].filter((n) => n >= 1 && n <= pages).sort((a, b) => a - b).forEach((n) => {
            if (n - previous > 1) {
                const gap = document.createElement('span');
                gap.className = 'dt-gap';
                gap.textContent = '…';
                items.push(gap);
            }
            items.push(button(String(n), n, { active: n === current, aria: `Page ${n}` }));
            previous = n;
        });
        items.push(button('Suivant', current + 1, { disabled: current >= pages }));

        this.pagerTarget.replaceChildren(...items);
        this.pagerTarget.hidden = pages <= 1;
    }

    renderSelection() {
        const filteredIds = this.filtered.map((row) => row.id);
        const selectedInFilter = filteredIds.filter((id) => this.selected.has(id)).length;

        this.rows.forEach((row) => {
            const checked = this.selected.has(row.id);
            const box = row.element.querySelector('[data-row-check]');
            if (box) {
                box.checked = checked;
            }
            row.element.classList.toggle('is-selected', checked);
        });

        if (this.hasSelectAllTarget) {
            this.selectAllTarget.checked = filteredIds.length > 0 && selectedInFilter === filteredIds.length;
            this.selectAllTarget.indeterminate = selectedInFilter > 0 && selectedInFilter < filteredIds.length;
            this.selectAllTarget.disabled = filteredIds.length === 0;
        }

        if (this.hasBulkBarTarget) {
            const count = this.selected.size;
            this.bulkBarTarget.hidden = count === 0;
            this.bulkCountTarget.textContent = `${count} sélectionné${count > 1 ? 's' : ''}`;
        }
    }
}
