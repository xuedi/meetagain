/**
 * Item Taxonomy Tags -- checkbox cascade for a tag vocabulary that nests
 *
 * Enhances the tag checkbox list on an item edit form (the item_taxonomy_tags block of the
 * _form/taxonomy.html.twig form theme) once the vocabulary carries sub-tags: checking a tag checks
 * every tag above it, unchecking one clears everything below it. The server writes the same closure
 * on save, so a submission with JavaScript disabled stores exactly the same set - this only makes
 * the boxes show it before the save.
 *
 * Loaded in:  base.html.twig (all pages; no-ops when no [data-taxonomy-tree] is present)
 * Used by:    [data-taxonomy-tree] with checkboxes carrying [data-taxonomy-parent]
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-taxonomy-tree]').forEach((tree) => {
        const boxes = Array.from(tree.querySelectorAll('input[type="checkbox"][data-taxonomy-parent]'));
        if (boxes.length === 0) {
            return;
        }

        const byId = new Map(boxes.map((box) => [box.value, box]));
        const parentOf = (box) => byId.get(box.dataset.taxonomyParent) || null;
        const childrenOf = (box) => boxes.filter((other) => other.dataset.taxonomyParent === box.value);

        boxes.forEach((box) => box.addEventListener('change', () => {
            if (box.checked) {
                const walked = new Set([box]);
                for (let ancestor = parentOf(box); ancestor && !walked.has(ancestor); ancestor = parentOf(ancestor)) {
                    walked.add(ancestor);
                    ancestor.checked = true;
                }

                return;
            }

            const pending = childrenOf(box);
            while (pending.length > 0) {
                const child = pending.pop();
                if (!child.checked) {
                    continue;
                }
                child.checked = false;
                pending.push(...childrenOf(child));
            }
        }));
    });
});
