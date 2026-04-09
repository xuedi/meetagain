/**
 * Event Details — Reply Button, Leaflet Map & Fancybox Init
 *
 * Three behaviours for the event detail page:
 *   1. Reply button — prefills the comment textarea with a quote of the
 *      selected comment when a .replyButton is clicked.
 *   2. Leaflet map — initialises the OSM map if the #osm-map element is
 *      present and the Leaflet library has loaded. Coordinates and marker
 *      icon URLs are read from data-* attributes on the map element.
 *   3. Fancybox — binds [data-fancybox] elements if the Fancybox library
 *      has been loaded (only when the user is authenticated and images exist).
 *
 * Loaded in:  templates/events/details.html.twig (all event detail pages)
 * Used by:    #replyInput, .replyButton, #osm-map, [data-fancybox]
 * Depends on: leaflet.js (L) if map present, fancybox.umd.js (Fancybox) if images present
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

    // Leaflet map init: read coordinates and icon URLs from data-* attributes
    const mapEl = document.getElementById('osm-map');
    if (mapEl && typeof L !== 'undefined') {
        mapEl.style.height = '220px';
        const map = L.map(mapEl, {attributionControl: false});
        const attributionControl = L.control.attribution().addTo(map);
        attributionControl.setPrefix('<a href="https://leafletjs.com/">Leaflet</a>');

        const iconMarker = new L.icon({
            iconUrl: mapEl.dataset.markerIcon,
            shadowUrl: mapEl.dataset.markerShadow,
            iconSize: [25, 41],
            shadowSize: [41, 41]
        });

        L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
            attribution: '<a href="http://osm.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        const target = L.latLng(mapEl.dataset.lat, mapEl.dataset.lng);
        map.setView(target, 16);
        L.marker(target, {icon: iconMarker}).addTo(map);
    }

    // Fancybox init: bind gallery if library is loaded
    if (typeof Fancybox !== 'undefined') {
        Fancybox.bind('[data-fancybox]', {});
    }
});
