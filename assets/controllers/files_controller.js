import { Controller } from '@hotwired/stimulus';
import { adminConfirm, adminPrompt } from '../modal.js';

const KIND_ICONS = {
    folder: 'fa-folder',
    image: 'fa-file-image',
    pdf: 'fa-file-pdf',
    doc: 'fa-file-word',
    sheet: 'fa-file-excel',
    slide: 'fa-file-powerpoint',
    text: 'fa-file-lines',
    archive: 'fa-file-zipper',
    video: 'fa-file-video',
    audio: 'fa-file-audio',
    other: 'fa-file',
};

const VIEW_TITLES = { recent: 'Récents', starred: 'Favoris' };

/**
 * Gestionnaire de fichiers : navigation dans les dossiers exposés par le
 * serveur, envoi (bouton ou glisser-déposer), création de dossier, renommage,
 * suppression, favoris, et visionneuse (images, PDF, vidéo, audio, texte).
 * Toute la logique de sécurité (dossiers protégés, extensions…) est côté serveur ;
 * ce contrôleur ne fait que présenter ce que le serveur autorise.
 */
export default class extends Controller {
    static targets = ['content', 'crumbs', 'search', 'nav', 'modeButton', 'back', 'newFolder', 'upload', 'picker', 'drop', 'usageBar', 'usageText', 'viewer', 'viewerBody', 'viewerName', 'viewerMeta', 'viewerDownload', 'viewerPrev', 'viewerNext'];
    static values = {
        list: String,
        usage: String,
        mkdir: String,
        send: String,
        rename: String,
        remove: String,
        star: String,
        view: String,
        download: String,
        token: String,
    };

    connect() {
        this.state = { view: 'all', path: '', query: '' };
        this.entries = [];
        this.canWrite = false;
        this.mode = this.readStored('fm-mode') === 'list' ? 'list' : 'grid';
        this.dragDepth = 0;

        this.onHash = () => this.applyHash();
        window.addEventListener('hashchange', this.onHash);
        this.onKey = (event) => this.viewerKey(event);
        this.applyHash();
        this.updateModeButtons();
        this.loadUsage();
    }

    disconnect() {
        window.removeEventListener('hashchange', this.onHash);
        clearTimeout(this.searchTimer);
        this.closeMenu();
    }

    // -- Navigation ------------------------------------------------------------

