/**
 * Modal & Image Upload — Global Image Modal + File Management
 *
 * Manages the global image modal (#globalImageModal) declared in base.html.twig
 * and all file interactions that appear inside it:
 *   1. Modal open (.modalTrigger / data-modal-url) — lazy-loads HTML from the given
 *      URL via maFetch and injects it into the modal content area.
 *   2. Modal close — dismisses via the .modal-close button or .modal-background click.
 *   3. File upload (.fileUploadTrigger) — sends the selected file via FormData POST
 *      and reloads. Uses event delegation to support dynamically injected modal content.
 *   4. File select/rotate (.fileSelectTrigger / .fileRotateTrigger) — fires the action
 *      URL via GET and reloads. Also delegated for modal content.
 *
 * Loaded in:  templates/base.html.twig (all pages)
 * Used by:    templates/_components/image_upload.html.twig,
 *             templates/image/modal_content.html.twig,
 *             templates/events/details/images.html.twig,
 *             templates/cms/blocks/_edit/Gallery.html.twig,
 *             templates/cms/blocks/_edit/TrioCards.html.twig
 * Depends on: ma-fetch.js (maFetch)
 */

// Modal: lazy-load content from data-modal-url into global modal
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modalTrigger').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            event.preventDefault();

            const url = event.currentTarget.dataset.modalUrl;
            if (!url) return;

            const modal = document.getElementById('globalImageModal');
            const content = document.getElementById('globalImageModalContent');
            if (!modal || !content) return;

            maFetch(url)
                .then(function (html) {
                    content.innerHTML = html;
                    modal.classList.add('is-active');
                })
                .catch(function (err) {
                    console.error('Modal load failed:', err);
                });
        });
    });
});

// Modal: close global modal via .modal-close or .modal-background
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('globalImageModal');
    if (!modal) return;

    const closeModal = () => modal.classList.remove('is-active');

    const closeBtn = modal.querySelector('.modal-close');
    if (closeBtn) closeBtn.addEventListener('click', closeModal);

    const bg = modal.querySelector('.modal-background');
    if (bg) bg.addEventListener('click', closeModal);
});

// File upload: delegated — send selected file via ajax and reload (works on dynamically injected modal content)
document.addEventListener('change', function (event) {
    const trigger = event.target.closest('.fileUploadTrigger');
    if (!trigger) return;

    const url = trigger.getAttribute('data-url');
    const file = trigger.files && trigger.files[0];
    if (!url || !file) return;

    const formData = new FormData();
    formData.append('image_upload[newImage]', file);

    maFetch(url, false, formData)
        .then(() => location.reload())
        .catch(() => location.reload());
});

// File actions: delegated — select/rotate via ajax and reload (works on dynamically injected modal content)
document.addEventListener('click', function (event) {
    const trigger = event.target.closest('.fileSelectTrigger, .fileRotateTrigger');
    if (!trigger) return;
    event.preventDefault();
    const url = trigger.getAttribute('href');
    if (!url) return;
    maFetch(url).then(() => location.reload());
});
