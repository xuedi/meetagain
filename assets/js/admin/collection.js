/**
 * Generic collection editor -- add/remove rows for any Symfony CollectionType rendered with
 * the Bulma `js-collection` widget (allow_add / allow_delete). Clones the widget's
 * data-prototype on "add", removes a row on its "x", and keeps a monotonic row index so a
 * removed row never collides with a newly added one. No-op on pages without a `.js-collection`,
 * so it is safe to load on every admin page. Without JavaScript, already-saved rows stay
 * editable and savable; only the add button is inert.
 *
 * Loaded in: templates/admin/base.html.twig
 * Used by:   .js-collection, .js-collection-items, .js-collection-item, .js-collection-add,
 *            .js-collection-remove, data-prototype, data-prototype-name, data-allow-delete
 */
(function () {
    function escapeRegExp(value) {
        return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function activate(container) {
        const items = container.querySelector('.js-collection-items');
        const addBtn = container.querySelector('.js-collection-add');
        const prototype = container.getAttribute('data-prototype');
        const prototypeName = container.getAttribute('data-prototype-name') || '__name__';
        const allowDelete = container.getAttribute('data-allow-delete') === '1';
        if (!items) {
            return;
        }

        let counter = items.querySelectorAll('.js-collection-item').length;

        function bindRemove(item) {
            const btn = item.querySelector('.js-collection-remove');
            if (btn) {
                btn.addEventListener('click', () => item.remove());
            }
        }

        items.querySelectorAll('.js-collection-item').forEach(bindRemove);

        if (addBtn && prototype) {
            addBtn.addEventListener('click', () => {
                const html = prototype.replace(new RegExp(escapeRegExp(prototypeName), 'g'), String(counter));
                counter += 1;

                const item = document.createElement('div');
                item.className = 'field has-addons js-collection-item';
                item.innerHTML = '<div class="control is-expanded js-collection-fields">' + html + '</div>'
                    + (allowDelete
                        ? '<div class="control"><button type="button" class="button is-danger is-light js-collection-remove"><span class="icon"><i class="fas fa-trash"></i></span></button></div>'
                        : '');
                items.appendChild(item);
                bindRemove(item);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.js-collection').forEach(activate);
    });
}());
