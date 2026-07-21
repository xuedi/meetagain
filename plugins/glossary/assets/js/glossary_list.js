/**
 * Glossary List - JSTable enhancement for the glossary item list
 *
 * Turns the shared item-list table into a sortable, instantly searchable, client-side paginated
 * table, which a glossary of several hundred phrases needs and the shared component does not
 * provide. Binds to the shared component's own container hook, so it only ever finds a table in
 * 'list' view mode and no-ops everywhere else - it is loaded site-wide with the plugin.
 *
 * Loaded in:  base.html.twig via Plugin::getJavascripts() (all pages, active plugin only)
 * Used by:    [data-item-list="glossary"] table (plugins/glossary/templates/index.html.twig)
 * Depends on: js/vendor/jstable.min.js (JSTable), loaded by the glossary index page
 */

document.addEventListener('DOMContentLoaded', function () {
    const table = document.querySelector('[data-item-list="glossary"] table');
    if (!table || typeof JSTable === 'undefined') {
        return;
    }

    new JSTable(table, {
        sortable: true,
        searchable: true,
        perPage: 25,
        perPageSelect: [25, 50, 100, 250, 500, 2500]
    });
});
