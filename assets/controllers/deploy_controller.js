import { Controller } from '@hotwired/stimulus';
import { adminConfirm } from '../modal.js';

/**
 * Page « Mise à jour » : vérifie les mises à jour disponibles sur GitHub,
 * lance la mise à jour et affiche sa progression en direct (elle tourne en
 * arrière-plan côté serveur, on relit son journal toutes les secondes).
 * Permet aussi de supprimer, après validation, la sauvegarde des fichiers d'une mise à jour.
 */
export default class extends Controller {
    static targets = ['checkButton', 'deployButton', 'pending', 'pendingList', 'summary', 'console', 'log', 'state', 'result'];
    static values = {
        checkUrl: String,
        startUrl: String,
        statusUrl: String, // contient __id__
        token: String,
        runningId: Number,
    };

    connect() {
        if (this.runningIdValue > 0) {
            this.follow(this.runningIdValue);
        }
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    async request(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: { 'X-CSRF-Token': this.tokenValue, Accept: 'application/json', ...(options.headers ?? {}) },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.error || `Erreur ${response.status}`);
        }

        return data;
    }

    async check() {
        this.checkButtonTarget.disabled = true;
        this.checkButtonTarget.classList.add('is-loading');
        this.summaryTarget.classList.remove('is-error');
        this.summaryTarget.textContent = 'Interrogation de GitHub…';
        try {
            const data = await this.request(this.checkUrlValue, { method: 'POST' });
            this.renderPending(data);
        } catch (error) {
            this.summaryTarget.textContent = error.message;
            this.summaryTarget.classList.add('is-error');
        } finally {
            this.checkButtonTarget.disabled = false;
            this.checkButtonTarget.classList.remove('is-loading');
        }
    }

    renderPending(data) {
        this.summaryTarget.classList.toggle('is-error', !!data.error);
        this.pendingListTarget.replaceChildren();

        if (data.error) {
            this.summaryTarget.textContent = data.error;
            this.pendingTarget.hidden = true;
            this.deployButtonTarget.disabled = true;

            return;
        }

        const count = data.pending.length;
        this.summaryTarget.textContent = count === 0
            ? `Le site est à jour (branche ${data.branch}).`
            : `${count} mise${count > 1 ? 's' : ''} à jour disponible${count > 1 ? 's' : ''} sur la branche ${data.branch}.`;
        this.pendingTarget.hidden = count === 0;
        this.deployButtonTarget.disabled = count === 0 || data.dirty;
        if (data.dirty) {
            this.summaryTarget.textContent += ' Des fichiers ont été modifiés à la main sur le serveur : le déploiement est bloqué.';
            this.summaryTarget.classList.add('is-error');
        }

        data.pending.forEach((commit) => {
            const li = document.createElement('li');
            li.className = 'dy-commit';
            const hash = document.createElement('code');
            hash.textContent = commit.short;
            const text = document.createElement('span');
            text.className = 'dy-commit-text';
            text.textContent = commit.subject;
            const meta = document.createElement('span');
            meta.className = 'dy-commit-meta';
            meta.textContent = `${commit.author} · ${commit.date}`;
            li.append(hash, text, meta);
            this.pendingListTarget.appendChild(li);
        });
    }

    async deploy() {
        const ok = await adminConfirm({
            title: 'Mettre le site à jour ?',
            message: "La base de données et les fichiers du site vont d'abord être sauvegardés, puis le code sera mis à jour depuis GitHub, la base migrée et le cache vidé. Si une étape échoue, le site est remis automatiquement dans son état d'avant. Le site reste accessible, mais quelques secondes de lenteur sont possibles.",
            confirmLabel: 'Mettre à jour',
        });
        if (ok) {
            this.start();
        }
    }

    async start() {
        this.deployButtonTarget.disabled = true;
        this.checkButtonTarget.disabled = true;
        try {
            const data = await this.request(this.startUrlValue, { method: 'POST' });
            this.follow(data.id);
        } catch (error) {
            this.summaryTarget.textContent = error.message;
            this.summaryTarget.classList.add('is-error');
            this.checkButtonTarget.disabled = false;
        }
    }

    /** Supprime l'archive des fichiers d'une mise à jour, après confirmation (la sauvegarde de la base est conservée). */
    async purge(event) {
        const { url, failed } = event.params;
        const ok = await adminConfirm({
            title: 'Supprimer la sauvegarde des fichiers ?',
            message: failed
                ? "Cette mise à jour a échoué. La sauvegarde des fichiers de l'ancienne version sera définitivement supprimée. La sauvegarde de la base de données est conservée."
                : "Le site fonctionne-t-il correctement avec la nouvelle version ? La sauvegarde des fichiers de l'ancienne version sera définitivement supprimée (retour arrière manuel impossible ensuite). La sauvegarde de la base de données est conservée.",
            confirmLabel: 'Supprimer',
            danger: true,
        });
        if (!ok) {
            return;
        }
        try {
            await this.request(url, { method: 'POST' });
            window.location.reload();
        } catch (error) {
            window.alert(error.message);
        }
    }

    follow(id) {
        this.consoleTarget.hidden = false;
        this.checkButtonTarget.disabled = true;
        this.deployButtonTarget.disabled = true;
        this.resultTarget.hidden = true;
        this.poll(id);
    }

    async poll(id) {
        try {
            const url = this.statusUrlValue.replace('__id__', String(id));
            const data = await this.request(url);
            this.logTarget.textContent = data.log;
            this.logTarget.scrollTop = this.logTarget.scrollHeight;
            this.stateTarget.textContent = data.status === 'running' ? (data.step || 'En cours…') : (data.status === 'success' ? 'Terminé' : 'Échec');
            this.stateTarget.dataset.status = data.status;

            if (data.status === 'running') {
                this.timer = setTimeout(() => this.poll(id), 1000);

                return;
            }
            this.finish(data);
        } catch (error) {
            // Le cache se vide en fin de déploiement : une requête peut échouer un instant, on réessaie.
            this.timer = setTimeout(() => this.poll(id), 2000);
        }
    }

    finish(data) {
        this.resultTarget.hidden = false;
        this.resultTarget.className = `dy-result ${data.status === 'success' ? 'is-success' : 'is-failed'}`;
        this.resultTarget.replaceChildren();
        const text = document.createElement('span');
        text.textContent = data.status === 'success'
            ? 'Mise à jour terminée. Vérifiez le site, puis rechargez cette page pour supprimer la sauvegarde des fichiers depuis l'historique.'
            : 'La mise à jour a échoué : consultez le journal ci-dessus (le site a été remis dans son état d'avant si le retour arrière a abouti).';
        this.resultTarget.appendChild(text);

        const reload = document.createElement('button');
        reload.type = 'button';
        reload.className = 'admin-btn admin-btn-ghost admin-btn-sm';
        reload.textContent = 'Recharger';
        reload.addEventListener('click', () => window.location.reload());
        this.resultTarget.appendChild(reload);

    }
}
