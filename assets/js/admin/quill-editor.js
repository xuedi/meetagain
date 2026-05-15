/**
 * Admin Quill Editor — Rich text on flagged textareas
 *
 * Mounts a Quill (snow theme) editor on every textarea marked with
 * `data-rich="true"`. The textarea is hidden but kept in the DOM so the
 * form continues to submit its value; on every Quill change the
 * sanitized HTML is written back into the textarea, and on submit a final
 * sync runs to catch the last edit.
 *
 * Toolbar mirrors the cms.content html_sanitizer whitelist
 * (config/packages/html_sanitizer.yaml) so the editor cannot produce
 * markup that will be stripped server-side.
 *
 * Loaded in:  templates that include rich-text fields (event edit, etc.)
 * Used by:    textarea[data-rich="true"]
 * Depends on: vendor/quill.min.js (must be loaded first), Quill global
 */

document.addEventListener('DOMContentLoaded', function () {
    if (typeof Quill === 'undefined') {
        return;
    }

    const toolbar = [
        [{ header: [1, 2, 3, 4, 5, 6, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ script: 'sub' }, { script: 'super' }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['blockquote', 'code-block'],
        ['clean'],
    ];

    document.querySelectorAll('textarea[data-rich="true"]').forEach(function (textarea) {
        const wrapper = document.createElement('div');
        wrapper.className = 'quill-editor-wrapper';
        const editor = document.createElement('div');
        editor.className = 'quill-editor';
        editor.innerHTML = textarea.value;
        wrapper.appendChild(editor);
        textarea.parentNode.insertBefore(wrapper, textarea);
        textarea.style.display = 'none';

        const quill = new Quill(editor, {
            theme: 'snow',
            modules: { toolbar: toolbar },
        });

        const sync = function () {
            const html = quill.getSemanticHTML();
            textarea.value = (html === '<p><br></p>' || html === '') ? '' : html;
        };

        quill.on('text-change', sync);

        const form = textarea.closest('form');
        if (form) {
            form.addEventListener('submit', sync);
        }
    });
});
