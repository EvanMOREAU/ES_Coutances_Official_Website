/*
 * Formats d'affichage de la messagerie (dates relatives, tailles de fichiers) : des fonctions pures, sans dépendance au DOM.
 */

export function dayKey(iso) {
    const d = new Date(iso);

    return `${d.getFullYear()}-${d.getMonth() + 1}-${d.getDate()}`;
}

export function daysAgo(iso) {
    const start = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());

    return Math.round((start(new Date()) - start(new Date(iso))) / 86400000);
}

export function formatTime(iso) {
    return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

export function formatDay(iso) {
    const days = daysAgo(iso);
    if (days === 0) {
        return "Aujourd'hui";
    }
    if (days === 1) {
        return 'Hier';
    }

    return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}

export function formatListTime(iso) {
    const days = daysAgo(iso);
    if (days === 0) {
        return formatTime(iso);
    }
    if (days === 1) {
        return 'Hier';
    }
    if (days < 7) {
        return new Date(iso).toLocaleDateString('fr-FR', { weekday: 'short' });
    }

    return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
}

export function formatLastSeen(iso) {
    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
    if (minutes < 1) {
        return "à l'instant";
    }
    if (minutes < 60) {
        return `il y a ${minutes} min`;
    }
    const days = daysAgo(iso);
    if (days === 0) {
        return `à ${formatTime(iso)}`;
    }
    if (days === 1) {
        return `hier à ${formatTime(iso)}`;
    }

    return `le ${new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })}`;
}

export function formatSize(bytes) {
    if (bytes < 1024) {
        return `${bytes} o`;
    }
    if (bytes < 1048576) {
        return `${Math.round(bytes / 1024)} Ko`;
    }

    return `${(bytes / 1048576).toFixed(1).replace('.', ',')} Mo`;
}
