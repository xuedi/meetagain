
// maFetch: thin fetch wrapper supporting XML (XHR-like) and FormData requests
function maFetch(url, isXml = false, formDataOrMethod = null) {
    let options;

    if (formDataOrMethod instanceof FormData) {
        // FormData provided - use POST with body
        options = { method: 'POST', body: formDataOrMethod };
    } else if (typeof formDataOrMethod === 'string') {
        // HTTP method provided as string (e.g., 'POST', 'GET')
        options = { method: formDataOrMethod.toUpperCase() };
    } else {
        // Default to GET
        options = { method: 'GET' };
    }

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

// Modal: lazy-load content from data-modal-url into global modal (binds at DOMContentLoaded)
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
            maFetch(url, true, 'POST').then(response => {
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

// Ajax unblock: remove user from blocked list
document.addEventListener('DOMContentLoaded', function () {
    (document.querySelectorAll('.ajax-unblock') || []).forEach((link) => {
        link.addEventListener('click', event => {
            event.preventDefault();

            const userName = event.currentTarget.dataset.userName;
            if (!confirm('Unblock ' + userName + '?')) {
                return;
            }

            const url = event.currentTarget.getAttribute('href');
            const userId = event.currentTarget.dataset.userId;

            maFetch(url, true, 'POST').then(() => {
                const row = document.getElementById('block-' + userId);
                if (row) {
                    row.remove();
                }
            });
        });
    });
});

// Ajax block/unblock: handle block forms on messages page
document.addEventListener('DOMContentLoaded', function () {
    (document.querySelectorAll('.ajax-block-form') || []).forEach((form) => {
        form.addEventListener('submit', event => {
            event.preventDefault();

            const url = form.getAttribute('action');
            const isBlockAction = url.includes('/block/');

            if (isBlockAction) {
                if (!confirm('Block this user? They will not be able to see your profile or message you.')) {
                    return;
                }
            }

            maFetch(url, true, 'POST').then(() => {
                // Reload the page to update the conversation state
                window.location.reload();
            });
        });
    });
});

// Tag input: interactive add/remove tags for group profile
document.addEventListener('DOMContentLoaded', function () {
    const tagInput = document.getElementById('tag-input');
    const tagAddBtn = document.getElementById('tag-add-btn');
    const tagList = document.getElementById('tag-list');
    const hiddenField = document.querySelector('textarea[data-tag-field="true"]');

    // Debug: log what we found
    if (!tagInput) console.warn('Tag input: tag-input not found');
    if (!tagAddBtn) console.warn('Tag input: tag-add-btn not found');
    if (!tagList) console.warn('Tag input: tag-list not found');
    if (!hiddenField) console.warn('Tag input: tags hidden field not found');

    if (!tagInput || !tagAddBtn || !tagList || !hiddenField) {
        return;
    }

    console.log('Tag input initialized. Existing tags:', hiddenField.value);

    const tags = new Set();

    // Parse existing tags from hidden field
    if (hiddenField.value) {
        const existingTags = hiddenField.value.split(',').map(t => t.trim()).filter(t => t);
        existingTags.forEach(tag => tags.add(tag));
    }

    function renderTags() {
        tagList.innerHTML = '';

        if (tags.size === 0) {
            tagList.innerHTML = '<span class="has-text-grey-light">No tags yet</span>';
            hiddenField.value = '';
            return;
        }

        tags.forEach(tag => {
            const tagElement = document.createElement('span');
            tagElement.className = 'tag is-medium is-light';

            const tagText = document.createTextNode(tag + ' ');
            tagElement.appendChild(tagText);

            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'delete is-small';
            deleteBtn.setAttribute('data-tag', tag);
            deleteBtn.addEventListener('click', function() {
                tags.delete(this.getAttribute('data-tag'));
                renderTags();
            });

            tagElement.appendChild(deleteBtn);
            tagList.appendChild(tagElement);
        });

        // Update hidden field
        hiddenField.value = Array.from(tags).join(', ');
    }

    function addTag() {
        const newTag = tagInput.value.trim().toLowerCase();
        if (newTag && !tags.has(newTag)) {
            tags.add(newTag);
            tagInput.value = '';
            renderTags();
        } else if (newTag && tags.has(newTag)) {
            // Tag already exists - just clear input
            tagInput.value = '';
        }
    }

    tagAddBtn.addEventListener('click', function(e) {
        e.preventDefault();
        addTag();
    });

    tagInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addTag();
        }
    });

    // Initial render
    renderTags();
});
