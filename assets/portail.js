import './stimulus_bootstrap.js';
import './styles/custom-select.css';
import './styles/admin-components.css';
import './styles/admin-files.css';
import './styles/chat.css';
import './styles/planning.css';
import { initCustomSelects } from './custom_select.js';
import { initCustomDates } from './custom_date.js';
import { initCustomTimes } from './custom_time.js';
import { initConfirmForms } from './modal.js';

// Formulaires à confirmer (data-confirm) : fenêtre de confirmation du site.
initConfirmForms();

// Espace familles / licenciés : messagerie (fenêtres, listes déroulantes) aux couleurs du club.
const initInputs = () => {
    initCustomSelects();
    initCustomDates();
    initCustomTimes();
};
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initInputs);
} else {
    initInputs();
}
