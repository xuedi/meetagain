/**
 * Taxonomy Definitions -- language-column toggle and enable gating for the item taxonomy editor
 *
 * Enhances the TaxonomyConfigType editor rendered by the _form/taxonomy.html.twig form theme.
 * Within each [data-taxonomy-section] it shows one language's label column at a time behind a
 * button group, and hides the definition collection when the section's enable checkbox is off.
 * Newly added collection rows are re-filtered via a MutationObserver. With JavaScript disabled every
 * column and both collections stay visible, so every label remains editable - this only layers the
 * tab/gating behaviour on top.
 *
 * Loaded in: templates/admin/base.html.twig
 * Used by:   [data-taxonomy-section], [data-taxonomy-body], .taxonomy-locale-button
 *            [data-taxonomy-locale-target], [data-taxonomy-locale-cell]
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

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-taxonomy-section]').forEach(activateSection);
    });
}());
