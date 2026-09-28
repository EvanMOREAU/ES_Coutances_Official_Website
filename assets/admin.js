import './stimulus_bootstrap.js';
import './styles/admin.css';
import { initCustomSelects } from './custom_select.js';
import { initCustomDates } from './custom_date.js';
import { initCustomTimes } from './custom_time.js';
import { initCustomNumbers } from './custom_number.js';
import { initCustomCombobox } from './custom_combobox.js';
import { initConfirmForms } from './modal.js';

// Listes déroulantes, sélecteurs de date/heure, champs numériques, combobox « choisir ou créer » et fenêtres de confirmation (data-confirm) personnalisés.
initConfirmForms();
const initInputs = () => {
    initCustomSelects();
    initCustomDates();
    initCustomTimes();
    initCustomNumbers();
    initCustomCombobox();
};
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initInputs);
} else {
    initInputs();
}

// Retour visuel à l'envoi d'un formulaire : le bouton passe en "chargement" et
// n'est plus cliquable (évite les doubles envois).
document.addEventListener('submit', (event) => {
    if (event.defaultPrevented) {
        return;
    }

    const button = event.submitter;
    if (!button || button.type !== 'submit') {
        return;
    }

    button.classList.add('is-loading');
    setTimeout(() => { button.disabled = true; }, 0);
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('.is-loading').forEach((button) => {
        button.classList.remove('is-loading');
        button.disabled = false;
    });
});
