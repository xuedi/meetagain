/**
 * OSM Map — Leaflet init for the #osm-map element.
 *
 * Initialises a Leaflet map on the first #osm-map element on the page if the
 * Leaflet library has loaded. Coordinates and marker icon URLs are read from
 * data-* attributes on the map element.
 *
 * Loaded in:  templates/events/details.html.twig, templates/admin/location/edit.html.twig
 * Used by:    #osm-map
 * Depends on: leaflet.js (L)
 */

document.addEventListener('DOMContentLoaded', function () {
    const mapEl = document.getElementById('osm-map');
    if (!mapEl || typeof L === 'undefined') {
        return;
    }

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

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    const target = L.latLng(mapEl.dataset.lat, mapEl.dataset.lng);
    map.setView(target, 16);
    L.marker(target, {icon: iconMarker}).addTo(map);
});
