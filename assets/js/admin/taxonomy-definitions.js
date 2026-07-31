/**
 * Taxonomy Definitions -- language-column toggle and delete guard for the item taxonomy editor
 *
 * Enhances the TaxonomyDefinitionsType editor rendered by the _form/taxonomy.html.twig form theme.
 * Within each [data-taxonomy-section] it shows one language's label column at a time behind a
 * button group, and hides the definition collection when the section carries an enable checkbox
 * that is off. Newly added collection rows are re-filtered via a MutationObserver. A row whose
 * definition is still assigned to items asks for confirmation before collection.js removes it -
 * the listener runs in the capture phase so cancelling stops that removal. With JavaScript disabled
 * every column stays visible, so every label remains editable.
 *
 * Loaded in: templates/admin/base.html.twig, templates/item/taxonomy.html.twig
 * Used by:   [data-taxonomy-section], [data-taxonomy-body], .taxonomy-locale-button
 *            [data-taxonomy-locale-target], [data-taxonomy-locale-cell], [data-taxonomy-usage]
 */
(function () {
    function activateLocale(section, locale) {
        section.querySelectorAll('.taxonomy-locale-button').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.taxonomyLocaleTarget === locale);
        });
        section.querySelectorAll('[data-taxonomy-locale-cell]').forEach((cell) => {
            cell.classList.toggle('is-hidden', cell.dataset.taxonomyLocaleCell !== locale);
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
            });
        });

        if (checkbox && body) {
            const applyGate = () => body.classList.toggle('is-hidden', !checkbox.checked);
            applyGate();
            checkbox.addEventListener('change', applyGate);
        }

        const items = section.querySelector('.js-collection-items');
        if (items) {
            const observer = new MutationObserver(() => activateLocale(section, currentLocale));
            observer.observe(items, { childList: true });
        }
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
