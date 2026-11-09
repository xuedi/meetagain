/**
 * Support thread page -- copy the secret thread URL, and report the view
 *
 * The link is the only way back into the thread, so the copy button is the difference
 * between a bookmarked thread and a lost one. Falls back to select-and-execCommand where
 * the async clipboard API is unavailable.
 *
 * The view is posted once after load, so link scanners that only fetch the page are not
 * counted as readers. The page renders no beacon for staff.
 *
 * Loaded in:  templates/support/thread.html.twig
 * Used by:    [data-thread-copy], [data-thread-link], [data-thread-seen-url]
 * Depends on: ma-fetch.js (maFetch)
 */

document.addEventListener('DOMContentLoaded', function () {
    const beacon = document.querySelector('[data-thread-seen-url]');
    if (!beacon) return;

    const body = new FormData();
    body.append('_token', beacon.dataset.threadSeenToken);
    maFetch(beacon.dataset.threadSeenUrl, false, body).catch(function () {});
});

document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-thread-copy]');
    if (!button) return;

    event.preventDefault();

    const sheet = button.closest('[data-thread-link-sheet]');
    const input = sheet ? sheet.querySelector('[data-thread-link]') : null;
    if (!(input instanceof HTMLInputElement)) return;

    const flash = function () {
        const label = button.querySelector('span:not(.icon)');
        const copied = button.dataset.copiedLabel;
        if (!label || !copied) return;

        const original = label.textContent;
        label.textContent = copied;
        button.classList.add('is-success');
        setTimeout(function () {
            label.textContent = original;
            button.classList.remove('is-success');
        }, 1500);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value).then(flash).catch(function () {
            input.select();
            document.execCommand('copy');
            flash();
        });
    } else {
        input.select();
        document.execCommand('copy');
        flash();
    }
});
