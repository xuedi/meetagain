/**
 * Notifications — Bulma Notification Dismissal & Flash Auto-Hide
 *
 * Handles two notification patterns used across all pages:
 *   1. Bulma .notification close buttons (.notification .delete) — removes the
 *      notification element from the DOM when clicked.
 *   2. Flash notifications (.flashNotification) — automatically hides Symfony
 *      flash messages after 12 seconds.
 *
 * Loaded in:  templates/base.html.twig (all pages)
 * Used by:    base.html.twig flash blocks, any template rendering a Bulma .notification
 */

// Notifications: enable close button on Bulma .notification
document.addEventListener('DOMContentLoaded', () => {
    (document.querySelectorAll('.notification .delete') || []).forEach(($delete) => {
        const $notification = $delete.parentNode;

        $delete.addEventListener('click', () => {
            $notification.parentNode.removeChild($notification);
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
