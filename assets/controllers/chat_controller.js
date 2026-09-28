import { Controller } from '@hotwired/stimulus';

const POLL_VISIBLE_MS = 2500;
const POLL_HIDDEN_MS = 12000;

/**
 * Messagerie en direct (admin et espace familles).
 *
 * Le navigateur interroge le serveur toutes les 2,5 s (12 s onglet masqué) :
 * la réponse contient la liste des discussions avec leurs non-lus et, pour la
 * discussion ouverte, uniquement les messages arrivés depuis le dernier affiché.
 * Aucun rechargement de page : les messages des autres apparaissent d'eux-mêmes.
 */
export default class extends Controller {
    static targets = [
        'side', 'list', 'search', 'thread', 'placeholder', 'conversation', 'headAvatar', 'headTitle', 'headStatus',
        'messages', 'jump', 'input', 'send',
        'newDialog', 'newHelp', 'newLabel', 'newSelect', 'subjectField', 'newSubject', 'newMessage', 'newError', 'newSubmit',
    ];
    static values = { base: String, token: String, staff: Boolean, open: Number, me: Number };

    connect() {
        this.conversations = [];
        this.activeId = 0;
        this.lastId = 0;
        this.oldestId = 0;
        this.hasMore = false;
        this.loadingOlder = false;
        this.contacts = null;
        this.pendingSeq = 0;
        this.baseTitle = document.title;
        this.filterText = '';
        this.running = true;

        this.onVisibility = () => {
            if (!document.hidden) {
                this.pollNow();
            }
        };
        document.addEventListener('visibilitychange', this.onVisibility);

        this.pollNow(true);
    }

    disconnect() {
        this.running = false;
        clearTimeout(this.timer);
        document.removeEventListener('visibilitychange', this.onVisibility);
        document.title = this.baseTitle;
    }

    // -- Réseau ----------------------------------------------------------------

    async api(path, { method = 'GET', body = null } = {}) {
        const response = await fetch(`${this.baseValue}/api/${path}`, {
            method,
            headers: {
                'X-CSRF-Token': this.tokenValue,
                Accept: 'application/json',
                ...(body ? { 'Content-Type': 'application/json' } : {}),
            },
            body: body ? JSON.stringify(body) : null,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(data.error || `Erreur ${response.status}`);
            error.status = response.status;
            throw error;
        }

        return data;
    }

    pollNow(first = false) {
        clearTimeout(this.timer);
        this.poll(first);
    }

    async poll(first = false) {
        if (!this.running || this.polling) {
            return;
        }
        this.polling = true;
        const requestedActive = this.activeId;
        try {
            const params = new URLSearchParams({ active: String(requestedActive), after: String(this.lastId) });
            const data = await this.api(`sync?${params}`);
            this.conversations = data.conversations;
            this.renderList();
            this.updateBadges(data.unread);

            if (requestedActive === this.activeId && data.active) {
                this.applyIncoming(data.active);
            }
            this.refreshHeader();

            if (first && this.openValue > 0 && this.conversations.some((c) => c.id === this.openValue)) {
                this.openConversation(this.openValue);
            }
            this.failures = 0;
        } catch (error) {
            this.failures = (this.failures ?? 0) + 1;
            if (error.status === 401 || error.status === 403) {
                window.location.reload();

                return;
            }
        } finally {
            this.polling = false;
            if (this.running) {
                const base = document.hidden ? POLL_HIDDEN_MS : POLL_VISIBLE_MS;
                this.timer = setTimeout(() => this.poll(), Math.min(base * (1 + (this.failures ?? 0)), 30000));
            }
        }
    }

    // -- Liste des discussions ---------------------------------------------------

    filter() {
        this.filterText = this.searchTarget.value.trim().toLowerCase();
        this.renderList();
    }

    renderList() {
        const list = this.listTarget;
        const scroll = list.scrollTop;
        list.replaceChildren();

        const visible = this.conversations.filter((c) => !this.filterText
            || c.title.toLowerCase().includes(this.filterText)
            || c.preview.toLowerCase().includes(this.filterText));

        if (visible.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'ch-list-empty';
            empty.textContent = this.conversations.length === 0 ? 'Aucune discussion pour le moment.' : 'Aucun résultat.';
            list.appendChild(empty);

            return;
        }

        visible.forEach((conversation) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = `ch-item ${conversation.id === this.activeId ? 'is-active' : ''} ${conversation.unread > 0 ? 'has-unread' : ''}`;
            item.setAttribute('role', 'listitem');
            item.addEventListener('click', () => this.openConversation(conversation.id));

            const text = document.createElement('span');
            text.className = 'ch-item-text';
            const top = document.createElement('span');
            top.className = 'ch-item-top';
            const title = document.createElement('strong');
            title.textContent = conversation.title;
            const time = document.createElement('time');
            time.textContent = this.formatListTime(conversation.previewAt);
            top.append(title, time);
            const bottom = document.createElement('span');
            bottom.className = 'ch-item-bottom';
            const preview = document.createElement('span');
            preview.className = 'ch-item-preview';
            preview.textContent = conversation.preview;
            bottom.appendChild(preview);
            if (conversation.unread > 0 && conversation.id !== this.activeId) {
                const badge = document.createElement('span');
                badge.className = 'ch-badge';
                badge.textContent = conversation.unread > 99 ? '99+' : String(conversation.unread);
                bottom.appendChild(badge);
            }
            text.append(top, bottom);

            item.append(this.avatar(conversation), text);
            list.appendChild(item);
        });
        list.scrollTop = scroll;
    }

