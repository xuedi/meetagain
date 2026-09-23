/**
 * Captcha Counter -- Live Refresh Count, Cooldown Timer, and In-Place New Code
 *
 * Keeps the refresh count and next-free-try countdown current without asking the server: it
 * counts down the expiry offsets rendered into data-expiries, and restarts from the offsets
 * every new code returns. Also takes over the "new code" button so a fresh code is fetched
 * and swapped into the image without leaving the page; without JavaScript that button
 * submits the form in _components/captcha_refresh.html.twig instead, which does the same
 * thing over a redirect and loses whatever the visitor had typed.
 *
 * Loaded in:  templates/security/register.html.twig, templates/security/reset.html.twig,
 *             templates/security/login.html.twig, templates/support/index.html.twig
 * Used by:    [data-captcha-counter] root, [data-captcha-refresh], [data-captcha-image],
 *             #refreshCount, #refreshTime
 * Depends on: ma-fetch.js (maFetch)
 */

document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-captcha-counter]');
    if (!root) return;

    const refreshCount = document.getElementById('refreshCount');
    const refreshTime = document.getElementById('refreshTime');
    if (!refreshCount || !refreshTime) return;

    let expiresAt = [];
    let timer = null;

    function render() {
        const now = Date.now();
        expiresAt = expiresAt.filter(function (at) { return at > now; });
        refreshCount.textContent = expiresAt.length;
        refreshTime.textContent = expiresAt.length > 0 ? Math.ceil((expiresAt[0] - now) / 1000) : 0;
        if (expiresAt.length === 0 && timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    }

    function start(offsets) {
        const now = Date.now();
        expiresAt = (offsets || []).map(function (seconds) { return now + seconds * 1000; }).sort(function (a, b) { return a - b; });
        render();
        if (expiresAt.length > 0 && timer === null) {
            timer = setInterval(render, 1000);
        }
    }

    start(JSON.parse(root.dataset.expiries || '[]'));

    const button = root.querySelector('[data-captcha-refresh]');
    const image = root.querySelector('[data-captcha-image]');
    const refreshUrl = root.dataset.refreshUrl;
    const form = document.getElementById('captchaRefresh');
    if (!button || !image || !refreshUrl || !form) return;

    button.addEventListener('click', function (event) {
        event.preventDefault();
        button.classList.add('is-loading');

        const body = new FormData();
        body.append('_token', form.querySelector('[name="_token"]').value);

        maFetch(refreshUrl, true, body)
            .then(function (response) {
                image.src = 'data:image/png;base64, ' + response.image;
                start(response.expiries);
            })
            .finally(function () {
                button.classList.remove('is-loading');
            });
    });
});
