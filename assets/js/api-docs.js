/**
 * API Docs — Endpoint Example Toggle
 *
 * Handles click events on the .api-toggle buttons within .api-endpoint
 * elements. Toggles the visibility of the .api-example section and updates
 * the chevron icon and aria-expanded attribute accordingly.
 *
 * Loaded in:  templates/_non_locale/api.html.twig only
 * Used by:    .api-toggle buttons, .api-example sections inside .api-endpoint
 * Depends on: none
 */

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.api-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const example = this.closest('.api-endpoint').querySelector('.api-example');
            const icon = this.querySelector('i');
            const expanded = this.getAttribute('aria-expanded') === 'true';

            if (expanded) {
                example.classList.add('is-hidden');
                icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
                this.setAttribute('aria-expanded', 'false');
            } else {
                example.classList.remove('is-hidden');
                icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
                this.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.querySelectorAll('.api-copy').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const pre = this.closest('.api-example').querySelector('.api-example-pre');
            if (!pre) {
                return;
            }
            const label = this.querySelector('.api-copy-label');
            const original = label ? label.textContent : '';
            navigator.clipboard.writeText(pre.textContent).then(function () {
                if (label) {
                    label.textContent = '✓';
                    setTimeout(function () { label.textContent = original; }, 1500);
                }
            }).catch(function () {
                if (label) {
                    label.textContent = '!';
                    setTimeout(function () { label.textContent = original; }, 1500);
                }
            });
        });
    });
});
