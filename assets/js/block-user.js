/**
 * Block / Unblock User — Profile & Messaging Block Actions
 *
 * Three related user-blocking interactions used on profile and messaging pages:
 *   1. Ajax yes/no toggle (.triggerToggleBlock) — fires the action URL and updates
 *      the button pair styling (is-success / is-danger) without reloading. Used in
 *      the shared _components/toggle.html.twig component.
 *   2. Ajax unblock (.ajax-unblock) — prompts for confirmation, fires a POST, then
 *      removes the user's table row from the DOM.
 *   3. Ajax block form (.ajax-block-form) — intercepts form submission, prompts for
 *      confirmation on block actions, then reloads to reflect the new state.
 *
 * Loaded in:  templates/base.html.twig (all pages)
 * Used by:    templates/_components/toggle.html.twig,
 *             templates/profile/config.html.twig,
 *             templates/profile/messages/chat_desktop.html.twig,
 *             templates/profile/messages/chat_mobile.html.twig
 * Depends on: ma-fetch.js (maFetch)
 */

// Ajax yes/no toggle: update button styles without page reload
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

// Ajax block form: handle block/unblock forms on messages page
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
                window.location.reload();
            });
        });
    });
});
