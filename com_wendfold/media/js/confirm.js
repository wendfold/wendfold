/**
 * Wendfold - confirm before a control activates or a form posts.
 *
 * Copyright (C) 2026 Adan Lozano
 * GNU General Public License version 2 or later; see LICENSE.txt
 *
 * A control or form carries its prompt in data-confirm. The text is HTML-escaped
 * by the template and read back as a string. Cancel calls preventDefault.
 * Submit buttons are asked on the submit event so a keyboard submit asks once.
 * Other controls are asked on click. No library.
 */

const messageFrom = (el) => {
    if (!el || !el.dataset) {
        return '';
    }

    return el.dataset.confirm || '';
};

const ask = (message, event) => {
    if (message === '') {
        return;
    }

    if (!window.confirm(message)) {
        event.preventDefault();
        event.stopPropagation();
    }
};

const isSubmitControl = (el) => {
    if (el instanceof HTMLInputElement) {
        return el.type === 'submit' || el.type === 'image';
    }

    if (el instanceof HTMLButtonElement) {
        return el.type === 'submit' || el.type === '';
    }

    return false;
};

document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    const el = target.closest('[data-confirm]');

    if (!el || el instanceof HTMLFormElement || isSubmitControl(el)) {
        return;
    }

    ask(messageFrom(el), event);
}, true);

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    let submitter = event.submitter || null;

    if (!submitter && document.activeElement && document.activeElement.form === form) {
        submitter = document.activeElement;
    }

    let message = submitter ? messageFrom(submitter) : '';

    if (message === '') {
        message = messageFrom(form);
    }

    ask(message, event);
}, true);
