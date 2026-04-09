/**
 * Admin Email Template Edit — Language Tab Switcher
 *
 * Handles the language tab buttons in the email template editor. Clicking a
 * translation-button hides all other translation panels and shows the selected
 * one, updating the active state on the button. Works with any number of
 * locale tabs without requiring locale list injection from Twig.
 *
 * Loaded in:  templates/admin/email/templates/edit.html.twig only
 * Used by:    .translation-button elements, .translation-div elements
 * Depends on: none
 */

document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.getElementsByClassName('translation-button');

    Array.from(buttons).forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.preventDefault();

            document.querySelectorAll('.translation-button').forEach(function (b) {
                b.classList.remove('is-active');
            });
            document.querySelectorAll('.translation-div').forEach(function (d) {
                d.classList.add('is-hidden');
            });

            const locale = event.currentTarget.getAttribute('data-id');
            const activeBtn = document.getElementById('translation-button-' + locale);
            const activeDiv = document.getElementById('translation-div-' + locale);
            if (activeBtn) activeBtn.classList.add('is-active');
            if (activeDiv) activeDiv.classList.remove('is-hidden');
        });
    });
});
