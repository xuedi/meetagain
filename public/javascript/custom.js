

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
