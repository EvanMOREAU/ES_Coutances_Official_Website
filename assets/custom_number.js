/**
 * Habille les <input type="number"> avec deux boutons −/+ à la place des
 * flèches natives du navigateur, dans le même esprit que le sélecteur
 * d'heure/date : l'<input> natif reste dans le DOM (valeur, validation,
 * envoi du formulaire), le composant ne fait qu'ajouter deux boutons qui
 * appellent stepUp()/stepDown() dessus.
 *
 * Attribut facultatif sur l'<input> : data-native (ne pas l'habiller).
 */

import { icon } from './ui_icons.js';

function step(input, direction) {
    if (input.disabled || input.readOnly) {
        return;
    }
    const before = input.value;
    direction > 0 ? input.stepUp() : input.stepDown();
    if (input.value !== before) {
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

function refresh(wrapper, input) {
    const min = input.min !== '' ? Number(input.min) : null;
    const max = input.max !== '' ? Number(input.max) : null;
    const value = input.value !== '' ? Number(input.value) : null;

    wrapper.querySelector('[data-role="dec"]').disabled = input.disabled || (min !== null && value !== null && value <= min);
    wrapper.querySelector('[data-role="inc"]').disabled = input.disabled || (max !== null && value !== null && value >= max);
    wrapper.classList.toggle('is-disabled', input.disabled);
}

function build(input) {
    const wrapper = document.createElement('div');
    wrapper.className = 'num-stepper';
    input.parentNode.insertBefore(wrapper, input);

    const dec = document.createElement('button');
    dec.type = 'button';
    dec.className = 'num-btn';
    dec.dataset.role = 'dec';
    dec.setAttribute('aria-label', 'Diminuer');
    dec.innerHTML = icon('minus', 13);
    dec.tabIndex = -1;
    dec.addEventListener('click', () => step(input, -1));

    const inc = document.createElement('button');
    inc.type = 'button';
    inc.className = 'num-btn';
    inc.dataset.role = 'inc';
    inc.setAttribute('aria-label', 'Augmenter');
    inc.innerHTML = icon('plus', 13);
    inc.tabIndex = -1;
    inc.addEventListener('click', () => step(input, 1));

    input.classList.add('num-input');
    wrapper.append(dec, input, inc);

    input.addEventListener('input', () => refresh(wrapper, input));
    input.addEventListener('change', () => refresh(wrapper, input));
    input.form?.addEventListener('reset', () => setTimeout(() => refresh(wrapper, input), 0));

    refresh(wrapper, input);
}

function enhance(input) {
    if (!(input instanceof HTMLInputElement) || input.type !== 'number' || input.dataset.native !== undefined || input.dataset.numberReady) {
        return;
    }
    input.dataset.numberReady = '1';
    build(input);
}

function enhanceWithin(root) {
    if (root instanceof HTMLInputElement) {
        enhance(root);
    } else if (root instanceof Element) {
        root.querySelectorAll('input[type="number"]').forEach(enhance);
    }
}

export function initCustomNumbers() {
    enhanceWithin(document.body);

    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach(enhanceWithin);
        }
    }).observe(document.body, { childList: true, subtree: true });
}
