import { Controller } from '@hotwired/stimulus';

/**
 * Enregistrement et connexion par clé d'accès (passkey / WebAuthn), en s'appuyant sur les
 * méthodes natives du navigateur `PublicKeyCredential.parseCreationOptionsFromJSON` /
 * `.parseRequestOptionsFromJSON` et `credential.toJSON()`, qui suivent le format JSON standard
 * du W3C et correspondent exactement à ce que le serveur (Webauthn\Denormalizer\WebauthnSerializerFactory)
 * envoie et attend — aucune conversion base64url manuelle n'est donc nécessaire ici.
 */
export default class extends Controller {
    static targets = ['label', 'status', 'button'];
    static values = { optionsUrl: String, finishUrl: String, csrfToken: String };

    async register() {
        if (!this.supported()) {
            return;
        }
        this.setStatus('');
        this.setBusy(true);
        try {
            const options = await this.fetchJson(this.optionsUrlValue);
            const creationOptions = PublicKeyCredential.parseCreationOptionsFromJSON(options);
            const credential = await navigator.credentials.create({ publicKey: creationOptions });
            const payload = {
                response: credential.toJSON(),
                label: this.hasLabelTarget ? this.labelTarget.value : '',
                _csrf_token: this.csrfTokenValue,
            };
            const res = await fetch(this.finishUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.ok) {
                window.location.reload();

                return;
            }
            this.setStatus(data.error || "L'enregistrement de la clé d'accès a échoué.");
        } catch (error) {
            this.setStatus(this.friendlyError(error));
        } finally {
            this.setBusy(false);
        }
    }

    async login() {
        if (!this.supported()) {
            return;
        }
        this.setStatus('');
        this.setBusy(true);
        try {
            const options = await this.fetchJson(this.optionsUrlValue);
            const requestOptions = PublicKeyCredential.parseRequestOptionsFromJSON(options);
            const credential = await navigator.credentials.get({ publicKey: requestOptions });
            const res = await fetch(this.finishUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ response: credential.toJSON() }),
            });
            // Si l'authenticator (ou la double authentification) a produit une redirection
            // classique plutôt qu'une réponse JSON, fetch l'a déjà suivie : on applique la même.
            if (res.redirected) {
                window.location.href = res.url;

                return;
            }
            let data = null;
            try {
                data = await res.json();
            } catch {
                data = null;
            }
            if (data?.redirect) {
                window.location.href = data.redirect;

                return;
            }
            this.setStatus(data?.error || 'La connexion a échoué.');
        } catch (error) {
            this.setStatus(this.friendlyError(error));
        } finally {
            this.setBusy(false);
        }
    }

    supported() {
        if (!window.PublicKeyCredential || !PublicKeyCredential.parseCreationOptionsFromJSON) {
            this.setStatus("Votre navigateur ne prend pas en charge les clés d'accès.");

            return false;
        }

        return true;
    }

    async fetchJson(url) {
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!res.ok) {
            throw new Error('Impossible de préparer la demande.');
        }

        return res.json();
    }

    friendlyError(error) {
        if ('NotAllowedError' === error.name) {
            return 'Opération annulée.';
        }

        return error.message || 'Une erreur est survenue.';
    }

    setStatus(message) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = message;
        }
    }

    setBusy(busy) {
        if (this.hasButtonTarget) {
            this.buttonTarget.disabled = busy;
        }
    }
}