    applyHash() {
        const hash = decodeURIComponent(window.location.hash.replace(/^#/, ''));
        if (hash === 'recent' || hash === 'starred') {
            this.state = { view: hash, path: '', query: '' };
        } else {
            this.state = { view: 'all', path: hash.replace(/^\/+/, ''), query: '' };
        }
        if (this.hasSearchTarget) {
            this.searchTarget.value = '';
        }
        this.load();
    }

    go(view, path = '') {
        const hash = view === 'all' ? (path ? `#/${path.split('/').map(encodeURIComponent).join('/')}` : '#/') : `#${view}`;
        if (window.location.hash === hash) {
            this.applyHash();
        } else {
            window.location.hash = hash;
        }
    }

    selectNav(event) {
        this.go(event.currentTarget.dataset.view);
    }

    goHome() {
        this.go('all');
    }

    goBack() {
        this.go('all', this.parentPath ?? '');
    }

    crumb(event) {
        this.go('all', event.currentTarget.dataset.path);
    }

    search() {
        clearTimeout(this.searchTimer);
        this.searchTimer = setTimeout(() => {
            this.state.query = this.searchTarget.value.trim();
            this.load(false);
        }, 250);
    }

    setMode(event) {
        this.mode = event.currentTarget.dataset.mode;
        this.storeValue('fm-mode', this.mode);
        this.updateModeButtons();
        this.renderEntries();
    }

    updateModeButtons() {
        this.modeButtonTargets.forEach((button) => button.classList.toggle('is-active', button.dataset.mode === this.mode));
    }

    // -- Requêtes --------------------------------------------------------------

    async api(url, { method = 'GET', body = null } = {}) {
        const response = await fetch(url, {
            method,
            body,
            headers: { 'X-CSRF-Token': this.tokenValue, Accept: 'application/json' },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.error || `Erreur ${response.status}`);
        }

        return data;
    }

    form(fields) {
        const body = new FormData();
        Object.entries(fields).forEach(([key, value]) => body.append(key, value));

        return body;
    }

    async load(showLoading = true) {
        const token = (this.loadToken = (this.loadToken ?? 0) + 1);
        if (showLoading) {
            this.contentTarget.classList.add('is-loading');
        }
        try {
            const params = new URLSearchParams({ vue: this.state.view, path: this.state.path });
            if (this.state.query) {
                params.set('q', this.state.query);
            }
            const data = await this.api(`${this.listValue}?${params}`);
            if (token !== this.loadToken) {
                return;
            }
            this.entries = data.entries;
            this.canWrite = data.canWrite;
            this.parentPath = data.parent;
            this.breadcrumb = data.breadcrumb;
            this.render();
        } catch (error) {
            if (token !== this.loadToken) {
                return;
            }
            this.toast(error.message, true);
            if (this.state.path !== '') {
                this.go('all');
            }
        } finally {
            this.contentTarget.classList.remove('is-loading');
        }
    }

    async loadUsage() {
        try {
            const { used, quota } = await this.api(this.usageValue);
            const ratio = quota > 0 ? Math.min(100, (used / quota) * 100) : 0;
            this.usageBarTarget.style.width = `${Math.max(ratio, used > 0 ? 1.5 : 0)}%`;
            this.usageTextTarget.textContent = `${this.formatSize(used)} sur ${this.formatSize(quota)} utilisés`;
        } catch {
            this.usageTextTarget.textContent = '';
        }
    }

    // -- Rendu -----------------------------------------------------------------

    render() {
        this.navTargets.forEach((item) => item.classList.toggle('is-active', item.dataset.view === this.state.view));
        this.backTarget.hidden = !(this.state.view === 'all' && this.state.path !== '' && !this.state.query);
        this.newFolderTarget.hidden = this.state.view !== 'all';
        this.uploadTarget.hidden = this.state.view !== 'all';
        this.newFolderTarget.disabled = !this.canWrite;
        this.uploadTarget.disabled = !this.canWrite;
        this.renderCrumbs();
        this.renderEntries();
    }

    renderCrumbs() {
        this.crumbsTarget.replaceChildren();
        const add = (label, { path = null, icon = null, current = false } = {}) => {
            if (this.crumbsTarget.childElementCount > 0) {
                const sep = document.createElement('i');
                sep.className = 'fa-solid fa-chevron-right fm-crumb-sep';
                this.crumbsTarget.appendChild(sep);
            }
            const el = document.createElement(current ? 'span' : 'button');
            el.className = `fm-crumb ${current ? 'is-current' : ''}`;
            if (icon) {
                el.innerHTML = `<i class="fa-solid ${icon}"></i> `;
            }
            el.append(label);
            if (!current) {
                el.type = 'button';
                el.dataset.path = path ?? '';
                el.addEventListener('click', path === null ? () => this.goHome() : (event) => this.crumb(event));
            }
            this.crumbsTarget.appendChild(el);
        };

        if (this.state.view !== 'all') {
            add(VIEW_TITLES[this.state.view], { current: true, icon: this.state.view === 'recent' ? 'fa-clock' : 'fa-star' });

            return;
        }
        const crumbs = this.breadcrumb ?? [];
        add('Fichiers', { path: null, icon: 'fa-house', current: crumbs.length === 0 && !this.state.query });
        crumbs.forEach((crumb, index) => add(crumb.name, { path: crumb.path, current: index === crumbs.length - 1 && !this.state.query }));
        if (this.state.query) {
            add(`Résultats pour « ${this.state.query} »`, { current: true });
        }
    }

    renderEntries() {
        const container = this.contentTarget;
        container.replaceChildren();
        container.classList.toggle('is-list', this.mode === 'list');

        if (this.entries.length === 0) {
            container.appendChild(this.emptyState());

            return;
        }

        const grid = document.createElement('div');
        grid.className = `fm-grid ${this.mode === 'list' ? 'is-list' : ''}`;
        if (this.mode === 'list') {
            const head = document.createElement('div');
            head.className = 'fm-list-head';
            head.innerHTML = '<span>Nom</span><span>Modifié</span><span>Taille</span><span></span>';
            grid.appendChild(head);
        }
        this.entries.forEach((entry) => grid.appendChild(this.itemElement(entry)));
        container.appendChild(grid);
    }

    emptyState() {
        const box = document.createElement('div');
        box.className = 'fm-empty';
        let icon = 'fa-folder-open';
        let title = 'Ce dossier est vide';
        let hint = this.canWrite ? 'Glissez des fichiers ici ou utilisez « Envoyer ».' : '';
        if (this.state.query) {
            icon = 'fa-magnifying-glass';
            title = 'Aucun fichier trouvé';
            hint = 'Essayez un autre nom.';
        } else if (this.state.view === 'starred') {
            icon = 'fa-star';
            title = 'Aucun favori';
            hint = 'Ajoutez un fichier ou un dossier aux favoris depuis son menu « … ».';
        } else if (this.state.view === 'recent') {
            icon = 'fa-clock';
            title = 'Aucun fichier récent';
            hint = '';
        }
        box.innerHTML = `<i class="fa-solid ${icon}"></i><strong></strong><span></span>`;
        box.querySelector('strong').textContent = title;
        box.querySelector('span').textContent = hint;

        return box;
    }

    itemElement(entry) {
        const item = document.createElement('div');
        item.className = 'fm-item';
        item.tabIndex = 0;
        item.setAttribute('role', 'button');
        item.dataset.path = entry.path;
        item.title = entry.name;

        const tile = document.createElement('span');
        tile.className = `fm-tile is-${entry.kind}`;
        if (entry.kind === 'image' && !entry.private) {
            const img = document.createElement('img');
            img.loading = 'lazy';
            img.alt = '';
            img.src = `${this.viewValue}?path=${encodeURIComponent(entry.path)}`;
            img.addEventListener('error', () => img.replaceWith(this.iconElement(entry)));
            tile.appendChild(img);
        } else {
            tile.appendChild(this.iconElement(entry));
        }

        const name = document.createElement('span');
        name.className = 'fm-name';
        name.textContent = entry.name;

        const meta = document.createElement('span');
        meta.className = 'fm-meta';
        const size = document.createElement('span');
        size.className = 'fm-size';
        const date = document.createElement('span');
        date.className = 'fm-date';
        if (entry.type === 'folder') {
            size.textContent = entry.count === null ? '' : `${entry.count} élément${entry.count > 1 ? 's' : ''}`;
        } else {
            size.textContent = this.formatSize(entry.size ?? 0);
            date.textContent = this.formatDate(entry.mtime);
        }
        meta.append(size, date);
        if (entry.type === 'folder') {
            date.textContent = this.formatDate(entry.mtime);
        }

        const flags = document.createElement('span');
        flags.className = 'fm-flags';
        if (entry.starred) {
            const star = document.createElement('i');
            star.className = 'fa-solid fa-star fm-star';
            star.title = 'Favori';
            flags.appendChild(star);
        }
        if (entry.readOnly || entry.protected) {
            const lock = document.createElement('i');
            lock.className = 'fa-solid fa-lock fm-lock';
            lock.title = entry.readOnly ? 'Lecture seule' : 'Dossier utilisé par le site : ne peut pas être supprimé';
            flags.appendChild(lock);
        }

        const more = document.createElement('button');
        more.type = 'button';
        more.className = 'fm-more';
        more.setAttribute('aria-label', `Actions pour ${entry.name}`);
        more.innerHTML = '<i class="fa-solid fa-ellipsis"></i>';
        more.addEventListener('click', (event) => {
            event.stopPropagation();
            this.openMenu(more, entry);
        });

        item.append(flags, more, tile, name, meta);
        item.addEventListener('click', () => this.open(entry));
        item.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && event.target === item) {
                this.open(entry);
            }
        });
        item.addEventListener('contextmenu', (event) => {
            event.preventDefault();
            this.openMenu(more, entry);
        });

