/**
 * Admin Quill Editor — Rich text on flagged textareas
 *
 * Mounts a Quill (snow theme) editor on every textarea marked with
 * `data-rich="true"`. The original textarea is hidden but kept in the DOM
 * so the form continues to submit its value; on every change in either
 * mode the active source is written back into it.
 *
 * Toolbar mirrors the cms.content html_sanitizer whitelist
 * (config/packages/html_sanitizer.yaml) so the editor cannot produce
 * markup that will be stripped server-side.
 *
 * A "Source" toggle button switches between the visual editor and a raw
 * HTML textarea. Round-trip caveat: when toggling back to visual, Quill
 * re-parses the HTML through its Delta model — markup outside Quill's
 * known formats (e.g. <table>) gets dropped even if cms.content would
 * allow it.
 *
 * Loaded in:  templates that include rich-text fields (event edit, etc.)
 * Used by:    textarea[data-rich="true"]
 * Depends on: vendor/quill.min.js (must be loaded first), Quill global
 */

document.addEventListener('DOMContentLoaded', function () {
    if (typeof Quill === 'undefined') {
        return;
    }

    // Root fix for Quill's "every space becomes &nbsp;" behaviour in getSemanticHTML().
    // The serializer calls blot.html() if present, otherwise falls back to the nbsp
    // replacement. Defining html() on the Text blot prototype takes the clean path.
    const TextBlot = Quill.import('blots/text');
    if (!TextBlot.prototype.html) {
        const escapeHtml = function (s) {
            return s
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        };
        TextBlot.prototype.html = function (index, length) {
            return escapeHtml(this.value().slice(index, index + length));
        };
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

        const sourceArea = document.createElement('textarea');
        sourceArea.className = 'quill-source-area textarea';
        sourceArea.style.display = 'none';
        sourceArea.value = textarea.value;
        wrapper.appendChild(sourceArea);

        const toggleBar = document.createElement('div');
        toggleBar.className = 'quill-source-toggle';
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'button is-small';
        toggleBtn.innerHTML = '<span class="icon is-small"><i class="fa fa-code"></i></span><span>Source</span>';
        toggleBar.appendChild(toggleBtn);
        wrapper.appendChild(toggleBar);

        textarea.parentNode.insertBefore(wrapper, textarea);
        textarea.style.display = 'none';

        const quill = new Quill(editor, {
            theme: 'snow',
            modules: { toolbar: toolbar },
        });

        // Paste from Word/Outlook brings in U+00A0 (non-breaking space) text nodes.
        // Normalise them to regular spaces at the moment they enter the editor,
        // so the Delta model never holds nbsp and serialization stays clean.
        quill.clipboard.addMatcher(Node.TEXT_NODE, function (node, delta) {
            delta.ops.forEach(function (op) {
                if (typeof op.insert === 'string') {
                    op.insert = op.insert.replace(/ /g, ' ');
                }
            });
            return delta;
        });

        let mode = 'wysiwyg';

        // Block-level tags Quill can emit (subset of cms.content whitelist).
        // Used to insert newlines BEFORE these tags for source-view readability,
        // and to detect inter-tag whitespace to strip on submit.
        const BLOCK_TAG_RE = /<(p|ul|ol|li|h[1-6]|blockquote|pre)(\s|>)/gi;

        const prettyPrintForSource = function (html) {
            return html.replace(BLOCK_TAG_RE, '\n$&').replace(/^\n+/, '');
        };

        const compactForStorage = function (html) {
            return html.replace(/>\s*\n+\s*</g, '><').trim();
        };

        const sync = function () {
            const raw = mode === 'source' ? compactForStorage(sourceArea.value) : quill.getSemanticHTML();
            textarea.value = (raw === '<p><br></p>' || raw === '') ? '' : raw;
        };

        toggleBtn.addEventListener('click', function () {
            if (mode === 'wysiwyg') {
                sourceArea.value = prettyPrintForSource(quill.getSemanticHTML());
                editor.style.display = 'none';
                wrapper.querySelector('.ql-toolbar').style.display = 'none';
                sourceArea.style.display = '';
                toggleBtn.querySelector('span:last-child').textContent = 'Visual';
                toggleBtn.querySelector('.fa').className = 'fa fa-eye';
                mode = 'source';
            } else {
                quill.clipboard.dangerouslyPasteHTML(sourceArea.value);
                sourceArea.style.display = 'none';
                editor.style.display = '';
                wrapper.querySelector('.ql-toolbar').style.display = '';
                toggleBtn.querySelector('span:last-child').textContent = 'Source';
                toggleBtn.querySelector('.fa').className = 'fa fa-code';
                mode = 'wysiwyg';
            }
            sync();
        });

        quill.on('text-change', sync);
        sourceArea.addEventListener('input', sync);

        const form = textarea.closest('form');
        if (form) {
            form.addEventListener('submit', sync);
        }
    });
});
