/**
 * Messages — Auto-Scroll to Latest Message
 *
 * On page load, scrolls both the desktop and mobile message containers to
 * the bottom so the most recent messages are immediately visible. Only loaded
 * when a conversation is active (messages is not null in the template).
 *
 * Loaded in:  templates/profile/messages/index.html.twig (when messages present)
 * Used by:    #desktop-message-container, #mobile-message-container
 * Depends on: none
 */

document.addEventListener('DOMContentLoaded', function () {
    const desktopContainer = document.getElementById('desktop-message-container');
    if (desktopContainer) {
        desktopContainer.scrollTop = desktopContainer.scrollHeight;
    }

    const mobileContainer = document.getElementById('mobile-message-container');
    if (mobileContainer) {
        mobileContainer.scrollTop = mobileContainer.scrollHeight;
    }
});
