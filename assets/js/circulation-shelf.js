/**
 * Circulation Shelf Filter -- client-side text narrowing of the shelf table
 *
 * Reveals the search field on the circulation dashboard's Shelf tab and hides table rows
 * whose text does not contain the typed term. The field ships hidden so a visitor without
 * JavaScript sees the full table and uses the server-rendered status filter instead.
 *
 * Loaded in:  templates/circulation/dashboard.html.twig
 * Used by:    [data-circulation-search], [data-circulation-shelf]
 */
document.addEventListener('DOMContentLoaded', () => {
    const input = document.querySelector('[data-circulation-search]');
    const table = document.querySelector('[data-circulation-shelf]');
    if (!input || !table) {
        return;
    }

    input.hidden = false;
    input.addEventListener('input', () => {
        const term = input.value.trim().toLowerCase();
        table.querySelectorAll('tbody tr').forEach((row) => {
            row.hidden = term !== '' && !row.textContent.toLowerCase().includes(term);
        });
    });
});
