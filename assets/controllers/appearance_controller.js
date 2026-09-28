import { Controller } from '@hotwired/stimulus';

/** Aperçu immédiat du thème / de la couleur / de la densité choisis (avant enregistrement). */
export default class extends Controller {
    apply(event) {
        const { name, value } = event.target;
        const root = document.documentElement;

        if (name.endsWith('[theme]')) {
            if (value === 'system') {
                root.removeAttribute('data-theme');
            } else {
                root.setAttribute('data-theme', value);
            }
        } else if (name.endsWith('[colorScheme]')) {
            root.setAttribute('data-scheme', value);
        } else if (name.endsWith('[density]')) {
            root.setAttribute('data-density', value);
        }
    }
}
