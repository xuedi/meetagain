/**
 * Event Details — Reply Button & Fancybox Init
 *
 * Two behaviours for the event detail page:
 *   1. Reply button — prefills the comment textarea with a quote of the
 *      selected comment when a .replyButton is clicked.
 *   2. Fancybox — binds [data-fancybox] elements if the Fancybox library
 *      has been loaded (only when the user is authenticated and images exist).
 *
 * The Leaflet OSM map init lives in assets/js/osm-map.js so it can be
 * reused by other pages (e.g. admin location edit).
 *
 * Loaded in:  templates/events/details.html.twig (all event detail pages)
 * Used by:    #replyInput, .replyButton, [data-fancybox]
 * Depends on: fancybox.umd.js (Fancybox) if images present
 */

document.addEventListener('DOMContentLoaded', function () {
    // Reply button: prefill comment textarea with quoted comment
    const replyInput = document.getElementById('replyInput');
    if (replyInput) {
        Array.from(document.getElementsByClassName('replyButton')).forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                replyInput.value = '> ' + e.currentTarget.getAttribute('data-comment') + '\n';
            });
        });
    }

    // Fancybox init: bind gallery if library is loaded
    if (typeof Fancybox !== 'undefined') {
        Fancybox.bind('[data-fancybox]', {});
    }
});
