/**
 * Admin Top - Action Dropdowns (Bulma)
 *
 * Generic click-to-toggle behaviour for any .dropdown[data-admin-dropdown].
 * Closes on outside click and on Escape. Multiple dropdowns are mutually
 * exclusive: opening one closes the others.
 *
 * Loaded in:  templates/admin/base.html.twig (all admin pages)
 * Used by:    AdminTopActionDropdown via _action_dropdown.html.twig
 */

document.addEventListener('DOMContentLoaded', () => {
    const dropdowns = document.querySelectorAll('.dropdown[data-admin-dropdown]');
    if (dropdowns.length === 0) {
        return;
    }

    const closeAll = (except = null) => {
        dropdowns.forEach(d => {
            if (d !== except) {
                d.classList.remove('is-active');
            }
        });
    };

    dropdowns.forEach(dropdown => {
        const trigger = dropdown.querySelector('.dropdown-trigger button');
        if (!trigger) {
            return;
        }
        trigger.addEventListener('click', e => {
            e.stopPropagation();
            const wasActive = dropdown.classList.contains('is-active');
            closeAll();
            if (!wasActive) {
                dropdown.classList.add('is-active');
            }
        });
    });

    document.addEventListener('click', e => {
        const insideDropdown = Array.from(dropdowns).some(d => d.contains(e.target));
        if (!insideDropdown) {
            closeAll();
        }
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeAll();
        }
    });
});
