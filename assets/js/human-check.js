/**
 * Human Check -- solves the proof-of-work challenge while the visitor fills the form
 *
 * Reads the signed stamp the server rendered, hands its nonce and the required difficulty
 * to a Worker, and writes the answer into the hidden proof field. The submit button is
 * disabled with a short waiting label until the answer lands, so a form cannot be sent
 * without one. Does nothing when the proof-of-work measure is switched off, since the
 * server then renders no difficulty attribute.
 *
 * Loaded in:  templates/security/register.html.twig, templates/security/reset.html.twig,
 *             templates/support/index.html.twig
 * Used by:    [data-form-meta] root, [data-form-meta-stamp], [data-form-meta-proof]
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
        const submit = form ? form.querySelector('button:not([type="button"])') : null;
        const originalLabel = submit ? submit.textContent : null;

        if (submit) {
            submit.disabled = true;
            if (root.dataset.formMetaLabel) {
                submit.textContent = root.dataset.formMetaLabel;
            }
        }

        const worker = new Worker(workerUrl);

        worker.onmessage = function (event) {
            proofField.value = event.data.proof;
            release();
        };

        worker.onerror = release;

        worker.postMessage({ nonce: nonce, difficulty: difficulty });

        function release() {
            worker.terminate();
            if (!submit) {
                return;
            }
            submit.disabled = false;
            if (originalLabel !== null) {
                submit.textContent = originalLabel;
            }
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
