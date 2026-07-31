/**
 * Not-Found Suspicious Flag -- in-place toggle for the 404 log list
 *
 * Posts the flag action via ajax so the list keeps its scroll position instead of reloading.
 * Suspicion is stored per URL, not per log row, so the response is applied to every row
 * carrying the same [data-suspicious-url].
 *
 * Loaded in: templates/admin/logs/logs_notFound_list.html.twig
 * Used by:   [data-suspicious-table], [data-suspicious-url], [data-suspicious-cell],
 *            [data-suspicious-toggle]
 * Depends on: ma-fetch.js (maFetch)
 */
(function () {
    function applyState(table, url, suspicious) {
        table.querySelectorAll('[data-suspicious-url]').forEach((row) => {
            if (row.dataset.suspiciousUrl !== url) {
                return;
            }

            const cell = row.querySelector('[data-suspicious-cell]');
            if (cell) {
                cell.classList.toggle('has-text-danger', suspicious);
            }

            const flag = row.querySelector('[data-suspicious-toggle]');
            if (flag) {
                flag.classList.toggle('has-text-danger', suspicious);
                flag.classList.toggle('has-text-grey', !suspicious);
                flag.title = suspicious ? flag.dataset.labelUnmark : flag.dataset.labelMark;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const table = document.querySelector('[data-suspicious-table]');
        if (!table) {
            return;
        }

        table.addEventListener('click', (event) => {
            const flag = event.target.closest('a[data-suspicious-toggle]');
            if (!flag) {
                return;
            }

            event.preventDefault();

            if (flag.dataset.busy) {
                return;
            }
            flag.dataset.busy = '1';

            const formData = new FormData();
            formData.append('_token', flag.dataset.csrfToken);

            maFetch(flag.href, true, formData)
                .then((response) => {
                    applyState(table, flag.closest('[data-suspicious-url]').dataset.suspiciousUrl, response.suspicious);
                })
                .catch(() => window.location.reload())
                .finally(() => delete flag.dataset.busy);
        });
    });
})();
