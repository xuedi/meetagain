/**
 * CMS Map - consent-gated Leaflet loader for the TextMap block.
 *
 * A CMS page body is cached and shared across all visitors, so a map block ships as a static
 * fallback box for everyone. This module reads the visitor's own OSM consent cookie and, only
 * when it is granted, injects Leaflet plus osm-map.js and replaces the fallback with a live map.
 * Without JavaScript, or without consent, the fallback box stays and links out to OpenStreetMap.
 *
 * Loaded in:  templates/cms/index.html.twig
 * Used by:    [data-cms-map] in templates/cms/blocks/TextMap.html.twig
 * Depends on: leaflet.js and osm-map.js, both injected at runtime
 */

function cmsMapHasOsmConsent() {
    return document.cookie.split(';').some(function (cookie) {
        return cookie.trim() === 'consent_cookies_osm=granted';
    });
}

function cmsMapLoadScript(src) {
    return new Promise(function (resolve, reject) {
        const existing = document.querySelector('script[data-cms-map-script="' + src + '"]');
        if (existing) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.dataset.cmsMapScript = src;
        script.addEventListener('load', function () {
            resolve();
        });
        script.addEventListener('error', function () {
            reject(new Error('Failed to load ' + src));
        });
        document.head.appendChild(script);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const wrappers = document.querySelectorAll('[data-cms-map]');
    if (wrappers.length === 0 || !cmsMapHasOsmConsent()) {
        return;
    }

    cmsMapLoadScript(wrappers[0].dataset.leafletSrc)
        .then(function () {
            return cmsMapLoadScript(wrappers[0].dataset.osmMapSrc);
        })
        .then(function () {
            wrappers.forEach(function (wrapper) {
                const fallback = wrapper.querySelector('[data-cms-map-fallback]');
                if (fallback) {
                    fallback.remove();
                }
            });
            initOsmMaps(document);
        })
        .catch(function () {
            return;
        });
});
