/**
 * Cookie Consent — Banner Display & Confirmation
 *
 * Manages the cookie consent banner in two steps:
 *   1. On load, checks for the consent_cookies cookie client-side and opens the
 *      dropdown banner if absent. This avoids the issue where the server renders the
 *      banner hidden in cached HTML even when consent has not been given.
 *   2. On confirm (.cookieTrigger), POSTs the consent choice (with OSM map opt-in)
 *      via maFetch and reloads to apply the new consent state.
 *
 * Loaded in:  templates/base.html.twig (all pages)
 * Used by:    templates/base_navbar_cookie.html.twig
 * Depends on: ma-fetch.js (maFetch)
 */

// Cookie consent: open banner client-side if no consent cookie is set (avoids cached HTML issue)
document.addEventListener('DOMContentLoaded', function () {
    const hasConsent = document.cookie.split(';').some(function (c) {
        return c.trim().startsWith('consent_cookies=');
    });
    if (!hasConsent) {
        const dropdown = document.getElementById('cookie-consent-dropdown');
        if (!dropdown) return;
        dropdown.classList.add('is-active');
        const trigger = dropdown.querySelector('.cookie-button');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
        const label = dropdown.querySelector('.cookie-consent-label');
        if (label) label.style.display = '';
    }
});

// Cookie consent: confirm via ajax and reload
document.addEventListener('DOMContentLoaded', function () {
    (document.querySelectorAll('.cookieTrigger') || []).forEach((el) => {
        el.addEventListener('click', (event) => {
            const osmConsent = document.getElementById('osm_consent_checkbox');
            const base = event.currentTarget.dataset.url;
            const url = base + '?osmConsent=' + (osmConsent && osmConsent.checked);
            maFetch(url).then(() => location.reload()).catch(() => location.reload());
        });
    });
});
