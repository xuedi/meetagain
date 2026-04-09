/**
 * Admin CMS Block Edit — Image Overlay Dirty State
 *
 * When the CMS block edit form is modified (input or change event), an overlay
 * is shown over the image upload area to remind the user to save before
 * changing the image. The listener removes itself after the first dirty event.
 *
 * Loaded in:  templates/admin/cms/cms_block_edit.html.twig (only when block supports image)
 * Used by:    #cms-block-form, #cms-image-overlay
 * Depends on: none
 */

(function () {
    const form = document.getElementById('cms-block-form');
    const overlay = document.getElementById('cms-image-overlay');
    if (!form || !overlay) return;
    function onDirty() {
        overlay.style.display = 'flex';
        form.removeEventListener('input', onDirty);
        form.removeEventListener('change', onDirty);
    }
    form.addEventListener('input', onDirty);
    form.addEventListener('change', onDirty);
}());