        return item;
    }

    iconElement(entry) {
        const icon = document.createElement('i');
        icon.className = `fa-solid ${entry.icon ? `fa-${entry.icon}` : (KIND_ICONS[entry.kind] ?? KIND_ICONS.other)}`;

        return icon;
    }

    // -- Actions ---------------------------------------------------------------

    open(entry) {
        this.closeMenu();
        if (entry.type === 'folder') {
            this.go('all', entry.path);
        } else if (entry.previewable) {
            this.openViewer(entry);
        } else {
            this.download(entry);
        }
    }

    download(entry) {
        const link = document.createElement('a');
        link.href = `${this.downloadValue}?path=${encodeURIComponent(entry.path)}`;
        link.download = entry.name;
        document.body.appendChild(link);
        link.click();
        link.remove();
    }

    async createFolder() {
        const name = await adminPrompt({ title: 'Nouveau dossier', label: 'Nom du dossier', confirmLabel: 'Créer', placeholder: 'Ex. Joueurs 2026' });
        if (!name) {
            return;
        }
        try {
            await this.api(this.mkdirValue, { method: 'POST', body: this.form({ path: this.state.path, name }) });
            this.toast('Dossier créé.');
            this.load(false);
        } catch (error) {
            this.toast(error.message, true);
        }
    }

    pick() {
        this.pickerTarget.click();
    }

    picked() {
        const files = [...this.pickerTarget.files];
        this.pickerTarget.value = '';
        this.send(files);
    }

    async send(files) {
        if (files.length === 0 || !this.canWrite || this.state.view !== 'all') {
            return;
        }
        const toast = this.toast(`Envoi de ${files.length} fichier${files.length > 1 ? 's' : ''}…`, false, true);
        const body = this.form({ path: this.state.path });
        files.forEach((file) => body.append('files[]', file));
        try {
            const data = await this.api(this.sendValue, { method: 'POST', body });
            toast.remove();
            if (data.uploaded.length > 0) {
                this.toast(`${data.uploaded.length} fichier${data.uploaded.length > 1 ? 's' : ''} envoyé${data.uploaded.length > 1 ? 's' : ''}.`);
            }
            data.errors.forEach((message) => this.toast(message, true));
            this.load(false);
            this.loadUsage();
        } catch (error) {
            toast.remove();
            this.toast(error.message, true);
        }
    }

    async rename(entry) {
        const name = await adminPrompt({ title: entry.type === 'folder' ? 'Renommer le dossier' : 'Renommer le fichier', label: 'Nouveau nom', value: entry.name, confirmLabel: 'Renommer' });
        if (!name || name === entry.name) {
            return;
        }
        try {
            await this.api(this.renameValue, { method: 'POST', body: this.form({ path: entry.path, name }) });
            this.toast('Renommé.');
            this.load(false);
        } catch (error) {
            this.toast(error.message, true);
        }
    }

    async remove(entry) {
        const isFolder = entry.type === 'folder';
        const ok = await adminConfirm({
            title: isFolder ? 'Supprimer ce dossier ?' : 'Supprimer ce fichier ?',
            message: isFolder
                ? `Le dossier « ${entry.name} » et tout ce qu'il contient seront supprimés définitivement.`
                : `« ${entry.name} » sera supprimé définitivement.${entry.path.startsWith('fichiers-du-site/') ? ' Attention : ce fichier est peut-être utilisé sur le site (slide, logo, photo…).' : ''}`,
            confirmLabel: 'Supprimer',
            danger: true,
        });
        if (!ok) {
            return;
        }
        try {
            await this.api(this.removeValue, { method: 'POST', body: this.form({ path: entry.path }) });
            this.toast('Supprimé.');
            this.load(false);
            this.loadUsage();
        } catch (error) {
            this.toast(error.message, true);
        }
    }

    async toggleStar(entry) {
        try {
            const data = await this.api(this.starValue, { method: 'POST', body: this.form({ path: entry.path }) });
            this.toast(data.starred ? 'Ajouté aux favoris.' : 'Retiré des favoris.');
            this.load(false);
        } catch (error) {
            this.toast(error.message, true);
        }
    }

    // -- Menu contextuel -------------------------------------------------------

    openMenu(button, entry) {
        this.closeMenu();
        const panel = document.createElement('div');
        panel.className = 'admin-menu-panel';
        panel.setAttribute('role', 'menu');

        const items = [
            { icon: entry.starred ? 'fa-solid fa-star' : 'fa-regular fa-star', label: entry.starred ? 'Retirer des favoris' : 'Ajouter aux favoris', run: () => this.toggleStar(entry) },
        ];
        if (entry.type === 'file') {
            items.push({ icon: 'fa-solid fa-download', label: 'Télécharger', run: () => this.download(entry) });
        }
        if (!entry.readOnly && !entry.protected) {
            items.splice(1, 0, { icon: 'fa-solid fa-pen', label: 'Renommer', run: () => this.rename(entry) });
            items.push({ icon: 'fa-solid fa-trash', label: 'Supprimer', danger: true, run: () => this.remove(entry) });
        }

        items.forEach((entryItem) => {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = `admin-menu-item ${entryItem.danger ? 'is-danger' : ''}`;
            row.innerHTML = `<i class="${entryItem.icon}"></i>`;
            row.append(entryItem.label);
            row.addEventListener('click', (event) => {
                event.stopPropagation();
                this.closeMenu();
                entryItem.run();
            });
            panel.appendChild(row);
        });

        document.body.appendChild(panel);
        const rect = button.getBoundingClientRect();
        const width = panel.offsetWidth;
        const height = panel.offsetHeight;
        const openUp = rect.bottom + 6 + height > window.innerHeight - 8 && rect.top - 6 - height > 8;
        panel.style.top = `${openUp ? rect.top - 6 - height : rect.bottom + 6}px`;
        panel.style.left = `${Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8))}px`;

        this.menu = panel;
        this.menuOutside = (event) => {
            if (!panel.contains(event.target)) {
                this.closeMenu();
            }
        };
        this.menuKey = (event) => {
            if (event.key === 'Escape') {
                this.closeMenu();
            }
        };
        document.addEventListener('pointerdown', this.menuOutside, true);
        document.addEventListener('keydown', this.menuKey);
        window.addEventListener('scroll', this.menuDismiss = () => this.closeMenu(), true);
        panel.querySelector('button')?.focus();
    }

    closeMenu() {
        if (!this.menu) {
            return;
        }
        this.menu.remove();
        this.menu = null;
        document.removeEventListener('pointerdown', this.menuOutside, true);
        document.removeEventListener('keydown', this.menuKey);
        window.removeEventListener('scroll', this.menuDismiss, true);
    }

    // -- Glisser-déposer -------------------------------------------------------

    dragEnter(event) {
        if (!event.dataTransfer?.types?.includes('Files')) {
            return;
        }
        event.preventDefault();
        this.dragDepth += 1;
        if (this.canWrite && this.state.view === 'all') {
            this.dropTarget.hidden = false;
        }
    }

    dragOver(event) {
        if (event.dataTransfer?.types?.includes('Files')) {
            event.preventDefault();
        }
    }

    dragLeave() {
        this.dragDepth = Math.max(0, this.dragDepth - 1);
        if (this.dragDepth === 0) {
            this.dropTarget.hidden = true;
        }
    }

    drop(event) {
        if (!event.dataTransfer?.files?.length) {
            return;
        }
        event.preventDefault();
        this.dragDepth = 0;
        this.dropTarget.hidden = true;
        this.send([...event.dataTransfer.files]);
    }

    // -- Visionneuse -----------------------------------------------------------

    openViewer(entry) {
        this.viewerList = this.entries.filter((candidate) => candidate.type === 'file' && candidate.previewable);
        this.viewerIndex = Math.max(0, this.viewerList.findIndex((candidate) => candidate.path === entry.path));
        if (!this.viewerTarget.open) {
            this.viewerTarget.showModal();
            document.addEventListener('keydown', this.onKey);
        }
        this.showViewerEntry();
    }

    async showViewerEntry() {
        const entry = this.viewerList[this.viewerIndex];
        const src = `${this.viewValue}?path=${encodeURIComponent(entry.path)}`;
        const body = this.viewerBodyTarget;
        body.replaceChildren();
        body.dataset.kind = entry.kind;

        this.viewerNameTarget.textContent = entry.name;
        this.viewerMetaTarget.textContent = `${this.formatSize(entry.size ?? 0)} · ${this.formatDate(entry.mtime)} · ${this.viewerIndex + 1} / ${this.viewerList.length}`;
        this.viewerDownloadTarget.href = `${this.downloadValue}?path=${encodeURIComponent(entry.path)}`;
        this.viewerDownloadTarget.setAttribute('download', entry.name);
        this.viewerPrevTarget.hidden = this.viewerNextTarget.hidden = this.viewerList.length < 2;

        const token = (this.viewerToken = (this.viewerToken ?? 0) + 1);
        if (entry.kind === 'image') {
            const img = document.createElement('img');
            img.src = src;
            img.alt = entry.name;
            body.appendChild(img);
        } else if (entry.kind === 'pdf') {
            const frame = document.createElement('iframe');
            frame.src = src;
            frame.title = entry.name;
            body.appendChild(frame);
        } else if (entry.kind === 'video' || entry.kind === 'audio') {
            const media = document.createElement(entry.kind);
            media.src = src;
            media.controls = true;
            media.autoplay = false;
            body.appendChild(media);
        } else if (entry.kind === 'text') {
            const pre = document.createElement('pre');
            pre.textContent = 'Chargement…';
            body.appendChild(pre);
            try {
                const response = await fetch(src);
                const text = await response.text();
                if (token === this.viewerToken) {
                    pre.textContent = text.length > 200000 ? `${text.slice(0, 200000)}\n\n… (aperçu tronqué, téléchargez le fichier pour tout lire)` : text;
                }
            } catch {
                pre.textContent = 'Aperçu indisponible.';
            }
        }
    }

    viewerStep(event) {
        this.stepViewer(Number(event.currentTarget.dataset.step));
    }

    stepViewer(step) {
        if (this.viewerList.length < 2) {
            return;
        }
        this.viewerIndex = (this.viewerIndex + step + this.viewerList.length) % this.viewerList.length;
        this.showViewerEntry();
    }

    closeViewer() {
        this.viewerTarget.close();
    }

    viewerClosed() {
        document.removeEventListener('keydown', this.onKey);
        this.viewerBodyTarget.replaceChildren();
    }

    viewerBackdrop(event) {
        if (event.target === this.viewerTarget) {
            this.closeViewer();
        }
    }

    viewerKey(event) {
        if (event.key === 'ArrowLeft') {
            this.stepViewer(-1);
        } else if (event.key === 'ArrowRight') {
            this.stepViewer(1);
        }
    }

    // -- Utilitaires -----------------------------------------------------------

    toast(message, isError = false, sticky = false) {
        let host = document.querySelector('.fm-toasts');
        if (!host) {
            host = document.createElement('div');
            host.className = 'fm-toasts';
            host.setAttribute('role', 'status');
            document.body.appendChild(host);
        }
        const toast = document.createElement('div');
        toast.className = `fm-toast ${isError ? 'is-error' : ''}`;
        toast.textContent = message;
        host.appendChild(toast);
        if (!sticky) {
            setTimeout(() => toast.remove(), isError ? 6000 : 3000);
        }

        return toast;
    }

    formatSize(bytes) {
        if (bytes < 1024) {
            return `${bytes} o`;
        }
        const units = ['Ko', 'Mo', 'Go', 'To'];
        let value = bytes / 1024;
        let unit = 0;
        while (value >= 1024 && unit < units.length - 1) {
            value /= 1024;
            unit += 1;
        }

        return `${value.toLocaleString('fr-FR', { maximumFractionDigits: value >= 100 ? 0 : 1 })} ${units[unit]}`;
    }

    formatDate(timestamp) {
        if (!timestamp) {
            return '';
        }
        const date = new Date(timestamp * 1000);
        const now = new Date();
        const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());
        const days = Math.round((startOfDay(now) - startOfDay(date)) / 86400000);
        if (days === 0) {
            return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        }
        if (days === 1) {
            return 'Hier';
        }
        if (days > 1 && days < 7) {
            return `Il y a ${days} jours`;
        }

        return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    readStored(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    }

    storeValue(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            // stockage indisponible : le mode d'affichage ne sera simplement pas mémorisé
        }
    }
}
