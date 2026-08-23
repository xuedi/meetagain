/**
 * OSM Map — Leaflet init for every [data-osm-map] element.
 *
 * Initialises a Leaflet map on each [data-osm-map] element if the Leaflet library has
 * loaded. Coordinates and marker icon URLs are read from data-* attributes on the map
 * element. Elements injected after load — the event share sheet inside the global modal —
 * are picked up through a MutationObserver; a map built into a container that was hidden
 * a moment earlier keeps a stale size until invalidateSize() runs.
 *
 * Loaded in:  templates/events/details.html.twig, templates/events/share.html.twig,
 *             templates/admin/location/edit.html.twig; injected on demand by cms-map.js
 * Used by:    [data-osm-map]
 * Depends on: leaflet.js (L)
 */

function initOsmMap(mapEl) {
    if (typeof L === 'undefined' || mapEl.dataset.osmMapReady) {
        return;
    }
    mapEl.dataset.osmMapReady = '1';

    mapEl.style.height = mapEl.dataset.osmHeight || '220px';
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
    map.setView(target, Number(mapEl.dataset.zoom) || 16);
    const marker = L.marker(target, {icon: iconMarker}).addTo(map);
    if (mapEl.dataset.markerLabel) {
        marker.bindPopup(mapEl.dataset.markerLabel);
    }

    setTimeout(function () {
        map.invalidateSize();
    }, 0);
}

function initOsmMaps(root) {
    root.querySelectorAll('[data-osm-map]').forEach(initOsmMap);
}

document.addEventListener('DOMContentLoaded', function () {
    initOsmMaps(document);

    const modalContent = document.getElementById('globalImageModalContent');
    if (!modalContent) return;

    new MutationObserver(function () {
        initOsmMaps(modalContent);
    }).observe(modalContent, {childList: true});
});
