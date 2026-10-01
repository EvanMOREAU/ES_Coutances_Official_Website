import { Controller } from '@hotwired/stimulus';
import { adminConfirm } from '../modal.js';

/**
 * Planning des entraînements : calendrier mensuel + panneau du jour + prochaines séances.
 *
 * Le même contrôleur sert l'admin (editable = true : ajout, modification, suppression)
 * et l'espace licencié (lecture seule). Les séances viennent d'une route JSON
 * (events-url) ; l'écriture passe par create-url / update-url / delete-url, où
 * « __id__ » est remplacé par l'identifiant de la séance.
 */

const MONTH = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' });
const LONG_DATE = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const DAY_MONTH = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' });
const SHORT_MONTH = new Intl.DateTimeFormat('fr-FR', { month: 'short' });
const SHORT_WEEKDAY = new Intl.DateTimeFormat('fr-FR', { weekday: 'short' });
const WEEKDAYS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
const MAX_CHIPS = 3;
const TONES = 8;

const cap = (text) => text.charAt(0).toUpperCase() + text.slice(1);
const pad = (n) => String(n).padStart(2, '0');
const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const fromIso = (s) => {
    const [y, m, d] = s.split('-').map(Number);

    return new Date(y, m - 1, d);
};
const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const tone = (category) => (category === 'Senior' ? 6 : (parseInt(category.slice(1), 10) || 0) % TONES);
const toneOf = (event, category = null) => {
    if (event.type === 'rencontre') return 'match';
    if (event.type === 'evenement') return 'event';

    return tone(category ?? event.categories[0] ?? '');
};
/** Libellés de ce qu'une séance vise : « A vs B » pour une rencontre, le titre pour un
 *  événement interne, sinon catégories (ou équipes choisies) pour un entraînement. */
const targets = (event) => {
    if (event.type === 'rencontre') {
        return [event.equipes.map((q) => q.nom).join(' vs ')];
    }
    if (event.type === 'evenement') {
        return [event.titre];
    }

    return event.categories.map((c) => {
        const teams = event.equipes.filter((q) => q.categorie === c).map((q) => q.nom);

        return teams.length ? teams.join(' / ') : c;
    });
};
/** Libellé du partage d'un événement interne, pour affichage dans la fiche. */
const partageLabel = (event) => {
    if (event.type !== 'evenement' || event.partage === 'tous') {
        return 'Tout le monde';
    }
    if (event.partage === 'utilisateurs') {
        const noms = event.partageUtilisateurs.map((u) => u.nom);

        return noms.length ? noms.join(', ') : 'Tout le monde';
    }
    const roleLabels = { ROLE_DEV: 'Développeur', ROLE_ADMIN: 'Administrateur', ROLE_EDITOR: 'Éditeur' };
    const parts = [...event.partageProfils.map((p) => p.nom), ...event.partageRoles.map((r) => roleLabels[r] ?? r)];

    return parts.length ? parts.join(', ') : 'Tout le monde';
};
const timeRange = (event) => (event.fin ? `${event.debut} – ${event.fin}` : event.debut);

export default class extends Controller {
    static targets = ['root'];
    static values = {
        eventsUrl: String,
        createUrl: String,
        updateUrl: String,
        deleteUrl: String,
        token: String,
        editable: Boolean,
        categories: Array,
        equipes: Array,
        filterCategories: Array,
        profils: Array,
        utilisateurs: Array,
    };

    connect() {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        this.today = today;
        this.view = 'month';
        this.anchor = today;
        this.selected = iso(today);
        this.filter = '';
        this.events = [];
        this.upcoming = [];
        this.loaded = false;
        this.requestId = 0;

        this.renderShell();
        this.load();
    }

    // ------------------------------------------------------------------ données

    async load() {
        const [first, last] = this.range();
        const query = new URLSearchParams({ start: iso(first), end: iso(last) });
        if (this.filter) {
            query.set('categorie', this.filter);
        }

        const request = ++this.requestId;
        const events = await this.getEvents(query);
        if (request !== this.requestId) {
            return; // une navigation plus récente a pris le relais
        }
        this.events = events ?? [];
        this.loaded = true;
        this.renderGrid();
        this.renderDay();
        this.loadUpcoming();
    }

    async loadUpcoming() {
        const query = new URLSearchParams({ prochains: '5' });
        if (this.filter) {
            query.set('categorie', this.filter);
        }
        this.upcoming = (await this.getEvents(query)) ?? [];
        this.renderUpcoming();
    }

    async getEvents(query) {
        try {
            const response = await fetch(`${this.eventsUrlValue}?${query}`, { headers: { Accept: 'application/json' } });

            return response.ok ? (await response.json()).events : null;
        } catch (error) {
            return null;
        }
    }

