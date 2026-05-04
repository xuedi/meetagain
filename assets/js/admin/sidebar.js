/**
 * Admin shell mobile sidebar drawer.
 *
 * Below 769px the admin sidebar is hidden and reachable via a sticky topbar
 * burger that slides the drawer in from the left. Handles burger toggle,
 * backdrop click, ESC key, and link-click auto-close. Sets aria-expanded on
 * the burger and aria-hidden on the drawer to match state.
 *
 * Loaded in:  templates/admin/base.html.twig (all admin pages)
 * Markup:     #adminBurger, #adminDrawer, #adminBackdrop in templates/admin/base.html.twig
 */

document.addEventListener('DOMContentLoaded', () => {
    const burger = document.getElementById('adminBurger');
    const drawer = document.getElementById('adminDrawer');
    const backdrop = document.getElementById('adminBackdrop');

    if (!burger || !drawer || !backdrop) {
        return;
    }

    const open = () => {
        drawer.classList.add('is-open');
        backdrop.classList.add('is-open');
        burger.classList.add('is-active');
        burger.setAttribute('aria-expanded', 'true');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admin-shell-no-scroll');
        const firstLink = drawer.querySelector('a');
        if (firstLink) {
            firstLink.focus();
        }
    };

    const close = () => {
        drawer.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        burger.classList.remove('is-active');
        burger.setAttribute('aria-expanded', 'false');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('admin-shell-no-scroll');
    };

    burger.addEventListener('click', () => {
        if (drawer.classList.contains('is-open')) {
            close();
            burger.focus();
        } else {
            open();
        }
    });

    backdrop.addEventListener('click', close);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && drawer.classList.contains('is-open')) {
            close();
            burger.focus();
        }
    });

    drawer.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (drawer.classList.contains('is-open')) {
                close();
            }
        });
    });
});
