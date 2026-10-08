// Case à cocher à effet immédiat : <input type="checkbox" data-auto-submit> envoie son formulaire dès qu'on la bascule.
// (Remplace l'attribut onchange="…", interdit par la Content-Security-Policy du site.)
export function initAutoSubmit() {
    document.addEventListener('change', (event) => {
        const field = event.target;
        if (field instanceof HTMLInputElement && field.hasAttribute('data-auto-submit') && field.form) {
            field.form.requestSubmit();
        }
    });
}
