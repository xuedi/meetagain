<?php declare(strict_types=1);

namespace App\Item\Tag;

use App\Entity\ItemTag;
use App\Review\FieldChange;

final readonly class SuggestionBuilder
{
    public function __construct(
        private TagService $tagService,
    ) {}

    /** @return list<array{id: int, label: string, depth: int, canAddChild: bool}> */
    public function rows(string $itemType, ?string $locale): array
    {
        $rows = [];
        foreach ($this->tagService->getVocabulary($itemType) as $tag) {
            $depth = $tag->getDepth();
            $rows[] = [
                'id' => (int) $tag->getId(),
                'label' => $this->tagService->labelFor($tag, $locale),
                'depth' => $depth,
                'canAddChild' => $depth < ItemTag::MAX_DEPTH,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<array-key, string>             $edited     tag id => submitted label
     * @param  list<string>                         $added      labels proposed as new root tags
     * @param  array<array-key, array<int, string>> $addedBelow tag id => labels proposed below it
     * @return array<int, list<FieldChange>>        target id => the changes proposed against it
     */
    public function changes(string $itemType, string $locale, array $edited, array $added, array $addedBelow): array
    {
        $changes = [];
        foreach ($this->rows($itemType, $locale) as $row) {
            $submitted = trim($edited[$row['id']] ?? $row['label']);
            if ($submitted === $row['label']) {
                continue;
            }

            $changes[$row['id']][] = $submitted === ''
                ? new FieldChange(ChangeTarget::FIELD_REMOVE, $row['label'], null)
                : new FieldChange(ChangeTarget::LABEL_PREFIX . $locale, $row['label'], $submitted);
        }

        $known = array_column($this->rows($itemType, $locale), 'id');
        $byParent = [ChangeTarget::VOCABULARY_TARGET => $added];
        foreach ($addedBelow as $parent => $labels) {
            if (!in_array((int) $parent, $known, true)) {
                continue;
            }

            $byParent[(int) $parent] = $labels;
        }

        foreach ($byParent as $parent => $labels) {
            $index = 0;
            foreach ($labels as $label) {
                $trimmed = trim($label);
                if ($trimmed === '') {
                    continue;
                }

                $field = sprintf('%s%s_%d', ChangeTarget::CHILD_PREFIX, $locale, $index);
                $changes[(int) $parent][] = new FieldChange($field, null, $trimmed);
                $index++;
            }
        }

        return $changes;
    }
}
