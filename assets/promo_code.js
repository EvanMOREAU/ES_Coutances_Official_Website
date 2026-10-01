/**
 * Vérification en direct du code de livraison saisi à l'étape « Vos informations » de la
 * commande boutique : interroge le serveur (sans consommer le code) pour prévenir tout de
 * suite le client qu'une étape de livraison suivra, sans attendre la validation du formulaire.
 * La vérification définitive (et l'incrémentation du compteur d'usage) a toujours lieu côté
 * serveur, à la validation de la commande.
 *
 * Gabarit attendu : un conteneur [data-promo-code] portant data-promo-code-url, avec à
 * l'intérieur [data-promo-code-input] et [data-promo-code-message].
 */

function setup(root) {
    const input = root.querySelector('[data-promo-code-input]');
    const message = root.querySelector('[data-promo-code-message]');
    if (!input || !message) {
        return;
    }

    const url = root.dataset.promoCodeUrl;
    let timeout = null;

    const showMessage = (text) => {
        message.textContent = text || '';
        message.hidden = !text;
    };

    const verify = async () => {
        const code = input.value.trim();
        if ('' === code) {
            showMessage('');

            return;
        }

        const requestUrl = new URL(url, window.location.href);
        requestUrl.searchParams.set('code', code);

        let data;
        try {
            const response = await fetch(requestUrl, { headers: { Accept: 'application/json' } });
            data = await response.json();
        } catch {
            return; // pas de réseau : la validation finale tranchera
        }

        // La réponse peut arriver après que le champ a été vidé ou changé entre-temps.
        if (input.value.trim() !== code) {
            return;
        }

        showMessage(data.valide
            ? 'Ce code permet de se faire livrer : l\'adresse vous sera demandée à l\'étape suivante.'
            : (data.message || ''));
    };

    input.addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(verify, 400);
    });
    input.addEventListener('blur', () => {
        clearTimeout(timeout);
        verify();
    });
}

export function initPromoCode() {
    document.querySelectorAll('[data-promo-code]').forEach(setup);
}
