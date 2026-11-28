/**
 * Cookie Consent — Banner Display & Confirmation
 *
 * The banner body is not part of the page HTML; it is an XHR fragment loaded on demand:
 *   1. On load, checks for the consent_cookies cookie client-side and, if absent, fetches
 *      the fragment and opens the dropdown. The check runs in the browser so cached HTML
 *      never decides whether the banner shows.
 *   2. The trigger is a link to the cookie page; with JavaScript it loads the fragment on
 *      first hover or focus and toggles the dropdown on click. A failed load follows the link.
 *   3. On confirm (.cookieTrigger, delegated so the injected buttons work), POSTs the consent
 *      choice (with the OSM map and external media opt-ins) via maFetch and reloads.
 *
 * Loaded in:  templates/base.html.twig (all pages)
 * Used by:    templates/base_navbar_cookie.html.twig, templates/cookie/_consent_body.html.twig
 * Depends on: ma-fetch.js (maFetch)
 */

// Cookie consent: load the banner body on demand and open it when no consent cookie is set
document.addEventListener('DOMContentLoaded', function () {
    const dropdown = document.getElementById('cookie-consent-dropdown');
    if (!dropdown) return;
    const content = dropdown.querySelector('[data-fragment-url]');
    const trigger = dropdown.querySelector('.cookie-button');
    if (!content || !trigger) return;

    let loading = null;
    const load = function () {
        if (!loading) {
            loading = maFetch(content.dataset.fragmentUrl, true).then(function (html) {
                content.innerHTML = html;
            });
            loading.catch(function () {
                loading = null;
            });
        }
        return loading;
    };

    const setOpen = function (open) {
        dropdown.classList.toggle('is-active', open);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    const hasConsent = document.cookie.split(';').some(function (c) {
        return c.trim().startsWith('consent_cookies=');
    });
    if (!hasConsent) {
        load().then(function () {
            setOpen(true);
        }).catch(function () {});
    }

    trigger.addEventListener('mouseenter', function () {
        load().catch(function () {});
    });
    trigger.addEventListener('focus', function () {
        load().catch(function () {});
    });
    trigger.addEventListener('click', function (event) {
        event.preventDefault();
        load().then(function () {
            setOpen(!dropdown.classList.contains('is-active'));
        }).catch(function () {
            location.href = trigger.href;
        });
    });

    dropdown.addEventListener('click', function (event) {
        const button = event.target.closest('.cookieTrigger');
        if (!button) return;
        const osmConsent = document.getElementById('osm_consent_checkbox');
        const externalMediaConsent = document.getElementById('external_media_consent_checkbox');
        const body = new FormData();
        body.append('osmConsent', String(osmConsent && osmConsent.checked));
        body.append('externalMediaConsent', String(externalMediaConsent && externalMediaConsent.checked));
        body.append('_token', button.dataset.token);
        maFetch(button.dataset.url, false, body).then(() => location.reload()).catch(() => location.reload());
    });
});
