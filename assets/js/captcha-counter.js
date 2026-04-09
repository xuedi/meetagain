/**
 * Captcha Counter — Live Refresh Count & Cooldown Timer
 *
 * Polls the captcha count endpoint every second and updates the refresh count
 * and next-free-try countdown displayed on the page. The endpoint URL is read
 * from the data-url attribute on a [data-captcha-counter] root element so
 * this file can be shared between the support contact and password reset forms.
 *
 * Loaded in:  templates/support/index.html.twig (guests only),
 *             templates/security/reset.html.twig
 * Used by:    [data-captcha-counter] root element, #refreshCount, #refreshTime
 * Depends on: none
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
});
