/**
 * Admin Collapsible Section - Click-to-toggle (Bulma)
 *
 * Generic accordion behaviour for any element with `.admin-section-header`.
 * Clicking the header toggles visibility of the body identified by the
 * header's `data-target` (referencing the body element id). Updates
 * aria-expanded and swaps the chevron icon. Adds/removes `is-open` on the
 * header so adjacent-sibling SCSS rules can render the bordered body.
 *
 * Loaded in:  templates/admin/base.html.twig (all admin pages)
 * Used by:    .admin-section-header rendered by AdminCollapsibleSection
 */

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.admin-section-header').forEach(header => {
        header.addEventListener('click', () => {
            const body = document.getElementById(header.dataset.target);
            if (!body) {
                return;
            }
            const btn = header.querySelector('.admin-section-toggle');
            const icon = btn ? btn.querySelector('i') : null;
            const expanded = btn?.getAttribute('aria-expanded') === 'true';

            if (expanded) {
                body.classList.add('is-hidden');
                if (icon) {
                    icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
                }
                if (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                }
                header.classList.remove('is-open');
            } else {
                body.classList.remove('is-hidden');
                if (icon) {
                    icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
                }
                if (btn) {
                    btn.setAttribute('aria-expanded', 'true');
                }
                header.classList.add('is-open');
            }
        });
    });
});
