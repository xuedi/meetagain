/**
 * Event Share -- copy link, native share and print for the event share sheet
 *
 * Three progressive enhancements for the share sheet, which is rendered both as a
 * standalone page and injected into the global modal - hence delegated handlers:
 *   1. Copy link ([data-share-copy]) -- copies the sheet's link input and flashes the label.
 *   2. Native share ([data-share-native]) -- revealed only where navigator.share exists.
 *   3. Print ([data-share-print]) -- revealed only when scripting is available.
 *
 * Loaded in:  templates/events/details.html.twig, templates/events/share.html.twig
 * Used by:    [data-share-copy], [data-share-link], [data-share-native], [data-share-print]
 */

function revealShareButtons(root) {
    if (navigator.share) {
        root.querySelectorAll('[data-share-native]').forEach(function (button) {
            button.hidden = false;
        });
    }

    root.querySelectorAll('[data-share-print]').forEach(function (button) {
        button.hidden = false;
    });
}

document.addEventListener('DOMContentLoaded', function () {
    revealShareButtons(document);

    const modalContent = document.getElementById('globalImageModalContent');
    if (!modalContent) return;

    new MutationObserver(function () {
        revealShareButtons(modalContent);
    }).observe(modalContent, {childList: true});
});

document.addEventListener('click', function (event) {
    const copyButton = event.target.closest('[data-share-copy]');
    if (copyButton) {
        event.preventDefault();
        copyShareLink(copyButton);
        return;
    }

    const nativeButton = event.target.closest('[data-share-native]');
    if (nativeButton) {
        event.preventDefault();
        navigator.share({
            title: nativeButton.dataset.shareTitle,
            text: nativeButton.dataset.shareText,
            url: nativeButton.dataset.shareUrl,
        }).catch(function () {
        });
        return;
    }

    const printButton = event.target.closest('[data-share-print]');
    if (printButton) {
        event.preventDefault();
        window.print();
    }
});

function copyShareLink(button) {
    const sheet = button.closest('.event-share-sheet');
    const input = sheet ? sheet.querySelector('[data-share-link]') : null;
    if (!(input instanceof HTMLInputElement)) return;

    const flash = () => {
        const label = button.querySelector('span:not(.icon)');
        const original = label ? label.textContent : null;
        const copied = button.dataset.copiedLabel;
        if (!label || !copied) return;

        label.textContent = copied;
        button.classList.add('is-success');
        setTimeout(function () {
            label.textContent = original;
            button.classList.remove('is-success');
        }, 1500);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value).then(flash).catch(function () {
            input.select();
            document.execCommand('copy');
            flash();
        });
    } else {
        input.select();
        document.execCommand('copy');
        flash();
    }
}
