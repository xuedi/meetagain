/**
 * Password Toggle -- Show or hide what was typed into a password field
 *
 * Puts an eye icon inside the right edge of every password input that carries
 * data-password-toggle, whose value is the translated label. It is Bulma's icon-in-input
 * pattern: has-icons-right on the surrounding .control and an .icon.is-right made clickable.
 * A click, Enter or Space flips the input between password and text and keeps aria-pressed in
 * step. Without JavaScript the field stays a plain password input.
 *
 * Loaded in:  templates/security/register.html.twig, templates/security/reset_password.html.twig
 * Used by:    input[data-password-toggle]
 * Depends on: Font Awesome icons (fa-eye, fa-eye-slash)
 */

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[data-password-toggle]').forEach(function (input) {
        let control = input.closest('.control');
        if (!control) {
            control = document.createElement('div');
            control.className = 'control';
            input.parentNode.insertBefore(control, input);
            control.appendChild(input);
        }
        control.classList.add('has-icons-right');

        const toggle = document.createElement('span');
        toggle.className = 'icon is-small is-right is-clickable';
        toggle.setAttribute('role', 'button');
        toggle.setAttribute('tabindex', '0');
        toggle.setAttribute('aria-label', input.dataset.passwordToggle);
        toggle.setAttribute('aria-pressed', 'false');

        const icon = document.createElement('i');
        icon.className = 'fas fa-eye';
        toggle.appendChild(icon);

        function flip() {
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            icon.className = reveal ? 'fas fa-eye-slash' : 'fas fa-eye';
        }

        toggle.addEventListener('click', flip);
        toggle.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                flip();
            }
        });

        control.appendChild(toggle);
    });
});