    avatar(conversation) {
        const avatar = document.createElement('span');
        avatar.className = `ch-avatar ${conversation.group ? 'is-group' : ''}`;
        if (conversation.group) {
            avatar.innerHTML = '<i class="fa-solid fa-user-group"></i>';
        } else if (conversation.avatar) {
            const img = document.createElement('img');
            img.src = conversation.avatar;
            img.alt = '';
            avatar.appendChild(img);
        } else {
            avatar.textContent = conversation.initials;
        }
        if (!conversation.group) {
            const dot = document.createElement('span');
            dot.className = `ch-dot ${conversation.online ? 'is-online' : ''}`;
            avatar.appendChild(dot);
        }

        return avatar;
    }

    updateBadges(unread) {
        document.querySelectorAll('[data-chat-badge]').forEach((badge) => {
            badge.textContent = unread > 99 ? '99+' : String(unread);
            badge.hidden = unread === 0;
        });
        document.title = unread > 0 ? `(${unread}) ${this.baseTitle}` : this.baseTitle;
    }

    // -- Discussion ouverte ------------------------------------------------------

    async openConversation(id) {
        if (this.activeId === id && !this.conversationTarget.hidden) {
            return;
        }
        this.activeId = id;
        this.lastId = 0;
        this.oldestId = 0;
        this.hasMore = false;
        this.readUpTo = 0;
        this.element.classList.add('has-active');
        this.placeholderTarget.hidden = true;
        this.conversationTarget.hidden = false;
        this.messagesTarget.replaceChildren();
        this.jumpTarget.hidden = true;
        this.sendTarget.disabled = true;
        this.inputTarget.value = '';
        this.autosize();
        this.renderList();
        this.refreshHeader();
        this.rememberInUrl(id);

        try {
            const data = await this.api(`conversations/${id}`);
            if (this.activeId !== id) {
                return;
            }
            this.readUpTo = data.readUpTo;
            this.hasMore = data.hasMore;
            data.messages.forEach((message) => this.appendMessage(message));
            this.scrollToEnd();
            this.inputTarget.focus({ preventScroll: true });
            // Le serveur vient de marquer la discussion comme lue : on rafraîchit les compteurs.
            this.pollNow();
        } catch (error) {
            this.messagesTarget.textContent = error.message;
        }
    }

    closeConversation() {
        this.activeId = 0;
        this.element.classList.remove('has-active');
        this.conversationTarget.hidden = true;
        this.placeholderTarget.hidden = false;
        this.rememberInUrl(0);
        this.renderList();
    }

    rememberInUrl(id) {
        const url = new URL(window.location.href);
        id > 0 ? url.searchParams.set('c', String(id)) : url.searchParams.delete('c');
        window.history.replaceState(null, '', url);
    }

