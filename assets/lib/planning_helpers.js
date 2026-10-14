// Constantes et fonctions pures du planning (dates, libellés, couleurs) : aucune dépendance au DOM ni à Stimulus.

export const MONTH = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' });
export const LONG_DATE = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
export const DAY_MONTH = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' });
export const SHORT_MONTH = new Intl.DateTimeFormat('fr-FR', { month: 'short' });
export const SHORT_WEEKDAY = new Intl.DateTimeFormat('fr-FR', { weekday: 'short' });
export const WEEKDAYS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
export const MAX_CHIPS = 3;
export const TONES = 8;

export const cap = (text) => text.charAt(0).toUpperCase() + text.slice(1);
export const pad = (n) => String(n).padStart(2, '0');
export const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
export const fromIso = (s) => {
    const [y, m, d] = s.split('-').map(Number);

    return new Date(y, m - 1, d);
};
export const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
export const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
export const tone = (category) => (category === 'Senior' ? 6 : (parseInt(category.slice(1), 10) || 0) % TONES);
export const toneOf = (event, category = null) => {
    if (event.type === 'rencontre') return 'match';
    if (event.type === 'evenement') return 'event';

    return tone(category ?? event.categories[0] ?? '');
};
/** Libellés de ce qu'une séance vise : « A vs B » pour une rencontre, le titre pour un
 *  événement interne, sinon catégories (ou équipes choisies) pour un entraînement. */
export const targets = (event) => {
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
export const partageLabel = (event) => {
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
export const timeRange = (event) => (event.fin ? `${event.debut} – ${event.fin}` : event.debut);
