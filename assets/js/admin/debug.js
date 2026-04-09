/**
 * Admin Debug — Test Exception Trigger
 *
 * Fires a POST to the debug trigger endpoint when the button is clicked, then
 * shows a confirmation message. The endpoint intentionally throws an exception
 * to verify that Bugsink is capturing errors and sending alert emails correctly.
 *
 * Loaded in:  templates/admin/system/debug/index.html.twig only
 * Used by:    #debugTriggerException button
 * Depends on: ma-fetch.js (maFetch)
 */

// Debug: trigger test exception for error tracker verification
document.addEventListener('DOMContentLoaded', function () {
    const debugTriggerBtn = document.getElementById('debugTriggerException');
    if (debugTriggerBtn) {
        debugTriggerBtn.addEventListener('click', () => {
            debugTriggerBtn.classList.add('is-loading');
            const url = debugTriggerBtn.dataset.url;
            maFetch(url, true, 'POST')
                .catch(() => {
                    // Expected: endpoint throws, Bugsink captures it
                    debugTriggerBtn.classList.remove('is-loading');
                    const result = document.getElementById('debugTriggerResult');
                    result.textContent = 'Exception triggered — check Bugsink for the new issue.';
                    result.classList.remove('is-hidden');
                    result.classList.add('has-text-success');
                });
        });
    }
});
