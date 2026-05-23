/**
 * post-link — Synthesise a POST submission from a single anchor element.
 *
 * Converts `<a data-post href="...">` clicks into a one-shot `<form method="POST">`
 * submission so action buttons that need CSRF protection can be expressed as a single
 * HTML element instead of a four-element `<form><input _token><button></form>` cluster.
 *
 * Attributes:
 *   - data-post                  Mandatory marker. Anchor is treated as a POST trigger.
 *   - data-csrf-token="..."      Optional. When present and non-empty, appended as a
 *                                hidden `_token` input on the synthesised form. Routes
 *                                that do not validate `_token` server-side may omit it.
 *   - data-confirm="text"        Optional. When present and non-empty, runs
 *                                window.confirm(text); cancel aborts the submission.
 *
 * Click behaviour: preventDefault is unconditional — including ctrl/cmd/middle-click.
 * Opening a POST mutation in a new tab makes no sense, so modifier clicks submit in
 * the same tab as a normal click would.
 *
 * Loaded in:  templates/base.html.twig (all pages)
 * Used by:    templates/member/view.html.twig
 */

document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-post]');
        if (!link) return;
        if (!link.href) return;

        event.preventDefault();

        const confirmText = link.getAttribute('data-confirm');
        if (confirmText && !window.confirm(confirmText)) {
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = link.href;

        const token = link.getAttribute('data-csrf-token');
        if (token) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            input.value = token;
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    });
});
