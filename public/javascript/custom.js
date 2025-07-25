

// backenders attempt to create a fetch wrapper
function maFetch(url, isXml = false, formData) {
    const options = (isXml) ? {
        method: 'GET',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
    } : (formData instanceof FormData) ? {
        method: 'POST',
        body: formData,
        //headers: {"Content-Type": "application/x-www-form-urlencoded"},
    } : { // non xml & form --> simple get
        method: 'GET',
    };
    return fetch(url, options).then(response => {
        const contentType = response.headers.get("content-type");
        if (contentType && contentType.indexOf("application/json") !== -1) {
            return response.json();
        } else {
            return response.text();
        }
    });
}


// enable closing button for notification boxes
document.addEventListener('DOMContentLoaded', () => {
    (document.querySelectorAll('.notification .delete') || []).forEach(($delete) => {
        const $notification = $delete.parentNode;

        $delete.addEventListener('click', () => {
            $notification.parentNode.removeChild($notification);
        });
    });
});


// burger menu
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

// expandable card
document.addEventListener('DOMContentLoaded', function () {
    let cardToggles = document.getElementsByClassName('card-toggle');
    for (let i = 0; i < cardToggles.length; i++) {
        cardToggles[i].addEventListener('click', e => {
            e.currentTarget.parentElement.parentElement.childNodes[3].classList.toggle('is-hidden');
        });
    }
});

// expandable card
document.addEventListener('DOMContentLoaded', function () {
    (document.querySelectorAll('.flashNotification') || []).forEach((trigger) => {
        setTimeout(function () {
            trigger.style.display = 'none';
        }, 12000);
    });
});

// toggle is-hidden
document.addEventListener('DOMContentLoaded', function () {
    let trigger = document.getElementsByClassName('toggleTrigger');
    for (let i = 0; i < trigger.length; i++) {
        trigger[i].addEventListener('click', event => {
            event.preventDefault();
            let target = event.currentTarget.getAttribute('data-id');
            document.getElementById(target).classList.toggle('is-hidden');
        });
    }
});

// confirm cookie consent jia ajax TODO: use modern foreach
document.addEventListener('DOMContentLoaded', function () {
    let cookieTrigger = document.getElementsByClassName('cookieTrigger');
    for (let i = 0; i < cookieTrigger.length; i++) {
        cookieTrigger[i].addEventListener('click', event => {
            event.preventDefault();

            let osmConsent = document.getElementById('osm_consent_checkbox');
            let url = event.currentTarget.dataset.url + '?osmConsent=' + osmConsent.checked;
            maFetch(url).then(response => {
                location.reload();
            });

        });
    }
});

// modal open and closer, independent of the content
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modalTrigger').forEach((trigger) => {
        trigger.addEventListener('click', event => {
            event.preventDefault();

            const modalSelector = event.currentTarget.dataset.target;
            const modal = document.getElementById(modalSelector);
            const modalClose = modal.querySelector('.modal-close');

            modalClose.addEventListener('click', () => {
                modal.classList.remove('is-active');
            })

            modal.classList.add('is-active');
        });
    });
});


// TODO:
//  - currently it send the whole form
//  - upload only the image and replace it with the returned value (retrigger)
//  - if no javascript, open image upload form like in event images upload
//  - unify image upload process with event and use in both with no JS form fallback
//  - delete this thing and have form send my classic save button

//            maFetch(url, false, formData).then(response => {
//                location.reload();
//            });

document.addEventListener('DOMContentLoaded', function () {
    (document.querySelectorAll('.fileUploadTrigger') || []).forEach((trigger) => {
        trigger.addEventListener('change', event => {
            event.preventDefault();

            let url = event.currentTarget.getAttribute('data-url');

            let formData = new FormData();
            formData.append('image_upload[newImage]', event.currentTarget.files[0]);

            let xhr = new XMLHttpRequest();
            xhr.open("POST", url, true);
            xhr.onreadystatechange = function () {
                if (this.readyState == 4 && this.status == 200) {
                    location.reload();
                }
            }
            xhr.send(formData);
        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    (document.querySelectorAll('.fileSelectTrigger, .fileRotateTrigger') || []).forEach((trigger) => {
        trigger.addEventListener('click', event => {
            event.preventDefault();

            let url = event.currentTarget.getAttribute('href');
            maFetch(url).then(response => {
                location.reload();
            });

        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    (document.querySelectorAll('.showAll') || []).forEach((trigger) => {
        trigger.addEventListener('click', event => {
            event.preventDefault();
            (event.currentTarget.parentNode.parentNode.querySelectorAll('.showAllToggle') || []).forEach((toggle) => {
                toggle.classList.toggle('is-hidden');
            })
        });
    });
});

// triggerToggleBlock: toggle (yes/no) after ajax update parent for event delegation [DONE]
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

// TODO: markers [DONE] only for logic that is kind of final, clean & works well without JS completely