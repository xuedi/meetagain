/**
 * Venue Quick-Create — add a venue from the admin event form
 *
 * Appends a sentinel entry to the venue dropdown that opens a modal instead of selecting
 * anything. The sentinel is a button disguised as an option: the select is reverted the
 * moment the modal opens, so its value can never reach the event form on submit.
 *
 * Creation goes through the modal form's own submit event, so the browser's required-field
 * validation runs before the POST. The response either carries the new venue (inserted into
 * the dropdown and selected) or a field-keyed error map rendered under the inputs.
 *
 * Loaded in:  templates/admin/event/edit.html.twig, new.html.twig
 * Used by:    templates/admin/event/_venue_modal.html.twig
 * Depends on: ma-fetch.js (maFetch)
 */

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('venue-modal');
    if (!modal) return;

    const select = document.getElementById(modal.dataset.targetSelect);
    const form = modal.querySelector('form');
    const globalError = modal.querySelector('[data-venue-global-error]');
    if (!select || !form) return;

    const SENTINEL = '__new_venue__';
    let previousValue = select.value;

    const sentinel = document.createElement('option');
    sentinel.value = SENTINEL;
    sentinel.textContent = modal.dataset.newLabel;

    // A group with no venue yet renders an empty select, which would auto-select the sentinel and leave nothing to change to.
    if (select.options.length === 0) {
        select.appendChild(new Option('', ''));
    }

    select.appendChild(sentinel);

    function openModal() {
        modal.classList.add('is-active');
        const firstInput = form.querySelector('input, textarea');
        if (firstInput) firstInput.focus();
    }

    function closeModal() {
        modal.classList.remove('is-active');
    }

    function clearErrors() {
        modal.querySelectorAll('[data-venue-error]').forEach(node => node.remove());
        globalError.classList.add('is-hidden');
        globalError.textContent = '';
    }

    function showErrors(payload) {
        const fields = (payload && payload.errors) || {};
        Object.keys(fields).forEach(function (field) {
            const input = form.querySelector('[name$="[' + field + ']"]');
            const wrapper = input && input.closest('.field');
            if (!wrapper) return;

            const message = document.createElement('div');
            message.className = 'help is-danger';
            message.setAttribute('data-venue-error', '');
            message.textContent = fields[field];
            wrapper.appendChild(message);
        });

        const global = (payload && payload.global) || [];
        const text = global.length > 0 ? global.join(' ') : (Object.keys(fields).length > 0 ? '' : modal.dataset.errorGeneric);
        if (text) {
            globalError.textContent = text;
            globalError.classList.remove('is-hidden');
        }
    }

    select.addEventListener('change', function () {
        if (select.value !== SENTINEL) {
            previousValue = select.value;

            return;
        }

        select.value = previousValue;
        clearErrors();
        openModal();
    });

    modal.querySelectorAll('[data-venue-close]').forEach(function (trigger) {
        trigger.addEventListener('click', closeModal);
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearErrors();

        maFetch(form.action, false, new FormData(form))
            .then(function (payload) {
                const option = document.createElement('option');
                option.value = payload.id;
                option.textContent = payload.name;
                select.insertBefore(option, sentinel);

                select.value = String(payload.id);
                previousValue = select.value;

                form.reset();
                closeModal();
            })
            .catch(function (error) {
                showErrors(error.payload);
            });
    });
});
