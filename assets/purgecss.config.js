const path = require('path');
const root = path.join(__dirname, '..');

module.exports = {
    content: [
        path.join(root, 'templates/**/*.twig'),
        path.join(root, 'plugins/**/templates/**/*.twig'),
        path.join(root, 'assets/js/**/*.js'),
    ],
    css: [path.join(root, 'public/assets/media/styles/app-*.css')],
    output: path.join(root, 'public/assets/media/'),

    // Remove unused @font-face declarations (e.g. unused Inter variants, FA weights)
    fontFace: true,

    // Remove unused @keyframes (e.g. Bulma animations not triggered anywhere)
    keyframes: true,

    safelist: {
        // Patterns for class names applied dynamically by JavaScript at runtime.
        // These won't appear as literals in templates, so PurgeCSS would wrongly strip them.
        patterns: [
            /^is-/,         // Bulma state classes toggled by JS (is-active, is-danger, ...)
            /^has-/,        // Bulma helper classes toggled by JS
            /^fa-/,         // Font Awesome icons (referenced as strings in Twig — belt+suspenders)
            /^flatpickr-/,  // Flatpickr injects its own class names at runtime
            /^ts-/,         // Tom Select injects classes at runtime
            /^leaflet-/,    // Leaflet injects classes at runtime
            /^fancybox/,    // Fancybox injects classes at runtime
        ],
    },
};