    async post(url, body) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.tokenValue, Accept: 'application/json' },
                body: JSON.stringify(body),
            });

            return { ok: response.ok, ...(await response.json().catch(() => ({}))) };
        } catch (error) {
            return { ok: false, error: 'Connexion impossible. Réessayez.' };
        }
    }

    eventsOn(date) {
        return this.events.filter((event) => event.date === date).sort((a, b) => a.debut.localeCompare(b.debut));
    }

    // ------------------------------------------------------------------ calendrier

    /** Mois affiché (le 1er) : dérivé du jour de référence. */
    get month() {
        return new Date(this.anchor.getFullYear(), this.anchor.getMonth(), 1);
    }

    /** Lundi de la semaine du jour de référence. */
    weekStart() {
        return addDays(this.anchor, -((this.anchor.getDay() + 6) % 7));
    }

    /** Période affichée selon la vue : mois complet (6 semaines au plus), semaine ou jour. */
    range() {
        if (this.view === 'day') {
            return [this.anchor, this.anchor];
        }
        if (this.view === 'week') {
            const start = this.weekStart();

            return [start, addDays(start, 6)];
        }
        const first = this.gridStart();

        return [first, addDays(first, this.weekCount() * 7 - 1)];
    }

    title() {
        if (this.view === 'day') {
            return cap(LONG_DATE.format(this.anchor));
        }
        if (this.view === 'week') {
            const start = this.weekStart();
            const end = addDays(start, 6);

            return `${DAY_MONTH.format(start)} – ${DAY_MONTH.format(end)} ${end.getFullYear()}`;
        }

        return cap(MONTH.format(this.month));
    }

    /** Premier jour affiché en vue mois : le lundi de la semaine du 1er du mois. */
    gridStart() {
        const offset = (this.month.getDay() + 6) % 7;

        return addDays(this.month, -offset);
    }

    weekCount() {
        const offset = (this.month.getDay() + 6) % 7;
        const days = new Date(this.month.getFullYear(), this.month.getMonth() + 1, 0).getDate();

        return Math.ceil((offset + days) / 7);
    }

    /** Décale la période affichée d'un mois, d'une semaine ou d'un jour. */
    shift(direction) {
        if (this.view === 'day') {
            this.anchor = addDays(this.anchor, direction);
            this.selected = iso(this.anchor);
        } else if (this.view === 'week') {
            this.anchor = addDays(this.anchor, 7 * direction);
        } else {
            this.anchor = new Date(this.anchor.getFullYear(), this.anchor.getMonth() + direction, 1);
        }
        this.refresh();
    }

    prev() {
        this.shift(-1);
    }

    next() {
        this.shift(1);
    }

    goToday() {
        this.anchor = this.today;
        this.selected = iso(this.today);
        this.refresh();
    }

    setView(event) {
        const view = event.params.view;
        if (view === this.view) {
            return;
        }
        this.view = view;
        // Le jour sélectionné devient la référence : on garde le même repère en changeant de vue.
        this.anchor = this.selected ? fromIso(this.selected) : this.anchor;
        if (view === 'day') {
            this.selected = iso(this.anchor);
        }
        this.refresh();
        this.renderDay();
    }

    setFilter(event) {
        this.filter = event.target.value;
        this.refresh();
    }

    refresh() {
        this.loaded = false;
        this.events = [];
        this.renderGrid();
        this.load();
    }

    select(event) {
        const date = event.params.date;
        this.selected = this.selected === date && !event.params.keep ? null : date;
        const picked = date ? fromIso(date) : null;
        // Un jour hors de la période affichée (mois voisin…) : on y navigue.
        if (picked) {
            const [first, last] = this.range();
            if (picked < first || picked > last || this.view === 'day') {
                this.selected = date;
                this.anchor = picked;
                this.refresh();
                this.renderDay();

                return;
            }
        }
        this.renderGrid();
        this.renderDay();
    }

    clearSelection() {
        this.selected = null;
        this.renderGrid();
        this.renderDay();
    }

    // ------------------------------------------------------------------ rendu

    renderShell() {
        const filters = this.filterCategoriesValue;
        const filter = filters.length > 1 || this.editableValue
            ? `<div class="pl-filter"><i class="fa-solid fa-filter"></i>
                   <select data-action="change->planning#setFilter" aria-label="Filtrer par catégorie">
                       <option value="">${this.editableValue ? 'Toutes les catégories' : 'Toutes mes catégories'}</option>
                       ${filters.map((c) => `<option value="${esc(c)}">${esc(c)}</option>`).join('')}
                   </select>
               </div>`
            : '';

        this.rootTarget.innerHTML = `
            <div class="pl-layout">
                <section class="pl-card pl-calendar" aria-label="Calendrier">
                    <div class="pl-toolbar">
                        <div class="pl-nav">
                            <button type="button" class="pl-icon-btn" data-action="planning#prev" aria-label="Mois précédent"><i class="fa-solid fa-chevron-left"></i></button>
                            <button type="button" class="pl-icon-btn" data-action="planning#next" aria-label="Mois suivant"><i class="fa-solid fa-chevron-right"></i></button>
                            <h2 class="pl-month" data-role="month" aria-live="polite"></h2>
                        </div>
                        <div class="pl-toolbar-end">
                            ${filter}
                            <div class="pl-views" role="group" aria-label="Affichage">
                                <button type="button" data-view="month" data-action="planning#setView" data-planning-view-param="month">Mois</button>
                                <button type="button" data-view="week" data-action="planning#setView" data-planning-view-param="week">Semaine</button>
                                <button type="button" data-view="day" data-action="planning#setView" data-planning-view-param="day">Jour</button>
                            </div>
                            <button type="button" class="pl-btn" data-action="planning#goToday">Aujourd'hui</button>
                        </div>
                    </div>
                    <div class="pl-weekdays">${WEEKDAYS.map((d) => `<span>${d}</span>`).join('')}</div>
                    <div class="pl-grid" data-role="grid"></div>
                </section>
                <aside class="pl-side">
                    <section class="pl-card pl-day" data-role="day" aria-live="polite"></section>
                    <section class="pl-card pl-upcoming" data-role="upcoming"></section>
                </aside>
            </div>`;
        this.renderDay();
        this.renderUpcoming();
    }

    part(role) {
        return this.rootTarget.querySelector(`[data-role="${role}"]`);
    }

    renderGrid() {
        this.part('month').textContent = this.title();
        this.rootTarget.querySelectorAll('[data-view]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.view === this.view);
            button.setAttribute('aria-pressed', String(button.dataset.view === this.view));
        });
        const layout = this.rootTarget.querySelector('.pl-layout');
        layout.classList.toggle('is-day', this.view === 'day');
        this.rootTarget.querySelector('.pl-weekdays').hidden = this.view !== 'month';
        if (this.view !== 'month') {
            this.renderPeriod();

            return;
        }
        this.part('grid').className = 'pl-grid';

        const first = this.gridStart();
        const todayIso = iso(this.today);
        let html = '';
        for (let i = 0; i < this.weekCount() * 7; i++) {
            const day = addDays(first, i);
            const key = iso(day);
            const events = this.eventsOn(key);
            const classes = ['pl-cell'];
            if (day.getMonth() !== this.month.getMonth()) classes.push('is-other');
            if (key === todayIso) classes.push('is-today');
            if (key === this.selected) classes.push('is-selected');

            const chips = events.slice(0, MAX_CHIPS).map((e) => {
                const label = targets(e).join(', ');
                const title = e.type === 'evenement' ? e.titre : `${e.titre} · ${label}`;

                return `<span class="pl-chip pl-tone-${toneOf(e)}" title="${esc(title)}">${esc(e.debut)} ${esc(label)}</span>`;
            }).join('');
            const more = events.length > MAX_CHIPS ? `<span class="pl-more">+${events.length - MAX_CHIPS}</span>` : '';
            const label = `${cap(LONG_DATE.format(day))}${events.length ? `, ${events.length} séance${events.length > 1 ? 's' : ''}` : ''}`;

            html += `<button type="button" class="${classes.join(' ')}" data-action="planning#select"
                             data-planning-date-param="${key}" aria-label="${esc(label)}" aria-pressed="${key === this.selected}">
                         <span class="pl-daynum">${day.getDate()}</span>
                         <span class="pl-chips">${chips}${more}</span>
                     </button>`;
        }
        const grid = this.part('grid');
        grid.style.setProperty('--pl-weeks', this.weekCount());
        grid.classList.toggle('is-loading', !this.loaded);
        grid.innerHTML = html;
    }

    /** Vues semaine (sept colonnes) et jour (liste détaillée). */
    renderPeriod() {
        const grid = this.part('grid');
        grid.style.removeProperty('--pl-weeks');
        grid.classList.toggle('is-loading', !this.loaded);
        const todayIso = iso(this.today);

        if (this.view === 'day') {
            const events = this.eventsOn(iso(this.anchor));
            grid.className = 'pl-dayview';
            grid.innerHTML = !this.loaded
                ? '<p class="pl-muted">Chargement…</p>'
                : events.length
                    ? events.map((event) => this.eventCard(event)).join('')
                    : `<p class="pl-muted pl-day-empty">${this.editableValue ? 'Aucune séance ce jour-là.' : 'Aucun entraînement prévu ce jour-là.'}</p>`;

            return;
        }

        const start = this.weekStart();
        grid.className = 'pl-weekview';
        grid.innerHTML = Array.from({ length: 7 }, (_, i) => {
            const day = addDays(start, i);
            const key = iso(day);
            const events = this.eventsOn(key);
            const classes = ['pl-wcol'];
            if (key === todayIso) classes.push('is-today');
            if (key === this.selected) classes.push('is-selected');
            const cards = events.map((e) => `
                <button type="button" class="pl-wcard pl-tone-${toneOf(e)}" data-action="planning#select" data-planning-date-param="${key}" data-planning-keep-param="true">
                    <b>${esc(e.debut)}${e.fin ? ` – ${esc(e.fin)}` : ''}</b>
                    <span>${esc(e.type === 'rencontre' ? e.titre : targets(e).join(' · '))}</span>
                    ${e.lieu ? `<small>${esc(e.lieu)}</small>` : ''}
                </button>`).join('');

            return `
                <div class="${classes.join(' ')}">
                    <button type="button" class="pl-whead" data-action="planning#select" data-planning-date-param="${key}" data-planning-keep-param="true">
                        <small>${WEEKDAYS[i]}</small><b>${day.getDate()}</b>
                    </button>
                    <div class="pl-wbody">${cards || '<span class="pl-wnone">—</span>'}</div>
                </div>`;
        }).join('');
    }

    renderDay() {
        const panel = this.part('day');
        if (!this.selected) {
            panel.innerHTML = `
                <div class="pl-placeholder">
                    <span class="pl-placeholder-icon"><i class="fa-regular fa-calendar"></i></span>
                    <strong>Aucune date sélectionnée</strong>
                    <span>Cliquez sur un jour du calendrier pour voir ses séances.</span>
                    ${this.editableValue ? `<button type="button" class="pl-btn is-primary" data-action="planning#add"><i class="fa-solid fa-plus"></i> Ajouter une séance</button>` : ''}
                </div>`;

            return;
        }

        const events = this.eventsOn(this.selected);
        const add = this.editableValue
            ? `<button type="button" class="pl-btn" data-action="planning#add"><i class="fa-solid fa-plus"></i> Ajouter</button>`
            : '';
        const body = !this.loaded
            ? '<p class="pl-muted">Chargement…</p>'
            : events.length
                ? events.map((event) => this.eventCard(event)).join('')
                : `<p class="pl-muted">${this.editableValue ? 'Aucune séance ce jour-là.' : 'Aucun entraînement prévu ce jour-là.'}</p>`;

        panel.innerHTML = `
            <div class="pl-card-head">
                <div>
                    <h3>${esc(cap(LONG_DATE.format(fromIso(this.selected))))}</h3>
                    <p class="pl-muted">${this.loaded ? `${events.length} séance${events.length > 1 ? 's' : ''}` : ''}</p>
                </div>
                <div class="pl-card-actions">
                    ${add}
                    <button type="button" class="pl-icon-btn" data-action="planning#clearSelection" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            <div class="pl-events">${body}</div>`;
    }

    eventCard(event) {
        const actions = this.editableValue
            ? `<span class="pl-event-actions">
                   <button type="button" class="pl-icon-btn" data-action="planning#edit" data-planning-id-param="${event.id}" aria-label="Modifier"><i class="fa-solid fa-pen"></i></button>
                   <button type="button" class="pl-icon-btn is-danger" data-action="planning#remove" data-planning-id-param="${event.id}" aria-label="Supprimer"><i class="fa-solid fa-trash"></i></button>
               </span>`
            : '';

        const icon = event.type === 'rencontre' ? 'fa-futbol' : event.type === 'evenement' ? 'fa-calendar-day' : null;
        const badges = event.type === 'evenement'
            ? `<span class="pl-badge pl-tone-event"><i class="fa-solid fa-lock"></i> ${esc(partageLabel(event))}</span>`
            : `${event.type === 'rencontre' ? '<span class="pl-badge pl-tone-match">Rencontre</span>' : ''}${event.categories.map((c) => `<span class="pl-badge pl-tone-${toneOf(event, c)}">${esc(c)}</span>`).join('')}${event.type === 'rencontre' ? '' : event.equipes.map((q) => `<span class="pl-badge is-team pl-tone-${toneOf(event, q.categorie)}">${esc(q.nom)}</span>`).join('')}`;

        return `
            <article class="pl-event pl-tone-${toneOf(event)}">
                <div class="pl-event-head">
                    <strong>${icon ? `<i class="fa-solid ${icon}"></i> ` : ''}${esc(event.titre)}</strong>
                    ${actions}
                </div>
                <p class="pl-event-meta"><i class="fa-regular fa-clock"></i> ${esc(timeRange(event))}${event.serie ? ' <span class="pl-tag"><i class="fa-solid fa-repeat"></i> Chaque semaine</span>' : ''}</p>
                ${event.lieu ? `<p class="pl-event-meta"><i class="fa-solid fa-location-dot"></i> ${esc(event.lieu)}</p>` : ''}
                ${event.description ? `<p class="pl-event-desc">${esc(event.description)}</p>` : ''}
                <div class="pl-badges">${badges}</div>
            </article>`;
    }

    renderUpcoming() {
        const panel = this.part('upcoming');
        const items = this.upcoming.map((event) => {
            const date = fromIso(event.date);

            return `
                <button type="button" class="pl-upcoming-item pl-tone-${toneOf(event)}" data-action="planning#select"
                        data-planning-date-param="${event.date}" data-planning-keep-param="true">
                    <span class="pl-date-badge"><b>${date.getDate()}</b><small>${esc(SHORT_MONTH.format(date).replace('.', ''))}</small></span>
                    <span class="pl-upcoming-text">
                        <strong>${esc(event.titre)}${event.type === 'entrainement' ? ` · ${esc(targets(event).join(', '))}` : ''}</strong>
                        <small>${esc(cap(SHORT_WEEKDAY.format(date).replace('.', '')))} · ${esc(timeRange(event))}${event.lieu ? ` · ${esc(event.lieu)}` : ''}</small>
                    </span>
                </button>`;
        }).join('');

        panel.innerHTML = `
            <div class="pl-card-head"><h3>À venir</h3></div>
            ${items ? `<div class="pl-upcoming-list">${items}</div>` : '<p class="pl-muted">Rien de prévu pour le moment.</p>'}`;
    }

    // ------------------------------------------------------------------ édition (admin)

    add() {
        this.openForm(null);
    }

    edit(event) {
        const found = this.events.find((e) => e.id === event.params.id);
        if (found) {
            this.openForm(found);
        }
    }

    async remove(event) {
        const found = this.events.find((e) => e.id === event.params.id);
        if (found) {
            await this.confirmDelete(found);
        }
    }

    /** Suppression avec confirmation ; pour une séance répétée, choix entre cette séance et les suivantes. */
    async confirmDelete(found, dialogToClose = null) {
        let scope = 'occurrence';
        if (found.serie) {
            scope = await this.askScope('Supprimer cette séance répétée', 'Supprimer');
            if (!scope) {
                return false;
            }
        } else {
            const ok = await adminConfirm({
                title: 'Supprimer cette séance ?',
                message: `« ${found.titre} » (${found.categories.join(', ')}) du ${LONG_DATE.format(fromIso(found.date))} sera supprimée.`,
                confirmLabel: 'Supprimer',
                danger: true,
            });
            if (!ok) {
                return false;
            }
        }

        const result = await this.post(this.deleteUrlValue.replace('__id__', found.id), { portee: scope });
        if (!result.ok) {
            await adminConfirm({ title: 'Suppression impossible', message: result.error ?? 'Une erreur est survenue.', confirmLabel: 'OK' });

            return false;
        }
        if (dialogToClose) {
            dialogToClose.close();
            dialogToClose.remove();
        }
        this.load();

        return true;
    }

    /** Petite fenêtre : « cette séance uniquement » ou « celle-ci et les suivantes ». */
    askScope(title, confirmLabel) {
        return new Promise((resolve) => {
            const dialog = document.createElement('dialog');
            dialog.className = 'admin-modal';
            dialog.innerHTML = `
                <form class="admin-modal-body" novalidate>
                    <h2 class="admin-modal-title">${esc(title)}</h2>
                    <div class="pl-scope">
                        <label><input type="radio" name="portee" value="occurrence" checked> Cette séance uniquement</label>
                        <label><input type="radio" name="portee" value="serie"> Cette séance et les suivantes</label>
                    </div>
                    <div class="admin-modal-actions">
                        <button type="button" data-role="cancel" class="admin-btn admin-btn-ghost">Annuler</button>
                        <button type="submit" class="admin-btn admin-btn-danger">${esc(confirmLabel)}</button>
                    </div>
                </form>`;
            let done = false;
            const finish = (answer) => {
                if (done) {
                    return;
                }
                done = true;
                dialog.close();
                dialog.remove();
                resolve(answer);
            };
            dialog.querySelector('form').addEventListener('submit', (e) => {
                e.preventDefault();
                finish(dialog.querySelector('input[name="portee"]:checked').value);
            });
            dialog.querySelector('[data-role="cancel"]').addEventListener('click', () => finish(null));
            // Échap et clic à côté : on gère la fermeture nous-mêmes pour toujours résoudre la promesse.
            dialog.addEventListener('cancel', (e) => {
                e.preventDefault();
                finish(null);
            });
            dialog.addEventListener('click', (e) => {
                if (e.target === dialog) {
                    finish(null);
                }
            });
            document.body.appendChild(dialog);
            dialog.showModal();
        });
    }

    openForm(existing) {
        const isEdit = existing !== null;
        const values = existing ?? { type: 'evenement', titre: '', categories: [], equipes: [], date: this.selected ?? iso(this.today), debut: '', fin: '', lieu: '', description: '', serie: false, partage: 'tous', partageProfils: [], partageRoles: [], partageUtilisateurs: [] };
        const chips = this.categoriesValue.map((c) => `
            <label class="pl-check pl-tone-${tone(c)}"><input type="checkbox" name="categories" value="${esc(c)}" ${values.categories.includes(c) ? 'checked' : ''}><span>${esc(c)}</span></label>`).join('');
        const teamIds = values.equipes.map((q) => q.id);
        // Rencontre : deux listes d'équipes (regroupées par catégorie).
        const groups = [...new Set(this.equipesValue.map((q) => q.categorie))];
        const teamOptions = (selected) => `<option value="">Choisir une équipe</option>${groups.map((g) => `<optgroup label="${esc(g)}">${this.equipesValue.filter((q) => q.categorie === g).map((q) => `<option value="${q.id}" ${q.id === selected ? 'selected' : ''}>${esc(q.nom)}</option>`).join('')}</optgroup>`).join('')}`;
        const isMatch = values.type === 'rencontre';
        const isEntrainement = values.type === 'entrainement';
        const isEvenement = values.type === 'evenement';
        const roleLabels = { ROLE_DEV: 'Développeur', ROLE_ADMIN: 'Administrateur', ROLE_EDITOR: 'Éditeur' };
        const profilIds = (values.partageProfils ?? []).map((p) => p.id);
        const roleValues = values.partageRoles ?? [];
        const utilisateurIds = (values.partageUtilisateurs ?? []).map((u) => u.id);
        const partageValue = values.partage ?? 'tous';
        const isGroupe = partageValue === 'groupe';
        const isUtilisateurs = partageValue === 'utilisateurs';

        const dialog = document.createElement('dialog');
        dialog.className = 'admin-modal admin-modal-wide pl-modal';
        dialog.innerHTML = `
            <form class="admin-modal-body" novalidate>
                <h2 class="admin-modal-title">${isEdit ? "Modifier l'événement" : 'Ajouter un événement'}</h2>
                <p class="admin-modal-text">${isEdit ? 'Les changements sont visibles tout de suite par les licenciés (ou comptes) concernés.' : "Planifiez un événement interne, un entraînement (catégories ou équipes), ou une rencontre entre deux équipes du club."}</p>

                <div class="pl-field">
                    <span class="admin-modal-label">Type</span>
                    <div class="pl-types" role="radiogroup">
                        <label class="pl-type"><input type="radio" name="type" value="evenement" ${isEvenement ? 'checked' : ''}><span><i class="fa-solid fa-calendar-day"></i> Événement interne</span></label>
                        <label class="pl-type"><input type="radio" name="type" value="entrainement" ${isEntrainement ? 'checked' : ''}><span><i class="fa-solid fa-person-running"></i> Entraînement</span></label>
                        <label class="pl-type"><input type="radio" name="type" value="rencontre" ${isMatch ? 'checked' : ''}><span><i class="fa-solid fa-futbol"></i> Rencontre interne</span></label>
                    </div>
                </div>

                <div class="pl-field">
                    <label class="admin-modal-label" for="pl-titre">Titre</label>
                    <input class="admin-modal-input" id="pl-titre" name="titre" maxlength="150" placeholder="${isMatch ? 'Automatique : Équipe A – Équipe B' : (isEvenement ? 'Ex. Réunion coachs, formation arbitrage…' : 'Entraînement')}" value="${esc(values.titre)}" autocomplete="off">
                    <p class="pl-error" data-error="titre"></p>
                </div>

                <div data-section="entrainement" ${isMatch || isEvenement ? 'hidden' : ''}>
                    <div class="pl-field">
                        <span class="admin-modal-label">Catégories <em>*</em></span>
                        <div class="pl-checks">${chips}</div>
                        <p class="pl-error" data-error="categories"></p>
                    </div>
                    <div class="pl-field pl-teams" data-teams hidden>
                        <span class="admin-modal-label">Équipes (facultatif)</span>
                        <div data-teams-list></div>
                    </div>
                </div>

                <div data-section="rencontre" ${isMatch ? '' : 'hidden'}>
                    <div class="pl-row pl-row-2">
                        <div class="pl-field">
                            <label class="admin-modal-label" for="pl-equipe-a">Équipe A <em>*</em></label>
                            <select class="admin-modal-input" id="pl-equipe-a" name="equipeA">${teamOptions(isMatch ? teamIds[0] : null)}</select>
                        </div>
                        <div class="pl-field">
                            <label class="admin-modal-label" for="pl-equipe-b">Équipe B <em>*</em></label>
                            <select class="admin-modal-input" id="pl-equipe-b" name="equipeB">${teamOptions(isMatch ? teamIds[1] : null)}</select>
                        </div>
                    </div>
                    <p class="pl-error" data-error="equipes"></p>
                </div>

                <div data-section="evenement" ${isEvenement ? '' : 'hidden'}>
                    <div class="pl-field">
                        <span class="admin-modal-label">Partagé à</span>
                        <div class="pl-types" role="radiogroup">
                            <label class="pl-type"><input type="radio" name="partage" value="tous" ${!isGroupe && !isUtilisateurs ? 'checked' : ''}><span><i class="fa-solid fa-users"></i> Tout le monde</span></label>
                            <label class="pl-type"><input type="radio" name="partage" value="groupe" ${isGroupe ? 'checked' : ''}><span><i class="fa-solid fa-user-group"></i> Groupe(s) précis</span></label>
                            <label class="pl-type"><input type="radio" name="partage" value="utilisateurs" ${isUtilisateurs ? 'checked' : ''}><span><i class="fa-solid fa-user"></i> Utilisateur(s) précis</span></label>
                        </div>
                    </div>
                    <div class="pl-field" data-partage-groupe ${isGroupe ? '' : 'hidden'}>
                        <span class="admin-modal-label">Rôles</span>
                        <div class="pl-checks">
                            ${Object.entries(roleLabels).map(([role, label]) => `
                                <label class="pl-check"><input type="checkbox" name="roles" value="${esc(role)}" ${roleValues.includes(role) ? 'checked' : ''}><span>${esc(label)}</span></label>`).join('')}
                        </div>
                        <span class="admin-modal-label" style="margin-top:.6rem;display:block;">Profils d'autorisation</span>
                        <div class="pl-checks">
                            ${this.profilsValue.map((p) => `
                                <label class="pl-check"><input type="checkbox" name="profils" value="${p.id}" ${profilIds.includes(p.id) ? 'checked' : ''}><span>${esc(p.nom)}</span></label>`).join('') || '<p class="pl-muted">Aucun profil d\'autorisation défini pour le moment.</p>'}
                        </div>
                        <p class="pl-error" data-error="partage"></p>
                    </div>
                    <div class="pl-field" data-partage-utilisateurs ${isUtilisateurs ? '' : 'hidden'}>
                        <span class="admin-modal-label">Comptes</span>
                        <div class="pl-checks">
                            ${this.utilisateursValue.map((u) => `
                                <label class="pl-check"><input type="checkbox" name="utilisateurs" value="${u.id}" ${utilisateurIds.includes(u.id) ? 'checked' : ''}><span>${esc(u.nom)}</span></label>`).join('') || '<p class="pl-muted">Aucun compte disponible.</p>'}
                        </div>
                        <p class="pl-error" data-error="partage"></p>
                    </div>
                </div>

                <div class="pl-row">
                    <div class="pl-field">
                        <label class="admin-modal-label" for="pl-date">Date <em>*</em></label>
                        <input class="admin-modal-input" id="pl-date" type="date" name="date" value="${esc(values.date)}">
                        <p class="pl-error" data-error="date"></p>
                    </div>
                    <div class="pl-field">
                        <label class="admin-modal-label" for="pl-debut">Début <em>*</em></label>
                        <input class="admin-modal-input" id="pl-debut" type="time" name="debut" value="${esc(values.debut)}">
                        <p class="pl-error" data-error="debut"></p>
                    </div>
                    <div class="pl-field">
                        <label class="admin-modal-label" for="pl-fin">Fin</label>
                        <input class="admin-modal-input" id="pl-fin" type="time" name="fin" value="${esc(values.fin)}">
                        <p class="pl-error" data-error="fin"></p>
                    </div>
                </div>

                <div class="pl-field">
                    <label class="admin-modal-label" for="pl-lieu">Lieu</label>
                    <input class="admin-modal-input" id="pl-lieu" name="lieu" maxlength="150" placeholder="Ex. Stade Michel Villette, terrain synthétique" value="${esc(values.lieu)}" autocomplete="off">
                    <p class="pl-error" data-error="lieu"></p>
                </div>

                <div class="pl-field">
                    <label class="admin-modal-label" for="pl-description">Description</label>
                    <textarea class="admin-modal-input" id="pl-description" name="description" rows="3" maxlength="2000" placeholder="Facultatif : matériel à prévoir, thème de la séance…">${esc(values.description)}</textarea>
                    <p class="pl-error" data-error="description"></p>
                </div>

                ${isEdit ? (values.serie ? `
                <div class="pl-field pl-scope">
                    <span class="admin-modal-label">Appliquer à</span>
                    <label><input type="radio" name="portee" value="occurrence" checked> Cette séance uniquement</label>
                    <label><input type="radio" name="portee" value="serie"> Cette séance et les suivantes</label>
                </div>` : '') : `
                <div class="pl-field pl-repeat">
                    <label class="pl-switch"><input type="checkbox" name="repeter"> <span>Répéter chaque semaine</span></label>
                    <div class="pl-repeat-until" hidden>
                        <label class="admin-modal-label" for="pl-jusqua">Jusqu'au</label>
                        <input class="admin-modal-input" id="pl-jusqua" type="date" name="jusqua">
                        <p class="pl-error" data-error="jusqua"></p>
                    </div>
                </div>`}

                <p class="pl-error pl-error-global" data-error="_global"></p>

                <div class="admin-modal-actions pl-actions">
                    ${isEdit ? '<button type="button" data-role="delete" class="admin-btn admin-btn-ghost pl-delete"><i class="fa-solid fa-trash"></i> Supprimer</button>' : ''}
                    <button type="button" data-role="cancel" class="admin-btn admin-btn-ghost">Annuler</button>
                    <button type="submit" class="admin-btn admin-btn-primary">${isEdit ? 'Enregistrer' : "Créer l'événement"}</button>
                </div>
            </form>`;

        const form = dialog.querySelector('form');

        // Bascule entraînement / rencontre / événement interne.
        const sync = () => {
            const type = form.querySelector('input[name="type"]:checked').value;
            form.querySelector('[data-section="entrainement"]').hidden = type !== 'entrainement';
            form.querySelector('[data-section="rencontre"]').hidden = type !== 'rencontre';
            form.querySelector('[data-section="evenement"]').hidden = type !== 'evenement';
            form.titre.placeholder = type === 'rencontre' ? 'Automatique : Équipe A – Équipe B' : (type === 'evenement' ? 'Ex. Réunion coachs, formation arbitrage…' : 'Entraînement');
        };
        form.querySelectorAll('input[name="type"]').forEach((radio) => radio.addEventListener('change', sync));

        // Partage d'un événement interne : tout le monde, un groupe précis (rôles / profils), ou des comptes précis.
        const syncPartage = () => {
            const partage = form.querySelector('input[name="partage"]:checked')?.value;
            form.querySelector('[data-partage-groupe]').hidden = partage !== 'groupe';
            form.querySelector('[data-partage-utilisateurs]').hidden = partage !== 'utilisateurs';
        };
        form.querySelectorAll('input[name="partage"]').forEach((radio) => radio.addEventListener('change', syncPartage));

        // Pour chaque catégorie cochée qui a des équipes : « toute la catégorie » ou une équipe précise.
        const renderTeams = () => {
            const checked = [...form.querySelectorAll('input[name="categories"]:checked')].map((box) => box.value);
            const withTeams = checked.filter((c) => this.equipesValue.some((q) => q.categorie === c));
            const holder = form.querySelector('[data-teams]');
            const list = form.querySelector('[data-teams-list]');
            const previous = Object.fromEntries([...list.querySelectorAll('select')].map((sel) => [sel.dataset.cat, sel.value]));
            holder.hidden = withTeams.length === 0;
            list.innerHTML = withTeams.map((c) => {
                const current = previous[c] ?? String(values.equipes.find((q) => q.categorie === c)?.id ?? '');

                return `<div class="pl-team-row"><span class="pl-badge pl-tone-${tone(c)}">${esc(c)}</span>
                    <select class="admin-modal-input" data-cat="${esc(c)}" aria-label="Équipe ${esc(c)}">
                        <option value="">Toute la catégorie ${esc(c)}</option>
                        ${this.equipesValue.filter((q) => q.categorie === c).map((q) => `<option value="${q.id}" ${String(q.id) === current ? 'selected' : ''}>${esc(q.nom)}</option>`).join('')}
                    </select></div>`;
            }).join('');
        };
        form.querySelectorAll('input[name="categories"]').forEach((box) => box.addEventListener('change', renderTeams));
        renderTeams();
        const repeat = form.querySelector('input[name="repeter"]');
        const until = form.querySelector('.pl-repeat-until');
        repeat?.addEventListener('change', () => {
            until.hidden = !repeat.checked;
            if (repeat.checked && !form.jusqua.value) {
                // Par défaut : environ deux mois de séances.
                form.jusqua.value = iso(addDays(fromIso(form.date.value || iso(this.today)), 7 * 8));
                form.jusqua.dispatchEvent(new Event('change', { bubbles: true })); // met à jour le sélecteur de date personnalisé
            }
        });

        const dismiss = () => {
            dialog.close();
            dialog.remove();
        };
        dialog.querySelector('[data-role="cancel"]').addEventListener('click', dismiss);
        dialog.querySelector('[data-role="delete"]')?.addEventListener('click', () => this.confirmDelete(existing, dialog));
        dialog.addEventListener('close', () => dialog.remove()); // Échap
        dialog.addEventListener('click', (e) => {
            if (e.target === dialog) dismiss();
        });
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitForm(form, dialog, existing);
        });

        document.body.appendChild(dialog);
        dialog.showModal();
        form.titre.focus();
    }

    async submitForm(form, dialog, existing) {
        const data = new FormData(form);
        const type = data.get('type');
        const equipes = type === 'rencontre'
            ? [data.get('equipeA'), data.get('equipeB')].filter(Boolean)
            : type === 'evenement'
                ? []
                : [...form.querySelectorAll('[data-teams-list] select')].map((sel) => sel.value).filter(Boolean);
        const body = {
            type,
            equipes,
            titre: data.get('titre'),
            categories: type === 'entrainement' ? data.getAll('categories') : [],
            date: data.get('date'),
            debut: data.get('debut'),
            fin: data.get('fin'),
            lieu: data.get('lieu'),
            description: data.get('description'),
        };
        if (type === 'evenement') {
            body.partage = data.get('partage') ?? 'tous';
            body.roles = data.getAll('roles');
            body.profils = data.getAll('profils');
            body.utilisateurs = data.getAll('utilisateurs');
        }
        if (existing) {
            body.portee = data.get('portee') ?? 'occurrence';
        } else {
            body.repeter = data.get('repeter') === 'on';
            body.jusqua = data.get('jusqua');
        }

        form.querySelectorAll('[data-error]').forEach((el) => { el.textContent = ''; });
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;

        const url = existing ? this.updateUrlValue.replace('__id__', existing.id) : this.createUrlValue;
        const result = await this.post(url, body);
        submit.disabled = false;

        if (!result.ok) {
            const errors = result.errors ?? { _global: result.error ?? 'Une erreur est survenue.' };
            Object.entries(errors).forEach(([field, message]) => {
                const target = form.querySelector(`[data-error="${field}"]`) ?? form.querySelector('[data-error="_global"]');
                target.textContent = message;
            });
            form.querySelector('.pl-error:not(:empty)')?.scrollIntoView({ block: 'nearest' });

            return;
        }

        dialog.close();
        dialog.remove();
        // On se place sur le mois de la séance créée / modifiée.
        const target = fromIso(body.date);
        this.selected = body.date;
        this.anchor = target;
        this.refresh();
    }
}
