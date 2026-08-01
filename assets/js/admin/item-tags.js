/**
 * Item Tags -- language-column toggle, parent-select sync and delete guard for the tag editor
 *
 * Enhances the TagsType editor rendered by the _form/tag.html.twig form theme. It shows one
 * language's label column at a time behind a button group, and feeds each row's parent select from
 * the rows currently in the DOM (never its own), so a row added or renamed in the browser is
 * assignable before the first save; a row still without an id gets a client token that the save
 * turns into a real id. Newly added collection rows are re-filtered via a MutationObserver. A row
 * whose tag is still assigned to items asks for confirmation before collection.js removes it - the
 * listener runs in the capture phase so cancelling stops that removal. With JavaScript disabled
 * every column stays visible, so every label remains editable.
 *
 * Loaded in: templates/item/tags.html.twig
 * Used by:   [data-item-tag-section], [data-item-tag-body], .item-tag-locale-button,
 *            [data-item-tag-locale-target], [data-item-tag-locale-cell], [data-item-tag-usage],
 *            [data-item-tag-rows], [data-item-tag-parent-select]
 */
(function () {
    let tokenCounter = 0;

    function activateLocale(section, locale) {
        section.querySelectorAll('.item-tag-locale-button').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.itemTagLocaleTarget === locale);
        });
        section.querySelectorAll('[data-item-tag-locale-cell]').forEach((cell) => {
            cell.classList.toggle('is-hidden', cell.dataset.itemTagLocaleCell !== locale);
        });
    }

    function rowLabel(row) {
        let label = '';
        row.querySelectorAll('[data-item-tag-locale-cell]').forEach((cell) => {
            const input = cell.querySelector('input');
            const value = input ? input.value.trim() : '';
            if (value === '' || (label !== '' && cell.classList.contains('is-hidden'))) {
                return;
            }
            label = value;
        });

        return label;
    }

    function collectOptions(container) {
        const options = [];
        container.querySelectorAll('.js-collection-item').forEach((row) => {
            const idField = row.querySelector('input[type="hidden"]');
            const label = rowLabel(row);
            if (!idField || label === '') {
                return;
            }
            if (idField.value === '') {
                tokenCounter += 1;
                idField.value = 'n' + tokenCounter;
            }
            options.push({ value: idField.value, label: label, row: row });
        });

        return options;
    }

    function fillSelect(select, options) {
        const selected = select.value;
        while (select.options.length > 1) {
            select.remove(1);
        }
        options.forEach((option) => {
            select.add(new Option(option.label, option.value, false, option.value === selected));
        });
    }

    function syncParentSelects(section) {
        const rows = section.querySelector('[data-item-tag-rows]');
        const selects = section.querySelectorAll('[data-item-tag-parent-select]');
        if (!rows || selects.length === 0) {
            return;
        }

        const options = collectOptions(rows);
        selects.forEach((select) => {
            const own = select.closest('.js-collection-item');
            fillSelect(select, options.filter((option) => option.row !== own));
        });
    }

    function activateSection(section) {
        const buttons = section.querySelectorAll('.item-tag-locale-button');
        if (buttons.length === 0) {
            return;
        }

        let currentLocale = (section.querySelector('.item-tag-locale-button.is-active') || buttons[0])
            .dataset.itemTagLocaleTarget;
        activateLocale(section, currentLocale);

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                currentLocale = button.dataset.itemTagLocaleTarget;
                activateLocale(section, currentLocale);
                syncParentSelects(section);
            });
        });

        section.querySelectorAll('.js-collection-items').forEach((items) => {
            const observer = new MutationObserver(() => {
                activateLocale(section, currentLocale);
                syncParentSelects(section);
            });
            observer.observe(items, { childList: true });
        });

        const rows = section.querySelector('[data-item-tag-rows]');
        if (rows) {
            rows.addEventListener('input', () => syncParentSelects(section));
        }

        syncParentSelects(section);
    }

    function guardRemoval(event) {
        const button = event.target.closest ? event.target.closest('.js-collection-remove') : null;
        if (!button) {
            return;
        }

        const item = button.closest('.js-collection-item');
        const row = item ? item.querySelector('[data-item-tag-usage]') : null;
        if (!row || parseInt(row.dataset.itemTagUsage, 10) < 1) {
            return;
        }

        if (!window.confirm(row.dataset.itemTagUsageMessage)) {
            event.stopPropagation();
            event.preventDefault();
        }
    }

    document.addEventListener('click', guardRemoval, true);

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-item-tag-section]').forEach(activateSection);
    });
}());
