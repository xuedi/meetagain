/**
 * CMS TextMap Editor - coordinate picker for the TextMap block edit form
 *
 * Two independent parts. The location select copies a saved location's coordinates into the
 * latitude/longitude fields and works on its own - it needs no map. On top of that, when OSM
 * is allowed the picker renders a Leaflet map with a draggable marker: clicking the map or
 * dragging the marker writes the two inputs, zooming writes the zoom input, editing an input
 * moves the marker back, and the height select resizes the picker so it previews the height
 * the block will render at.
 *
 * The script self-scopes via getElementById early-return, so it is a no-op on pages without
 * the coordinate fields. Without JavaScript the coordinates remain editable by hand.
 *
 * Loaded in:  templates/admin/cms/cms_block_edit.html.twig
 * Used by:    #text-map-picker, #text-map-lat, #text-map-lng, #text-map-zoom,
 *             #text-map-height, #text-map-location
 * Depends on: leaflet.js (L) - only for the optional picker
 */

(function () {
    const picker = document.getElementById('text-map-picker');
    const latInput = document.getElementById('text-map-lat');
    const lngInput = document.getElementById('text-map-lng');
    const zoomInput = document.getElementById('text-map-zoom');
    const heightSelect = document.getElementById('text-map-height');
    const locationSelect = document.getElementById('text-map-location');
    if (!latInput || !lngInput) {
        return;
    }

    function readNumber(value, fallback) {
        const parsed = parseFloat(value);
        return isNaN(parsed) ? fallback : parsed;
    }

    function selectedPixels() {
        if (!heightSelect) {
            return '320px';
        }
        return heightSelect.options[heightSelect.selectedIndex].dataset.pixels || '320px';
    }

    function writeInputs(latLng) {
        latInput.value = latLng.lat.toFixed(6);
        lngInput.value = latLng.lng.toFixed(6);
    }

    let moveMarker = null;

    if (picker && typeof L !== 'undefined') {
        const start = L.latLng(
            readNumber(latInput.value, readNumber(picker.dataset.defaultLat, 0)),
            readNumber(lngInput.value, readNumber(picker.dataset.defaultLng, 0)),
        );

        picker.style.height = selectedPixels();
        const map = L.map(picker).setView(start, readNumber(zoomInput ? zoomInput.value : '', readNumber(picker.dataset.defaultZoom, 15)));
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        const marker = L.marker(start, {
            draggable: true,
            icon: new L.icon({
                iconUrl: picker.dataset.markerIcon,
                shadowUrl: picker.dataset.markerShadow,
                iconSize: [25, 41],
                shadowSize: [41, 41],
            }),
        }).addTo(map);

        moveMarker = function (latLng, recenter) {
            marker.setLatLng(latLng);
            if (recenter) {
                map.setView(latLng, map.getZoom());
            }
        };

        map.on('click', function (event) {
            moveMarker(event.latlng, false);
            writeInputs(event.latlng);
        });

        marker.on('dragend', function () {
            writeInputs(marker.getLatLng());
        });

        map.on('zoomend', function () {
            if (zoomInput) {
                zoomInput.value = String(map.getZoom());
            }
        });

        [latInput, lngInput].forEach(function (input) {
            input.addEventListener('change', function () {
                moveMarker(L.latLng(readNumber(latInput.value, 0), readNumber(lngInput.value, 0)), true);
            });
        });

        if (zoomInput) {
            zoomInput.addEventListener('change', function () {
                map.setZoom(readNumber(zoomInput.value, 15));
            });
        }

        if (heightSelect) {
            heightSelect.addEventListener('change', function () {
                picker.style.height = selectedPixels();
                map.invalidateSize();
            });
        }

        setTimeout(function () {
            map.invalidateSize();
        }, 0);
    }

    if (locationSelect) {
        locationSelect.addEventListener('change', function () {
            const option = locationSelect.options[locationSelect.selectedIndex];
            if (!option.value) {
                return;
            }
            const lat = readNumber(option.dataset.lat, 0);
            const lng = readNumber(option.dataset.lng, 0);
            writeInputs({lat: lat, lng: lng});
            if (moveMarker) {
                moveMarker(L.latLng(lat, lng), true);
            }
            const markerLabelInput = document.querySelector('input[name="markerLabel"]');
            if (markerLabelInput && markerLabelInput.value === '') {
                markerLabelInput.value = option.dataset.name || '';
            }
        });
    }
})();
