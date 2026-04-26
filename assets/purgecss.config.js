const path = require('path');
const root = path.join(__dirname, '..');

module.exports = {
    // Purge the served app CSS in place, AFTER `app:media:compile` has flattened it.
    // The filename is the SHA-256-truncated hash of `meetagain-media-v1|styles/app.scss`
    // (computed by `OpaqueMediaPathResolver::hashLogicalPath()`). It is stable across rebuilds:
    // the hash is over the LOGICAL path, not the file content, so the URL never changes.
    // If the salt in `OpaqueMediaPathResolver::SECRET_SALT` is changed, recompute via:
    //   php -r 'echo substr(hash("sha256", "<salt>|styles/app.scss"), 0, 16);'
    // Other CSS files under public/media/ (lxgw-wenkai font, plugin CSS, vendor CSS like
    // Flatpickr/Fancybox/Leaflet/FA) are intentionally NOT purged - see asset-pipeline.md.
    css:    [path.join(root, 'public/media/6b80199ac21f8ca1.css')],
    output: path.join(root, 'public/media/'),

    // Sources where class names appear. Adding a new templates root, plugin asset directory, or
    // place that emits class names from PHP requires updating this list.
    content: [
        path.join(root, 'templates/**/*.twig'),
        path.join(root, 'plugins/**/templates/**/*.twig'),
        path.join(root, 'assets/js/**/*.js'),
        path.join(root, 'plugins/**/assets/js/**/*.js'),
        path.join(root, 'src/**/*.php'),
        path.join(root, 'plugins/**/src/**/*.php'),
    ],

    // Remove unused @font-face declarations (e.g. unused Inter variants, FA weights)
    fontFace: true,

    // Remove unused @keyframes (e.g. Bulma animations not triggered anywhere)
    keyframes: true,

    // PurgeCSS v6 documented safelist keys: standard, deep, greedy, variables, keyframes.
    // (`patterns` is NOT a documented key; the previous config used it and was silently ignored.)
    safelist: {
        // Selectors to keep verbatim. Regex matches against class names; strings match exactly.
        standard: [
            /^is-/,           // Bulma state/colour classes (is-primary, is-active, is-danger, ...)
            /^has-/,          // Bulma helper classes (has-text-centered, has-background-light, ...)
            /^fa-/,           // Font Awesome icons (also referenced as PHP strings, e.g. EventListItemTag)
            /^fas$/,          // FA shorthand class often used standalone
            /^flatpickr-/,    // Flatpickr injects classes at runtime
            /^fp-/,           // Flatpickr internals
            /^dayContainer$/, // Flatpickr literal class
            /^ts-/,           // Tom Select (kept defensively in case re-added)
            /^leaflet-/,      // Leaflet map lib injects classes at runtime
            /^fancybox/,      // Fancybox v4 (legacy fancybox-* selectors)
            /^f-/,            // Fancybox v5 (f-button, f-carousel, f-spinner, ...)
            /^dt-/,           // JSTable (dt-container, dt-pagination, ...) - injected by JS
            /^chart-/,        // chart.js styled containers (chart-container)
            /^sr-only/,       // Font Awesome accessibility helper (sr-only, sr-only-focusable)
        ],

        // Keep selectors whose ancestor matches - covers nested vendor selectors with descendant combinators.
        greedy: [
            /flatpickr/,
            /fancybox/,
            /leaflet/,
        ],
    },
};
