/**
 * Event map - a Leaflet map of markers (events or cities) on every [data-event-map] element.
 *
 * Reads its points either from data-url (a GeoJSON FeatureCollection fetched once) or
 * from a <script type="application/json" data-event-map-points> inside the element, so a
 * page that already holds its points needs no second request. A marker click fills the
 * [data-event-map-detail] block of the page; a payload with truncated: true reveals
 * [data-event-map-truncated].
 *
 * Loaded in:  templates/map/index.html.twig, @Citypages/index.html.twig (only with OSM consent)
 * Used by:    [data-event-map]
 * Depends on: leaflet.js (L)
 */

function eventMapDetail(feature) {
    const detail = document.querySelector('[data-event-map-detail]');
    if (!detail) {
        return;
    }
    const props = feature.properties;
    const values = {
        title: props.title,
        start: props.start ? new Date(props.start).toLocaleString(document.documentElement.lang || undefined, {dateStyle: 'medium', timeStyle: 'short'}) : '',
        where: [props.venue, props.city, props.where].filter(Boolean).join(', '),
        summary: props.summary || '',
        rsvpCount: props.rsvpCount === undefined ? '' : String(props.rsvpCount),
    };
    Object.entries(values).forEach(function ([field, value]) {
        const el = detail.querySelector('[data-field="' + field + '"]');
        if (el) {
            el.textContent = value;
        }
    });
    detail.querySelector('[data-field="url"]')?.setAttribute('href', props.url);
    detail.classList.remove('is-hidden');
    document.querySelector('[data-event-map-hint]')?.classList.add('is-hidden');
}

function eventMapDraw(mapEl, map, collection) {
    const icon = L.icon({
        iconUrl: mapEl.dataset.markerIcon,
        shadowUrl: mapEl.dataset.markerShadow,
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        shadowSize: [41, 41]
    });
    const features = collection.features || [];
    if (collection.truncated) {
        document.querySelector('[data-event-map-truncated]')?.classList.remove('is-hidden');
    }
    if (features.length === 0) {
        document.querySelector('[data-event-map-empty]')?.classList.remove('is-hidden');
        document.querySelector('[data-event-map-hint]')?.classList.add('is-hidden');
        map.setView([20, 0], 2);
        return;
    }

    const bounds = [];
    features.forEach(function (feature) {
        const [lng, lat] = feature.geometry.coordinates;
        bounds.push([lat, lng]);
        L.marker([lat, lng], {icon: icon, title: feature.properties.title})
            .addTo(map)
            .on('click', function () {
                eventMapDetail(feature);
            });
    });
    map.fitBounds(bounds, {padding: [30, 30], maxZoom: 14});
}

function initEventMap(mapEl) {
    if (typeof L === 'undefined' || mapEl.dataset.eventMapReady) {
        return;
    }
    mapEl.dataset.eventMapReady = '1';

    const map = L.map(mapEl, {attributionControl: false});
    L.control.attribution().addTo(map).setPrefix('<a href="https://leafletjs.com/">Leaflet</a>');
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    const inline = mapEl.querySelector('script[data-event-map-points]');
    if (inline) {
        eventMapDraw(mapEl, map, JSON.parse(inline.textContent));
        return;
    }

    fetch(mapEl.dataset.url, {headers: {'Accept': 'application/json'}})
        .then(function (response) {
            return response.json();
        })
        .then(function (collection) {
            eventMapDraw(mapEl, map, collection);
        });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-event-map]').forEach(initEventMap);
});
