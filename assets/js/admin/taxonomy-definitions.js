/**
 * Taxonomy Definitions -- language-column toggle, group-select sync and delete guard for the item
 * taxonomy editor
 *
 * Enhances the TaxonomyDefinitionsType editor rendered by the _form/taxonomy.html.twig form theme.
 * Within each [data-taxonomy-section] it shows one language's label column at a time behind a
 * button group, and hides the definition collection when the section carries an enable checkbox
 * that is off. The group rows of an axis feed the group select of every definition row in the same
 * section, so a group added or renamed in the DOM is assignable before the first save; a row still
 * without an id gets a client token that normalize() turns into a real id. Newly added collection
 * rows are re-filtered via a MutationObserver. A row whose definition is still assigned to items
 * asks for confirmation before collection.js removes it - the listener runs in the capture phase so
 * cancelling stops that removal. With JavaScript disabled every column stays visible, so every
 * label remains editable.
 *
 * Loaded in: templates/admin/base.html.twig, templates/item/taxonomy.html.twig
 * Used by:   [data-taxonomy-section], [data-taxonomy-body], .taxonomy-locale-button
 *            [data-taxonomy-locale-target], [data-taxonomy-locale-cell], [data-taxonomy-usage]
 *            [data-taxonomy-groups], [data-taxonomy-group-select]
 */
(function () {
    let tokenCounter = 0;

    function activateLocale(section, locale) {
        section.querySelectorAll('.taxonomy-locale-button').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.taxonomyLocaleTarget === locale);
        });
        section.querySelectorAll('[data-taxonomy-locale-cell]').forEach((cell) => {
            cell.classList.toggle('is-hidden', cell.dataset.taxonomyLocaleCell !== locale);
        });
    }

    function rowLabel(row) {
        let label = '';
        row.querySelectorAll('[data-taxonomy-locale-cell]').forEach((cell) => {
            const input = cell.querySelector('input');
            const value = input ? input.value.trim() : '';
            if (value === '' || (label !== '' && cell.classList.contains('is-hidden'))) {
                return;
            }
            label = value;
        });

        return label;
    }

    function syncGroupSelects(section) {
        const groups = section.querySelector('[data-taxonomy-groups]');
        const selects = section.querySelectorAll('[data-taxonomy-group-select]');
        if (!groups || selects.length === 0) {
            return;
        }

        const options = [];
        groups.querySelectorAll('.js-collection-item').forEach((row) => {
            const idField = row.querySelector('input[type="hidden"]');
            const label = rowLabel(row);
            if (!idField || label === '') {
                return;
            }
            if (idField.value === '') {
                tokenCounter += 1;
                idField.value = 'n' + tokenCounter;
            }
            options.push({ value: idField.value, label: label });
        });

        selects.forEach((select) => {
            const selected = select.value;
            while (select.options.length > 1) {
                select.remove(1);
            }
            options.forEach((option) => {
                select.add(new Option(option.label, option.value, false, option.value === selected));
            });
        });
    }

    function activateSection(section) {
        const buttons = section.querySelectorAll('.taxonomy-locale-button');
        const body = section.querySelector('[data-taxonomy-body]');
        const checkbox = section.querySelector('input[type="checkbox"]');
        if (buttons.length === 0) {
            return;
        }

        let currentLocale = (section.querySelector('.taxonomy-locale-button.is-active') || buttons[0])
            .dataset.taxonomyLocaleTarget;
        activateLocale(section, currentLocale);

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                currentLocale = button.dataset.taxonomyLocaleTarget;
                activateLocale(section, currentLocale);
                syncGroupSelects(section);
            });
        });

        if (checkbox && body) {
            const applyGate = () => body.classList.toggle('is-hidden', !checkbox.checked);
            applyGate();
            checkbox.addEventListener('change', applyGate);
        }

        section.querySelectorAll('.js-collection-items').forEach((items) => {
            const observer = new MutationObserver(() => {
                activateLocale(section, currentLocale);
                syncGroupSelects(section);
            });
            observer.observe(items, { childList: true });
        });

        const groups = section.querySelector('[data-taxonomy-groups]');
        if (groups) {
            groups.addEventListener('input', () => syncGroupSelects(section));
        }
        syncGroupSelects(section);
    }

    function guardRemoval(event) {
        const button = event.target.closest ? event.target.closest('.js-collection-remove') : null;
        if (!button) {
            return;
        }

        const item = button.closest('.js-collection-item');
        const row = item ? item.querySelector('[data-taxonomy-usage]') : null;
        if (!row || parseInt(row.dataset.taxonomyUsage, 10) < 1) {
            return;
        }

        if (!window.confirm(row.dataset.taxonomyUsageMessage)) {
            event.stopPropagation();
            event.preventDefault();
        }
    }

    document.addEventListener('click', guardRemoval, true);

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-taxonomy-section]').forEach(activateSection);
    });
}());
