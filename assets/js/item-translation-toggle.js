/**
 * Item Translation Toggle -- Language tabs for per-language item content fields
 *
 * Enhances the shared item translation editor (templates/_components/item/translation_fields.html.twig):
 * shows one language's field panel at a time behind a button group. With JavaScript disabled every
 * panel stays visible, so all languages remain editable - this only layers the tab behaviour on top.
 *
 * Loaded in:  base.html.twig (all pages; no-ops when no [data-translation-group] is present)
 * Used by:    [data-translation-group] with .translation-toggle-button[data-translation-target]
 *             and .translation-panel[data-translation-panel]
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-translation-group]').forEach((group) => {
        const buttons = group.querySelectorAll('.translation-toggle-button');
        const panels = group.querySelectorAll('.translation-panel');
        if (buttons.length === 0 || panels.length === 0) {
            return;
        }

        const activate = (locale) => {
            buttons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.translationTarget === locale);
            });
            panels.forEach((panel) => {
                panel.classList.toggle('is-hidden', panel.dataset.translationPanel !== locale);
            });
        };

        buttons.forEach((button) => {
            button.addEventListener('click', () => activate(button.dataset.translationTarget));
        });

        const initial = group.querySelector('.translation-toggle-button.is-active') || buttons[0];
        activate(initial.dataset.translationTarget);
    });
});
