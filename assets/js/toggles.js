/**
 * Toggles — Card Section & Generic Element Visibility Toggles
 *
 * Two toggle patterns used across public and admin pages:
 *   1. Card section toggle (.card-toggle) — collapses/expands the fourth child node
 *      of the card header's parent (Bulma card structure). Used on profile pages.
 *   2. Generic toggle (.toggleTrigger / data-id) — toggles .is-hidden on any element
 *      by ID. Used in warning boxes, CMS editor, admin event/location/host/member edit,
 *      and the cookie consent navbar component.
 *
 * Loaded in:  templates/base.html.twig (all pages)
 * Used by:    templates/_components/warning_box.html.twig,
 *             templates/profile/index.html.twig,
 *             templates/profile/_partials/social_list.html.twig,
 *             templates/admin/event/edit.html.twig,
 *             templates/admin/location/edit.html.twig,
 *             templates/admin/host/edit.html.twig,
 *             templates/admin/member/edit.html.twig,
 *             templates/admin/cms/cms_list.html.twig,
 *             templates/admin/cms/cms_edit.html.twig,
 *             templates/admin/cms/newBlocks.html.twig
 */

// Card toggles: toggle specific card content section
document.addEventListener('DOMContentLoaded', function () {
    Array.from(document.getElementsByClassName('card-toggle')).forEach((el) => {
        el.addEventListener('click', (e) => {
            // Keep original structure assumption to avoid breaking changes
            e.currentTarget.parentElement.parentElement.childNodes[3].classList.toggle('is-hidden');
        });
    });
});

// Generic toggle: toggle .is-hidden on target id from data-id
document.addEventListener('DOMContentLoaded', function () {
    Array.from(document.getElementsByClassName('toggleTrigger')).forEach((el) => {
        el.addEventListener('click', (event) => {
            event.preventDefault();
            const target = event.currentTarget.getAttribute('data-id');
            const node = document.getElementById(target);
            if (node) node.classList.toggle('is-hidden');
        });
    });
});

// Show-more toggle: reveals hidden rows in the nearest table body and hides the trigger row
document.addEventListener('DOMContentLoaded', function () {
    Array.from(document.querySelectorAll('[data-show-more]')).forEach((el) => {
        el.addEventListener('click', (event) => {
            event.preventDefault();
            const triggerRow = event.currentTarget.closest('tr');
            const container = triggerRow ? triggerRow.parentElement : null;
            if (container) {
                container.querySelectorAll('.is-hidden').forEach((hidden) => {
                    hidden.classList.remove('is-hidden');
                });
            }
            if (triggerRow) triggerRow.classList.add('is-hidden');
        });
    });
});
