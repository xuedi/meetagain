/**
 * Admin Permissions — Accordion Section Toggle
 *
 * Handles click events on permission section headers to collapse and expand
 * the corresponding permission table. Uses the is-hidden Bulma class to
 * show/hide sections and toggles aria-expanded for accessibility.
 *
 * Loaded in:  templates/admin/system/permissions/index.html.twig only
 * Used by:    .perm-section-header elements with data-target pointing to .perm-section-body
 * Depends on: none
 */

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.perm-section-header').forEach(function (header) {
        header.addEventListener('click', function () {
            const body = document.getElementById(this.dataset.target);
            const btn = this.querySelector('.perm-section-toggle');
            const icon = btn.querySelector('i');
            const expanded = btn.getAttribute('aria-expanded') === 'true';

            if (expanded) {
                body.classList.add('is-hidden');
                icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
                btn.setAttribute('aria-expanded', 'false');
                this.classList.remove('is-open');
            } else {
                body.classList.remove('is-hidden');
                icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
                btn.setAttribute('aria-expanded', 'true');
                this.classList.add('is-open');
            }
        });
    });
});
