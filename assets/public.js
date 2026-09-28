import './controllers/csrf_protection_controller.js';
import './styles/custom-select.css';
import { initCustomSelects } from './custom_select.js';
import { initCustomDates } from './custom_date.js';

// Site public : jeton CSRF envoyé aussi en cookie (double envoi, sans dépendre de l'en-tête Origin),
// listes déroulantes et sélecteurs de date personnalisés (formulaires de contact et de commande).
const initInputs = () => {
    initCustomSelects();
    initCustomDates();
};
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initInputs);
} else {
    initInputs();
}
