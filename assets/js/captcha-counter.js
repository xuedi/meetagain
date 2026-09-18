/**
 * Captcha Counter -- Live Refresh Count, Cooldown Timer, and In-Place New Code
 *
 * Polls the captcha count endpoint every second and updates the refresh count and
 * next-free-try countdown displayed on the page. Also takes over the "new code" button so a
 * fresh code is fetched and swapped into the image without leaving the page; without
 * JavaScript that button submits the form in _components/captcha_refresh.html.twig instead,
 * which does the same thing over a redirect and loses whatever the visitor had typed.
 *
 * Loaded in:  templates/security/register.html.twig, templates/security/reset.html.twig,
 *             templates/support/index.html.twig
 * Used by:    [data-captcha-counter] root, [data-captcha-refresh], [data-captcha-image],
 *             #refreshCount, #refreshTime
 * Depends on: ma-fetch.js (maFetch)
 */

document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-captcha-counter]');
    if (!root) return;

    const url = root.dataset.url;
    const refreshCount = document.getElementById('refreshCount');
    const refreshTime = document.getElementById('refreshTime');
    if (!url || !refreshCount || !refreshTime) return;

    setInterval(function () {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.responseType = 'json';
        xhr.onreadystatechange = function () {
            if (this.readyState === 4 && this.status === 200) {
                if (this.response) {
                    refreshCount.textContent = this.response.count ?? '';
                    refreshTime.textContent = this.response.next ?? '';
                }
            }
        };
        xhr.send();
    }, 1000);

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
                refreshCount.textContent = response.count ?? '';
                refreshTime.textContent = response.next ?? '';
            })
            .finally(function () {
                button.classList.remove('is-loading');
            });
    });
});
