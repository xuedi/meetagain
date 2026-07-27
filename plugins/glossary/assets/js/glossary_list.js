/**
 * Glossary List - JSTable enhancement for the glossary item list
 *
 * Turns the shared item-list table into a sortable, instantly searchable, client-side paginated
 * table, which a glossary of several hundred phrases needs and the shared component does not
 * provide. Binds to the shared component's own container hook, so it only ever finds a table in
 * 'list' view mode and no-ops everywhere else - it is loaded site-wide with the plugin. A facet or
 * view-mode click re-renders the list body in place, so a MutationObserver rebinds the fresh table.
 *
 * Loaded in:  base.html.twig via Plugin::getJavascripts() (all pages, active plugin only)
 * Used by:    [data-item-list="glossary"] table (plugins/glossary/templates/item/list_body.html.twig)
 * Depends on: js/vendor/jstable.min.js (JSTable), loaded by the glossary index page
 */

function glossaryListBind() {
    const table = document.querySelector('[data-item-list="glossary"] table');
    if (!table || typeof JSTable === 'undefined' || table.dataset.jstable === '1') {
        return;
    }

    table.dataset.jstable = '1';
    new JSTable(table, {
        sortable: true,
        searchable: true,
        perPage: 25,
        perPageSelect: [25, 50, 100, 250, 500, 2500]
    });
}

document.addEventListener('DOMContentLoaded', function () {
    glossaryListBind();

    const region = document.querySelector('[data-item-list-scope="glossary"] [data-item-list-body]');
    if (region) {
        new MutationObserver(glossaryListBind).observe(region, {childList: true});
    }
});
