// Case à cocher ou liste à effet immédiat : <input type="checkbox" data-auto-submit> (ou <select data-auto-submit>) envoie son formulaire dès qu'on la bascule.
// (Remplace l'attribut onchange="…", interdit par la Content-Security-Policy du site.)
export function initAutoSubmit() {
    document.addEventListener('change', (event) => {
        const field = event.target;
        if ((field instanceof HTMLInputElement || field instanceof HTMLSelectElement) && field.hasAttribute('data-auto-submit') && field.form) {
            field.form.requestSubmit();
        }
    });
}
