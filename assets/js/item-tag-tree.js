/**
 * Item Tag Tree -- checkbox cascade for a tag vocabulary that nests
 *
 * Enhances the tag checkbox list on an item edit form (the item_tags block of the
 * _form/tag.html.twig form theme) once the vocabulary carries sub-tags: checking a tag checks
 * every tag above it, unchecking one clears everything below it. The server writes the same closure
 * on save, so a submission with JavaScript disabled stores exactly the same set - this only makes
 * the boxes show it before the save.
 *
 * Loaded in:  base.html.twig (all pages; no-ops when no [data-item-tag-tree] is present)
 * Used by:    [data-item-tag-tree] with checkboxes carrying [data-item-tag-parent]
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-item-tag-tree]').forEach((tree) => {
        const boxes = Array.from(tree.querySelectorAll('input[type="checkbox"][data-item-tag-parent]'));
        if (boxes.length === 0) {
            return;
        }

        const byId = new Map(boxes.map((box) => [box.value, box]));
        const parentOf = (box) => byId.get(box.dataset.itemTagParent) || null;
        const childrenOf = (box) => boxes.filter((other) => other.dataset.itemTagParent === box.value);

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
