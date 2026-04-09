/**
 * Navbar — Mobile Burger Menu Toggle
 *
 * Implements the Bulma navbar burger pattern: clicking a .navbar-burger toggles
 * the .is-active class on both the burger icon and the target navbar menu
 * (identified by the burger's data-target attribute).
 *
 * Loaded in:  templates/base.html.twig (all pages)
 * Used by:    templates/base_navbar.html.twig
 */

// Navbar burger: toggle mobile menu (Bulma)
document.addEventListener('DOMContentLoaded', () => {

    // Get all "navbar-burger" elements
    const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);

    // Add a click event on each of them
    $navbarBurgers.forEach(el => {
        el.addEventListener('click', () => {

            // Get the target from the "data-target" attribute
            const target = el.dataset.target;
            const $target = document.getElementById(target);

            // Toggle the "is-active" class on both the "navbar-burger" and the "navbar-menu"
            el.classList.toggle('is-active');
            $target.classList.toggle('is-active');

        });
    });
});
