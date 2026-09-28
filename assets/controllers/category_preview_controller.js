import { Controller } from '@hotwired/stimulus';

/**
 * Affiche en direct la catégorie d'âge d'un licencié (U11, U12... Senior)
 * calculée d'après sa date de naissance, la saison choisie et l'éventuel
 * décalage (surclassement / sous-classement). Même règle que CategorieAge en PHP.
 */
export default class extends Controller {
    static targets = ['output'];
    static values = { min: { type: Number, default: 6 }, senior: { type: Number, default: 20 } };

    connect() {
        this.handler = () => this.update();
        this.element.addEventListener('input', this.handler);
        this.element.addEventListener('change', this.handler);
        this.update();
    }

    disconnect() {
        this.element.removeEventListener('input', this.handler);
        this.element.removeEventListener('change', this.handler);
    }

    field(name) {
        return this.element.querySelector(`[name$="[${name}]"]`);
    }

    label(age) {
        return age >= this.seniorValue ? 'Senior' : `U${Math.max(age, this.minValue)}`;
    }

    update() {
        const birth = this.field('dateNaissance')?.value;
        const saison = this.field('saison');
        const year = parseInt(saison?.selectedOptions?.[0]?.dataset?.year ?? '', 10);
        const decalage = parseInt(this.field('decalageCategorie')?.value ?? '0', 10) || 0;

        if (!birth || Number.isNaN(year)) {
            this.render('—', '');
            return;
        }

        const age = year - parseInt(birth.slice(0, 4), 10);
        const finale = this.label(age + decalage);
        const naturelle = this.label(age);
        this.render(finale, decalage !== 0 ? `catégorie naturelle : ${naturelle}` : 'selon son âge');

        // Les équipes d'une autre catégorie sont estompées dans la liste (option[data-dim]).
        this.element.querySelectorAll('option[data-categorie]').forEach((option) => {
            option.toggleAttribute('data-dim', option.dataset.categorie !== finale && !option.selected);
        });
    }

    render(categorie, detail) {
        if (this.hasOutputTarget) {
            this.outputTarget.innerHTML = `<span class="rounded-full admin-gradient px-3 py-1 text-sm font-bold text-white">${categorie}</span>
                <span class="ml-2 text-xs text-admin-text-muted">${detail}</span>`;
        }
    }
}
