/**
 * Admin Base — JSTable & Flatpickr Initialisation
 *
 * Initialises JSTable on the #filteredTable element (sortable, searchable,
 * paginated) and Flatpickr date/datetime pickers on all .flatpickr-field
 * elements. Date format and locale are read from data-* attributes on this
 * script tag so the template does not need inline JS.
 *
 * Loaded in:  templates/admin/base.html.twig (all admin pages)
 * Used by:    #filteredTable, .flatpickr-field elements
 * Depends on: jstable.min.js (JSTable), flatpickr.min.js (Flatpickr)
 */

document.addEventListener('DOMContentLoaded', function () {
    const scriptEl = document.currentScript
        || document.querySelector('script[src*="admin/base.js"]');
    const dateFormat = scriptEl ? scriptEl.dataset.dateFormat : 'Y-m-d';
    const locale = scriptEl ? scriptEl.dataset.locale : 'en';

    const table = document.getElementById('filteredTable');
    if (table && typeof JSTable !== 'undefined') {
        new JSTable('#filteredTable', {
            sortable: true,
            searchable: true,
            perPage: 15,
            perPageSelect: [5, 10, 15, 25, 50, 100]
        });
    }

    if (typeof flatpickr !== 'undefined') {
        document.querySelectorAll('.flatpickr-field').forEach(function (el) {
            const enableTime = el.dataset.enableTime === 'true';
            flatpickr(el, {
                wrap: true,
                enableTime: enableTime,
                dateFormat: enableTime ? 'Y-m-d H:i' : dateFormat,
                time_24hr: true,
                locale: locale
            });
        });
    }
});
