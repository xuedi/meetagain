/**
 * RSVP Guest Counter - In-place +1 guest management on the event detail page.
 *
 * Intercepts clicks on the plus/minus buttons in the card-footer strip of the
 * viewer's own RSVP tile, posts the change via maFetch, and updates the +N
 * counter and the minus button's disabled state without a page reload. Both
 * buttons stay in the strip at all times so the tile never reflows; at zero
 * guests the minus is greyed out and inert instead of hidden. The anchors are
 * real data-post links, so the feature works unchanged with JavaScript
 * disabled; on a failed request the click falls back to that same full-page
 * POST submission.
 *
 * Loaded in:  templates/events/details.html.twig
 * Used by:    [data-rsvp-guests] overlay buttons in templates/events/details/rsvp.html.twig
 * Depends on: ma-fetch.js
 */

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-rsvp-guests]');
    if (!button || !button.href) return;

    event.preventDefault();
    event.stopImmediatePropagation();

    if (button.classList.contains('is-disabled')) return;

    const token = button.getAttribute('data-csrf-token');

    try {
        const formData = new FormData();
        if (token) formData.append('_token', token);

        const result = await maFetch(button.href, true, formData);
        if (typeof result.count !== 'number') {
            throw new Error('Unexpected response');
        }
        const count = result.count;

        const card = button.closest('.card');
        const countLabel = card.querySelector('.rsvp-guest-count');
        const removeButton = card.querySelector('[data-rsvp-guests="remove"]');
        countLabel.textContent = '+' + count;
        removeButton.classList.toggle('is-disabled', count === 0);
        if (count === 0) {
            removeButton.setAttribute('aria-disabled', 'true');
            removeButton.setAttribute('tabindex', '-1');
        } else {
            removeButton.removeAttribute('aria-disabled');
            removeButton.removeAttribute('tabindex');
        }
    } catch {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = button.href;
        if (token) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            input.value = token;
            form.appendChild(input);
        }
        document.body.appendChild(form);
        form.submit();
    }
}, true);
