/**
 * Dish Gallery - Fancybox binding for the dishes 'gallery' view mode
 *
 * Binds the lightbox to the gallery thumbnails of the dishes list. The binding lives here rather
 * than in the view template because the list body is re-rendered in place when a facet or the view
 * mode changes, and injected markup never runs its own scripts - a MutationObserver on the swapped
 * region rebinds instead.
 *
 * Loaded in:  base.html.twig via Plugin::getJavascripts() (all pages, active plugin only)
 * Used by:    [data-fancybox="dish-gallery"] (plugins/dishes/templates/views/gallery.html.twig)
 * Depends on: js/vendor/fancybox.umd.js (Fancybox), loaded by the dishes list page
 */

function dishGalleryBind() {
    if (typeof Fancybox === 'undefined' || !document.querySelector('[data-fancybox="dish-gallery"]')) {
        return;
    }

    Fancybox.bind('[data-fancybox="dish-gallery"]', {compact: true});
}

document.addEventListener('DOMContentLoaded', function () {
    dishGalleryBind();

    const region = document.querySelector('[data-item-list-scope="dish"] [data-item-list-body]');
    if (region) {
        new MutationObserver(dishGalleryBind).observe(region, {childList: true});
    }
});
