
// maFetch: thin fetch wrapper supporting XML (XHR-like) and FormData requests
function maFetch(url, isXml = false, formData) {
    const options = (formData instanceof FormData)
        ? { method: 'POST', body: formData }
        : { method: 'GET' };

    if (isXml) {
        options.headers = { 'X-Requested-With': 'XMLHttpRequest' };
    }

    return fetch(url, options).then(async (response) => {
        const contentType = response.headers.get('content-type') || '';
        const isJson = contentType.includes('application/json');
        const payload = isJson ? await response.json() : await response.text();
        if (!response.ok) {
            const err = new Error('Request failed');
            err.response = response;
            err.payload = payload;
            throw err;
        }
        return payload;
    });
}


// Notifications: enable close button on Bulma .notification
document.addEventListener('DOMContentLoaded', () => {
    (document.querySelectorAll('.notification .delete') || []).forEach(($delete) => {
        const $notification = $delete.parentNode;

        $delete.addEventListener('click', () => {
            $notification.parentNode.removeChild($notification);
        });
    });
});


// Navbar burger: toggle mobile menu (Bulma)
document.addEventListener('DOMContentLoaded', () => {

    // Get all "navbar-burger" elements
    const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);

    // Add a click event on each of them
    $navbarBurgers.forEach(el => {
        el.addEventListener('click', () => {

            // Get the target from the "data-target" attribute
            const target = el.dataset.target;
            const $target = document.getElementById(target);

            // Toggle the "is-active" class on both the "navbar-burger" and the "navbar-menu"
            el.classList.toggle('is-active');
            $target.classList.toggle('is-active');

        });
    });
});

// Card toggles: toggle specific card content section
document.addEventListener('DOMContentLoaded', function () {
    Array.from(document.getElementsByClassName('card-toggle')).forEach((el) => {
        el.addEventListener('click', (e) => {
            // Keep original structure assumption to avoid breaking changes
            e.currentTarget.parentElement.parentElement.childNodes[3].classList.toggle('is-hidden');
        });
    });
});

// Flash notifications: auto-hide after a short delay
document.addEventListener('DOMContentLoaded', function () {
    (document.querySelectorAll('.flashNotification') || []).forEach((trigger) => {
        setTimeout(function () {
            trigger.style.display = 'none';
        }, 12000);
    });
});

// Generic toggle: toggle .is-hidden on target id from data-id
document.addEventListener('DOMContentLoaded', function () {
    Array.from(document.getElementsByClassName('toggleTrigger')).forEach((el) => {
        el.addEventListener('click', (event) => {
            event.preventDefault();
            const target = event.currentTarget.getAttribute('data-id');
            const node = document.getElementById(target);
            if (node) node.classList.toggle('is-hidden');
        });
    });
});

// Cookie consent: confirm via ajax and reload (modern forEach)
document.addEventListener('DOMContentLoaded', function () {
    (document.querySelectorAll('.cookieTrigger') || []).forEach((el) => {
        el.addEventListener('click', (event) => {
            event.preventDefault();
            const osmConsent = document.getElementById('osm_consent_checkbox');
            const base = event.currentTarget.dataset.url;
            const url = base + '?osmConsent=' + (osmConsent && osmConsent.checked);
            maFetch(url).then(() => location.reload());
        });
    });
});

// Modal: open by trigger and close via .modal-close
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modalTrigger').forEach((trigger) => {
        trigger.addEventListener('click', event => {
            event.preventDefault();

            const modalSelector = event.currentTarget.dataset.target;
            const modal = document.getElementById(modalSelector);
            if (!modal) return;
            const modalClose = modal.querySelector('.modal-close');

            if (modalClose) {
                modalClose.addEventListener('click', () => {
                    modal.classList.remove('is-active');
                });
            }

            modal.classList.add('is-active');
        });
    });
});

// File upload: send selected file via ajax and reload on success
document.addEventListener('DOMContentLoaded', function () {
    (document.querySelectorAll('.fileUploadTrigger') || []).forEach((trigger) => {
        trigger.addEventListener('change', (event) => {
            event.preventDefault();
            const url = event.currentTarget.getAttribute('data-url');
            const file = event.currentTarget.files && event.currentTarget.files[0];
            if (!url || !file) return;

            const formData = new FormData();
            formData.append('image_upload[newImage]', file);

            maFetch(url, false, formData)
                .then(() => location.reload())
                .catch(() => location.reload());
        });
    });
});

// File actions: select/rotate via ajax and then reload
document.addEventListener('DOMContentLoaded', function () {
    (document.querySelectorAll('.fileSelectTrigger, .fileRotateTrigger') || []).forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            const url = event.currentTarget.getAttribute('href');
            if (!url) return;
            maFetch(url).then(() => location.reload());
        });
    });
});

// Show-all: toggle all siblings with .showAllToggle within container
document.addEventListener('DOMContentLoaded', function () {
    (document.querySelectorAll('.showAll') || []).forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            (event.currentTarget.parentNode.parentNode.querySelectorAll('.showAllToggle') || []).forEach((toggle) => {
                toggle.classList.toggle('is-hidden');
            });
        });
    });
});

// Toggle block: ajax yes/no toggle and update button styles
document.addEventListener('DOMContentLoaded', function () {
    (document.querySelectorAll('.triggerToggleBlock') || []).forEach((trigger) => {
        trigger.addEventListener('click', event => {
            event.preventDefault();

            let url = event.target.getAttribute('href');
            maFetch(url, true).then(response => {
                const toggleBlock = event.target.parentNode;
                const links = toggleBlock.querySelectorAll('a');
                links.forEach(link => {
                    if (link.dataset.type === 'yes') {
                        link.classList.toggle('is-success', response.newStatus);
                    } else {
                        link.classList.toggle('is-danger', !response.newStatus);
                    }
                });
            });
        });
    });
});
