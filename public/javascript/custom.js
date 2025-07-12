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
        }, 1700);
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

// confirm cookie consent jia ajax
document.addEventListener('DOMContentLoaded', function () {
    let cookieTrigger = document.getElementsByClassName('cookieTrigger');
    for (let i = 0; i < cookieTrigger.length; i++) {
        cookieTrigger[i].addEventListener('click', event => {
            event.preventDefault();

            let osmConsent = document.getElementById('osm_consent_checkbox');
            let url = event.currentTarget.dataset.url + '?osmConsent=' + osmConsent.checked;
            console.log(url);

            let xhr = new XMLHttpRequest();
            xhr.open("GET", url, true);
            xhr.onreadystatechange = function () {
                if (this.readyState == 4 && this.status == 200) {
                    location.reload();
                }
            }
            xhr.send();
        });
    }
});

// modals TODO use this clean style(?) of javascript for everything else
document.addEventListener('DOMContentLoaded', () => {
    function openModal($el) {
        $el.classList.add('is-active');
    }

    function closeModal($el) {
        $el.classList.remove('is-active');
    }

    function closeAllModals() {
        (document.querySelectorAll('.modal') || []).forEach(($modal) => {
            closeModal($modal);
        });
    }

    // Add a click event on buttons to open a specific modal
    (document.querySelectorAll('.modalTrigger') || []).forEach(($trigger) => {
        const modal = $trigger.dataset.target;
        const $target = document.getElementById(modal);

        $trigger.addEventListener('click', () => {
            openModal($target);
        });
    });

    // Add a click event on various child elements to close the parent modal
    (document.querySelectorAll('.modal-background, .modal-close, .modal-card-head .delete, .modal-card-foot .button') || []).forEach(($close) => {
        const $target = $close.closest('.modal');

        $close.addEventListener('click', () => {
            closeModal($target);
        });
    });

    // Add a keyboard event to close all modals
    document.addEventListener('keydown', (event) => {
        if (event.key === "Escape") {
            closeAllModals();
        }
    });
});

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

            let xhr = new XMLHttpRequest();
            xhr.open("GET", url, true);
            xhr.onreadystatechange = function () {
                if (this.readyState == 4 && this.status == 200) {
                    location.reload();
                }
            }
            xhr.send();
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

// attach all to body, so changed DOM will bubble up and match again
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('body').addEventListener('click', event => {
        event.preventDefault();

        if (event.target && event.target.matches("a.ajaxToggle")) {
            let url = event.target.getAttribute('href');
            let xhr = new XMLHttpRequest();
            xhr.open("GET", url, true);
            xhr.send();
            xhr.onload = function () {
                if (xhr.status === 200) {
                    event.target.parentNode.outerHTML = xhr.responseText;
                } else {
                    location.reload();
                }
            };
        }

    });
});