    /** Discussion de groupe : un clic sur le nom ou sur « N participants » affiche qui en fait partie. */
    showMembers() {
        const conversation = this.conversations.find((c) => c.id === this.activeId);
        if (!conversation?.group) {
            return;
        }
        const dialog = document.createElement('dialog');
        dialog.className = 'admin-modal';
        const rows = conversation.people.map((p) => `
            <li class="ch-member"><span class="ch-avatar">${p.name.slice(0, 1).toUpperCase()}</span>
                <span>${p.name.replace(/[<>&]/g, '')}${p.me ? ' <em>(vous)</em>' : ''}</span>
                ${p.staff ? '<small>Club</small>' : ''}</li>`).join('');
        dialog.innerHTML = `
            <div class="admin-modal-body">
                <h2 class="admin-modal-title"></h2>
                <p class="admin-modal-text">${conversation.people.length} participants</p>
                <ul class="ch-members">${rows}</ul>
                <div class="admin-modal-actions"><button type="button" class="admin-btn admin-btn-ghost">Fermer</button></div>
            </div>`;
        dialog.querySelector('.admin-modal-title').textContent = conversation.title;
        const close = () => {
            dialog.close();
            dialog.remove();
        };
        dialog.querySelector('button').addEventListener('click', close);
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                close();
            }
        });
        document.body.appendChild(dialog);
        dialog.showModal();
    }

    refreshHeader() {
        const conversation = this.conversations.find((c) => c.id === this.activeId);
        if (!conversation) {
            return;
        }
        this.headTitleTarget.textContent = conversation.title;
        this.headAvatarTarget.replaceWith(this.buildHeadAvatar(conversation));
        if (conversation.group) {
            this.headStatusTarget.textContent = `${conversation.members} participants`;
            this.headStatusTarget.dataset.state = 'group';
        } else if (conversation.online) {
            this.headStatusTarget.textContent = 'En ligne';
            this.headStatusTarget.dataset.state = 'online';
        } else {
            this.headStatusTarget.textContent = conversation.lastSeen ? `Vu ${this.formatLastSeen(conversation.lastSeen)}` : 'Hors ligne';
            this.headStatusTarget.dataset.state = 'offline';
        }
    }

    buildHeadAvatar(conversation) {
        const avatar = this.avatar(conversation);
        avatar.setAttribute('data-chat-target', 'headAvatar');

        return avatar;
    }

    applyIncoming(active) {
        const wasAtBottom = this.atBottom();
        let fromOthers = 0;
        active.messages.forEach((message) => {
            if (this.appendMessage(message) && !message.mine) {
                fromOthers += 1;
            }
        });
        if (active.readUpTo !== this.readUpTo) {
            this.readUpTo = active.readUpTo;
            this.updateReadTicks();
        }
        if (fromOthers > 0) {
            wasAtBottom ? this.scrollToEnd() : (this.jumpTarget.hidden = false);
        } else if (active.messages.length > 0 && wasAtBottom) {
            this.scrollToEnd();
        }
    }

    /** @returns {boolean} true si le message vient d'être ajouté (et n'était pas déjà affiché) */
    appendMessage(message) {
        if (this.messagesTarget.querySelector(`[data-id="${message.id}"]`)) {
            return false;
        }
        const row = this.messageElement(message);

        // Une bulle « en cours d'envoi » identique est remplacée par le vrai message.
        if (message.mine) {
            const pending = this.messagesTarget.querySelector('.ch-row.is-pending');
            if (pending && pending.dataset.body === message.body) {
                pending.replaceWith(row);
                this.lastId = Math.max(this.lastId, message.id);

                return false;
            }
        }

        this.insertDaySeparator(message);
        this.messagesTarget.appendChild(row);
        this.lastId = Math.max(this.lastId, message.id);
        if (this.oldestId === 0 || message.id < this.oldestId) {
            this.oldestId = message.id;
        }

        return true;
    }

    /** Ajoute « Aujourd'hui », « Hier »… quand on passe à un nouveau jour (les lignes et séparateurs portent data-day). */
    insertDaySeparator(message) {
        const day = this.dayKey(message.at);
        if (this.messagesTarget.lastElementChild?.dataset.day === day) {
            return;
        }
        const separator = document.createElement('div');
        separator.className = 'ch-day';
        separator.dataset.day = day;
        const label = document.createElement('span');
        label.textContent = this.formatDay(message.at);
        separator.appendChild(label);
        this.messagesTarget.appendChild(separator);
    }

    messageElement(message) {
        const conversation = this.conversations.find((c) => c.id === this.activeId);
        const row = document.createElement('div');
        row.className = `ch-row ${message.mine ? 'is-mine' : 'is-theirs'}`;
        row.dataset.id = String(message.id);
        row.dataset.day = this.dayKey(message.at);
        row.dataset.at = message.at;

        const bubble = document.createElement('div');
        bubble.className = 'ch-bubble';
        if (!message.mine && conversation?.group) {
            const author = document.createElement('span');
            author.className = 'ch-author';
            author.textContent = message.authorName;
            bubble.appendChild(author);
        }
        const body = document.createElement('span');
        body.className = 'ch-body';
        body.textContent = message.body;
        bubble.appendChild(body);

        const meta = document.createElement('span');
        meta.className = 'ch-meta';
        const time = document.createElement('time');
        time.textContent = this.formatTime(message.at);
        meta.appendChild(time);
        if (message.mine) {
            const tick = document.createElement('i');
            tick.className = `fa-solid ${message.read ? 'fa-check-double is-read' : 'fa-check'} ch-tick`;
            tick.title = message.read ? 'Lu' : 'Envoyé';
            meta.appendChild(tick);
        }

        row.append(bubble, meta);

        return row;
    }

    updateReadTicks() {
        this.messagesTarget.querySelectorAll('.ch-row.is-mine:not(.is-pending)').forEach((row) => {
            const read = Number(row.dataset.id) <= this.readUpTo;
            const tick = row.querySelector('.ch-tick');
            if (tick) {
                tick.className = `fa-solid ${read ? 'fa-check-double is-read' : 'fa-check'} ch-tick`;
                tick.title = read ? 'Lu' : 'Envoyé';
            }
        });
    }

    // -- Défilement / historique -------------------------------------------------

    atBottom() {
        const el = this.messagesTarget;

        return el.scrollHeight - el.scrollTop - el.clientHeight < 90;
    }

    scrollToEnd() {
        const el = this.messagesTarget;
        el.scrollTop = el.scrollHeight;
        this.jumpTarget.hidden = true;
    }

    jumpToEnd() {
        this.scrollToEnd();
    }

    onScroll() {
        if (this.atBottom()) {
            this.jumpTarget.hidden = true;
        }
        if (this.messagesTarget.scrollTop < 60 && this.hasMore && !this.loadingOlder) {
            this.loadOlder();
        }
    }

    async loadOlder() {
        const id = this.activeId;
        this.loadingOlder = true;
        try {
            const data = await this.api(`conversations/${id}?before=${this.oldestId}`);
            if (this.activeId !== id) {
                return;
            }
            const el = this.messagesTarget;
            const previousHeight = el.scrollHeight;
            this.hasMore = data.hasMore;

            // On insère du plus récent au plus ancien, au-dessus de ce qui est déjà affiché.
            const firstRow = el.querySelector('.ch-row');
            const fragment = document.createDocumentFragment();
            let previous = null;
            data.messages.forEach((message) => {
                if (previous?.dataset.day !== this.dayKey(message.at)) {
                    const separator = document.createElement('div');
                    separator.className = 'ch-day';
                    separator.dataset.day = this.dayKey(message.at);
                    separator.innerHTML = '<span></span>';
                    separator.firstChild.textContent = this.formatDay(message.at);
                    fragment.appendChild(separator);
                }
                const row = this.messageElement(message);
                fragment.appendChild(row);
                previous = row;
            });
            // Le premier message déjà affiché n'a plus besoin de son séparateur si c'est le même jour.
            const firstSeparator = el.querySelector('.ch-day');
            if (previous && firstRow && firstSeparator && firstSeparator.dataset.day === previous.dataset.day) {
                firstSeparator.remove();
            }
            el.prepend(fragment);
            if (data.messages.length > 0) {
                this.oldestId = data.messages[0].id;
            }
            el.scrollTop = el.scrollHeight - previousHeight;
        } catch {
            // l'historique se rechargera au prochain défilement
        } finally {
            this.loadingOlder = false;
        }
    }

    // -- Envoi -------------------------------------------------------------------

    autosize() {
        const input = this.inputTarget;
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 140)}px`;
        this.sendTarget.disabled = input.value.trim() === '';
    }

    composerKey(event) {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            this.submit(event);
        }
    }

    async submit(event) {
        event.preventDefault();
        const body = this.inputTarget.value.trim();
        if (body === '' || this.activeId === 0) {
            return;
        }
        const id = this.activeId;
        this.inputTarget.value = '';
        this.autosize();

        await this.sendMessage(id, body);
    }

    async sendMessage(id, body, existingRow = null) {
        const row = existingRow ?? this.pendingRow(body);
        row.classList.remove('is-error');
        row.querySelector('.ch-retry')?.remove();
        if (!existingRow) {
            this.insertDaySeparator({ at: new Date().toISOString() });
            this.messagesTarget.appendChild(row);
        }
        this.scrollToEnd();

        try {
            const { message } = await this.api(`conversations/${id}/messages`, { method: 'POST', body: { body } });
            if (this.activeId !== id) {
                return;
            }
            const existing = this.messagesTarget.querySelector(`.ch-row[data-id="${message.id}"]`);
            if (existing && existing !== row) {
                row.remove(); // l'interrogation régulière l'a déjà affiché
            } else {
                row.replaceWith(this.messageElement(message));
            }
            this.lastId = Math.max(this.lastId, message.id);
            this.pollNow();
        } catch (error) {
            row.classList.add('is-error');
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'ch-retry';
            retry.textContent = `${error.message} — Réessayer`;
            retry.addEventListener('click', () => this.sendMessage(id, body, row));
            row.querySelector('.ch-meta')?.replaceChildren(retry);
        }
    }

    pendingRow(body) {
        const now = new Date().toISOString();
        const row = this.messageElement({ id: `tmp${++this.pendingSeq}`, body, at: now, mine: true, read: false, authorName: '' });
        row.classList.add('is-pending');
        row.dataset.body = body;
        row.removeAttribute('data-id');
        row.querySelector('.ch-tick')?.classList.replace('fa-check', 'fa-clock');

        return row;
    }

    // -- Nouvelle discussion -----------------------------------------------------

    async openNew() {
        const dialog = this.newDialogTarget;
        this.newErrorTarget.hidden = true;
        this.newMessageTarget.value = '';
        this.newSubjectTarget.value = '';
        this.newHelpTarget.textContent = this.staffValue
            ? 'Choisissez une personne, ou plusieurs pour créer une discussion de groupe.'
            : 'Choisissez la personne du club à qui vous voulez écrire.';
        this.newLabelTarget.textContent = this.staffValue ? 'Destinataire(s)' : 'Destinataire';
        this.subjectFieldTarget.hidden = true;
        dialog.showModal();

        if (this.contacts === null) {
            try {
                this.contacts = (await this.api('contacts')).contacts;
            } catch (error) {
                this.showNewError(error.message);

                return;
            }
            this.fillContacts();
        }
    }

    fillContacts() {
        const select = this.newSelectTarget;
        select.replaceChildren();
        if (!select.multiple) {
            select.appendChild(new Option('Choisir…', ''));
        }
        this.contacts.forEach((contact) => {
            select.appendChild(new Option(`${contact.name} — ${contact.role}`, String(contact.id)));
        });
        select.addEventListener('change', () => {
            this.subjectFieldTarget.hidden = !(select.multiple && select.selectedOptions.length > 1);
        });
        select.dispatchEvent(new Event('change'));
    }

    closeNew() {
        this.newDialogTarget.close();
    }

    dialogBackdrop(event) {
        if (event.target === this.newDialogTarget) {
            this.closeNew();
        }
    }

    showNewError(message) {
        this.newErrorTarget.textContent = message;
        this.newErrorTarget.hidden = false;
    }

    async createConversation(event) {
        event.preventDefault();
        const participants = [...this.newSelectTarget.selectedOptions].map((option) => Number(option.value)).filter(Boolean);
        if (participants.length === 0) {
            this.showNewError('Choisissez au moins un destinataire.');

            return;
        }
        this.newSubmitTarget.disabled = true;
        try {
            const { id } = await this.api('conversations', {
                method: 'POST',
                body: { participants, subject: this.newSubjectTarget.value, message: this.newMessageTarget.value },
            });
            this.closeNew();
            // La nouvelle discussion doit figurer dans la liste avant d'être ouverte (titre de l'en-tête).
            this.conversations = (await this.api('sync?active=0&after=0')).conversations;
            this.activeId = 0;
            this.openConversation(id);
        } catch (error) {
            this.showNewError(error.message);
        } finally {
            this.newSubmitTarget.disabled = false;
        }
    }

    // -- Formats -----------------------------------------------------------------

    dayKey(iso) {
        const d = new Date(iso);

        return `${d.getFullYear()}-${d.getMonth() + 1}-${d.getDate()}`;
    }

    daysAgo(iso) {
        const start = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());

        return Math.round((start(new Date()) - start(new Date(iso))) / 86400000);
    }

    formatTime(iso) {
        return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    }

    formatDay(iso) {
        const days = this.daysAgo(iso);
        if (days === 0) {
            return "Aujourd'hui";
        }
        if (days === 1) {
            return 'Hier';
        }

        return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    formatListTime(iso) {
        const days = this.daysAgo(iso);
        if (days === 0) {
            return this.formatTime(iso);
        }
        if (days === 1) {
            return 'Hier';
        }
        if (days < 7) {
            return new Date(iso).toLocaleDateString('fr-FR', { weekday: 'short' });
        }

        return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
    }

    formatLastSeen(iso) {
        const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
        if (minutes < 1) {
            return "à l'instant";
        }
        if (minutes < 60) {
            return `il y a ${minutes} min`;
        }
        const days = this.daysAgo(iso);
        if (days === 0) {
            return `à ${this.formatTime(iso)}`;
        }
        if (days === 1) {
            return `hier à ${this.formatTime(iso)}`;
        }

        return `le ${new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })}`;
    }
}
