import { Controller } from '@hotwired/stimulus';

/**
 * Matrice d'autorisations : cases à cocher par élément et par action.
 * Créer, modifier et supprimer supposent de pouvoir voir : cocher l'un coche « voir »,
 * décocher « voir » retire les autres actions de la ligne.
 * setProfile() applique les autorisations d'un profil (liste de codes) à toute la matrice.
 */
export default class extends Controller {
    static targets = ['count', 'plural', 'master', 'groupMaster', 'rowMaster'];

    connect() {
        this.update();
        // Formulaire utilisateur : le choix d'un profil remplit la matrice.
        this.onProfile = (event) => {
            const option = event.target.selectedOptions?.[0];
            if (!option?.dataset.permissions) {
                return;
            }
            this.apply(JSON.parse(option.dataset.permissions));
        };
        document.querySelectorAll('[data-permission-profile]').forEach((select) => select.addEventListener('change', this.onProfile));
    }

    disconnect() {
        document.querySelectorAll('[data-permission-profile]').forEach((select) => select.removeEventListener('change', this.onProfile));
    }

    boxes(scope = this.element) {
        return [...scope.querySelectorAll('input[type="checkbox"][name="permissions[]"]:not(:disabled)')];
    }

    apply(codes) {
        const set = new Set(codes);
        this.boxes().forEach((box) => { box.checked = set.has(box.value); });
        this.update();
    }

    /** Interrupteur « Toutes les autorisations » : activé il coche tout, désactivé il décoche tout. */
    master(event) {
        this.boxes().forEach((box) => { box.checked = event.currentTarget.checked; });
        this.update();
    }

    /** Interrupteur d'une rubrique : même principe, limité à ses lignes. */
    groupToggle(event) {
        const checked = event.currentTarget.checked;
        this.boxes(event.currentTarget.closest('.pm-group')).forEach((box) => { box.checked = checked; });
        this.update();
    }

    readOnly() {
        this.boxes().forEach((box) => { box.checked = box.value.endsWith('.voir'); });
        this.update();
    }

    /** Interrupteur d'une ligne : coche ou décoche toutes les autorisations de l'élément. */
    rowToggle(event) {
        const checked = event.currentTarget.checked;
        this.boxes(event.currentTarget.closest('tr')).forEach((box) => { box.checked = checked; });
        this.update();
    }

    changed(event) {
        const box = event.currentTarget;
        const row = box.closest('tr');
        const view = row.querySelector('input[value$=".voir"]');
        if (view) {
            if (box.checked && box !== view) {
                view.checked = true;
            }
            if (!box.checked && box === view) {
                this.boxes(row).forEach((other) => { other.checked = false; });
            }
        }
        this.update();
    }

    update() {
        const count = this.boxes().filter((box) => box.checked).length;
        if (this.hasCountTarget) {
            this.countTarget.textContent = String(count);
            this.pluralTargets.forEach((el) => { el.textContent = count > 1 ? 's' : ''; });
        }
        // Les interrupteurs suivent l'état des cases : allumés seulement si tout est coché.
        const boxes = this.boxes();
        if (this.hasMasterTarget) {
            this.masterTarget.checked = boxes.length > 0 && boxes.every((box) => box.checked);
        }
        this.rowMasterTargets.forEach((toggle) => {
            const scoped = this.boxes(toggle.closest('tr'));
            toggle.checked = scoped.length > 0 && scoped.every((box) => box.checked);
        });
        this.groupMasterTargets.forEach((toggle) => {
            const scoped = this.boxes(toggle.closest('.pm-group'));
            toggle.checked = scoped.length > 0 && scoped.every((box) => box.checked);
        });
    }
}
