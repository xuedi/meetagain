/**
 * Item Gallery -- stage, prev/next and lightbox for the shared item-list 'gallery' mode
 *
 * Populates the gallery stage of _components/item/gallery.html.twig from the lightbox-bound
 * thumbnail slides, cycles the active slide via the prev/next buttons and forwards a click on
 * the stage image to the active slide's Fancybox anchor. The stage is server-rendered hidden,
 * so without JavaScript the thumbnails remain plain links to the large image. The list body is
 * re-rendered in place on facet and view-mode changes, so a MutationObserver on the swapped
 * region re-initialises the stage.
 *
 * Loaded in:  templates/base.html.twig (all pages)
 * Used by:    [data-item-gallery] and its data-item-gallery-* hooks
 *             (_components/item/gallery.html.twig, _components/item/styles/slide.html.twig)
 * Depends on: js/vendor/fancybox.umd.js (Fancybox), loaded by the item-list page
 */

function itemGallerySlides(gallery) {
    return Array.from(gallery.querySelectorAll('[data-item-gallery-slide]'));
}

function itemGallerySetActive(gallery, index) {
    const slides = itemGallerySlides(gallery);
    if (!slides.length) {
        return;
    }

    const active = ((index % slides.length) + slides.length) % slides.length;
    gallery.dataset.itemGalleryIndex = String(active);
    const slide = slides[active];
    slides.forEach((s, i) => s.classList.toggle('is-active', i === active));

    const image = gallery.querySelector('[data-item-gallery-image]');
    const thumbImage = slide.querySelector('img');
    image.src = slide.href;
    image.alt = thumbImage ? thumbImage.alt : '';
    gallery.querySelector('[data-item-gallery-open]').href = slide.href;

    const label = gallery.querySelector('[data-item-gallery-label]');
    label.textContent = '';
    const link = document.createElement('a');
    link.href = slide.dataset.itemGalleryUrl;
    link.textContent = slide.dataset.itemGalleryTitle;
    label.appendChild(link);
    if (slide.dataset.itemGallerySubtitle) {
        const subtitle = document.createElement('span');
        subtitle.className = 'has-text-grey';
        subtitle.textContent = ' · ' + slide.dataset.itemGallerySubtitle;
        label.appendChild(subtitle);
    }
    label.classList.remove('is-hidden');

    gallery.querySelector('[data-item-gallery-stage]').classList.remove('is-hidden');
}

function itemGalleryInitAll() {
    document.querySelectorAll('[data-item-gallery]').forEach((gallery) => itemGallerySetActive(gallery, 0));
}

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const gallery = event.target.closest('[data-item-gallery]');
    if (!gallery) {
        return;
    }

    const current = parseInt(gallery.dataset.itemGalleryIndex || '0', 10);
    if (event.target.closest('[data-item-gallery-prev]')) {
        itemGallerySetActive(gallery, current - 1);
        return;
    }
    if (event.target.closest('[data-item-gallery-next]')) {
        itemGallerySetActive(gallery, current + 1);
        return;
    }

    if (event.target.closest('[data-item-gallery-open]') && typeof Fancybox !== 'undefined') {
        event.preventDefault();
        const slide = itemGallerySlides(gallery)[current];
        if (slide) {
            slide.click();
        }
    }
});

document.addEventListener('DOMContentLoaded', () => {
    if (typeof Fancybox !== 'undefined') {
        Fancybox.bind('[data-item-gallery] [data-fancybox]', {});
    }

    itemGalleryInitAll();

    document.querySelectorAll('[data-item-list-body]').forEach((region) => {
        new MutationObserver(itemGalleryInitAll).observe(region, {childList: true});
    });
});
