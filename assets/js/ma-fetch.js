/**
 * maFetch — Thin Fetch Wrapper
 *
 * A lightweight wrapper around the Fetch API that handles FormData (POST with body),
 * plain method strings, and XML (XHR-style) requests. Returns a promise that resolves
 * to parsed JSON or raw text and rejects on non-2xx responses.
 *
 * Loaded in:  templates/base.html.twig (all pages, loaded first)
 * Used by:    cookie-consent.js, modal.js, block-user.js, admin/debug.js,
 *             and inline scripts in admin templates
 */

// maFetch: thin fetch wrapper supporting XML (XHR-like) and FormData requests
function maFetch(url, isXml = false, formDataOrMethod = null) {
    let options;

    if (formDataOrMethod instanceof FormData) {
        // FormData provided - use POST with body
        options = { method: 'POST', body: formDataOrMethod };
    } else if (typeof formDataOrMethod === 'string') {
        // HTTP method provided as string (e.g., 'POST', 'GET')
        options = { method: formDataOrMethod.toUpperCase() };
    } else {
        // Default to GET
        options = { method: 'GET' };
    }

    if (isXml) {
        options.headers = { 'X-Requested-With': 'XMLHttpRequest' };
    }

    return fetch(url, options).then(async (response) => {
        const contentType = response.headers.get('content-type') || '';
        const isJson = contentType.includes('application/json');
        const payload = isJson ? await response.json() : await response.text();
        if (!response.ok) {
            const err = new Error('Request failed');
            err.response = response;
            err.payload = payload;
            throw err;
        }
        return payload;
    });
}
