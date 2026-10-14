// Navigation Turbo et Content-Security-Policy : le navigateur garde la politique (donc le nonce) de la première page.
// Chaque visite Turbo renvoie ce nonce au serveur (en-tête X-Csp-Nonce), qui le reprend dans sa réponse : les <style>
// et <script> de la page suivante, comme les styles que Turbo et Trix créent eux-mêmes, restent ainsi autorisés.
// Voir App\Security\CspNonce.
export function initCspNonce() {
    document.addEventListener('turbo:before-fetch-request', (event) => {
        const nonce = document.querySelector('meta[name="csp-nonce"]')?.content;
        if (nonce) {
            event.detail.fetchOptions.headers['X-Csp-Nonce'] = nonce;
        }
    });
}
