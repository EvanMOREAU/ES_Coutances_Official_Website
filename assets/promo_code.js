/**
 * Aperçu en direct du code de réduction saisi à l'étape « Vos informations » de la commande
 * boutique : interroge le serveur (sans consommer le code) et met à jour le total affiché,
 * sans attendre la validation du formulaire. La vérification définitive (et l'incrémentation
 * du compteur d'usage) a toujours lieu côté serveur, à la validation de la commande.
 *
 * Gabarit attendu : un conteneur [data-promo-code] portant data-promo-code-url et
 * data-promo-code-sous-total (le sous-total du panier, en centimes), avec à l'intérieur
 * [data-promo-code-input], [data-promo-code-message], [data-promo-code-subtotal-row],
 * [data-promo-code-subtotal-amount], [data-promo-code-reduction-row],
 * [data-promo-code-reduction-amount] et [data-promo-code-total].
 */

const euros = (cents) => `${(cents / 100).toFixed(2).replace('.', ',')} €`;

function setup(root) {
    const input = root.querySelector('[data-promo-code-input]');
    const total = root.querySelector('[data-promo-code-total]');
    if (!input || !total) {
        return;
    }

    const message        = root.querySelector('[data-promo-code-message]');
    const sousTotalRow    = root.querySelector('[data-promo-code-subtotal-row]');
    const sousTotalAmount = root.querySelector('[data-promo-code-subtotal-amount]');
    const reductionRow    = root.querySelector('[data-promo-code-reduction-row]');
    const reductionAmount = root.querySelector('[data-promo-code-reduction-amount]');
    const url             = root.dataset.promoCodeUrl;
    const sousTotal       = Number(root.dataset.promoCodeSousTotal || 0);
    let timeout = null;

    const showMessage = (text) => {
        if (!message) {
            return;
        }
        message.textContent = text || '';
        message.hidden = !text;
    };

    const reset = () => {
        if (sousTotalRow) sousTotalRow.hidden = true;
        if (reductionRow) reductionRow.hidden = true;
        total.textContent = euros(sousTotal);
    };

    const verify = async () => {
        const code = input.value.trim();
        if ('' === code) {
            reset();
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
            return; // pas de réseau : le total reste inchangé, la validation finale tranchera
        }

        // La réponse peut arriver après que le champ a été vidé ou changé entre-temps.
        if (input.value.trim() !== code) {
            return;
        }

        if (!data.valide) {
            reset();
            showMessage(data.message || '');

            return;
        }

        showMessage(data.autoriseLivraison ? 'Ce code permet de se faire livrer : l\'adresse vous sera demandée à l\'étape suivante.' : '');

        const hasReduction = data.reductionCentimes > 0;
        if (sousTotalRow) sousTotalRow.hidden = !hasReduction;
        if (reductionRow) reductionRow.hidden = !hasReduction;
        if (hasReduction) {
            if (sousTotalAmount) sousTotalAmount.textContent = euros(data.sousTotalCentimes);
            if (reductionAmount) reductionAmount.textContent = `− ${euros(data.reductionCentimes)}`;
        }
        total.textContent = euros(data.totalCentimes);
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
