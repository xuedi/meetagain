/**
 * Human Check -- solves the proof-of-work challenge while the visitor fills the form
 *
 * Reads the signed stamp the server rendered, hands its nonce and the required difficulty
 * to a Worker, and writes the answer into the hidden proof field. The submit button stays
 * disabled until the answer lands, so a form cannot be sent without one, and the spinner in
 * the security fieldset says why the button is inert. Does nothing when the proof-of-work
 * measure is switched off, since the server then renders no difficulty attribute.
 *
 * Loaded in:  templates/security/register.html.twig, templates/security/reset.html.twig,
 *             templates/support/index.html.twig
 * Used by:    [data-form-meta] root, [data-form-meta-stamp], [data-form-meta-proof],
 *             [data-form-meta-status]
 * Depends on: assets/js/human-check-worker.js
 */

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-form-meta]').forEach(function (root) {
        const difficulty = parseInt(root.dataset.formMetaBits || '0', 10);
        const workerUrl = root.dataset.formMetaWorker;
        const stampField = root.querySelector('[data-form-meta-stamp]');
        const proofField = root.querySelector('[data-form-meta-proof]');

        if (!difficulty || !workerUrl || !stampField || !proofField || typeof Worker === 'undefined') {
            return;
        }

        const nonce = readNonce(stampField.value);
        if (!nonce) {
            return;
        }

        const form = root.closest('form');
        // `button.form` honours the HTML form attribute, so the captcha refresh button - which sits
        // inside this form but submits another - is not mistaken for this form's submit button.
        const submit = form
            ? Array.prototype.find.call(form.querySelectorAll('button'), function (candidate) {
                return candidate.form === form && candidate.type === 'submit';
            })
            : null;
        const status = root.querySelector('[data-form-meta-status]');

        // A desktop solves difficulty 18 in well under SHOW_DELAY, so the spinner never appears
        // there; MIN_VISIBLE stops it blinking once a slower device has made it appear.
        const SHOW_DELAY = 200;
        const MIN_VISIBLE = 400;
        let shownAt = 0;

        if (submit) {
            submit.disabled = true;
        }

        const showTimer = window.setTimeout(function () {
            shownAt = Date.now();
            if (status) {
                status.hidden = false;
            }
        }, SHOW_DELAY);

        const worker = new Worker(workerUrl);

        worker.onmessage = function (event) {
            proofField.value = event.data.proof;
            release();
        };

        worker.onerror = release;

        worker.postMessage({ nonce: nonce, difficulty: difficulty });

        function release() {
            worker.terminate();
            window.clearTimeout(showTimer);

            const remaining = shownAt === 0 ? 0 : Math.max(0, MIN_VISIBLE - (Date.now() - shownAt));
            window.setTimeout(function () {
                if (status) {
                    status.hidden = true;
                }
                if (submit) {
                    submit.disabled = false;
                }
            }, remaining);
        }
    });

    function readNonce(stamp) {
        const payload = String(stamp || '').split('.')[0];
        if (!payload) {
            return null;
        }

        try {
            const base64 = payload.replace(/-/g, '+').replace(/_/g, '/');
            const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
            return JSON.parse(atob(padded)).n || null;
        } catch (error) {
            return null;
        }
    }
});
