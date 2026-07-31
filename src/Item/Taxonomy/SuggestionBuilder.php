<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use App\Review\FieldChange;
use App\Service\Config\LanguageService;

final readonly class SuggestionBuilder
{
    public function __construct(
        private LanguageService $languageService,
    ) {}

    /** @return array<int, string> definition id => the label shown to a member in that locale */
    public function rows(Config $taxonomy, Axis $axis, string $locale): array
    {
        $rows = [];
        foreach ($taxonomy->definitions($axis) as $definition) {
            $rows[$definition->id] = $definition->labelFor($locale, $this->languageService->getFilteredDefaultLocale());
        }

        return $rows;
    }

    /** @return list<array{label: ?string, rows: array<int, string>}> */
    public function groups(Config $taxonomy, Axis $axis, string $locale): array
    {
        $sourceLocale = $this->languageService->getFilteredDefaultLocale();

        $groups = [];
        foreach ($taxonomy->groupedDefinitions($axis) as $bucket) {
            $rows = [];
            foreach ($bucket['definitions'] as $definition) {
                $rows[$definition->id] = $definition->labelFor($locale, $sourceLocale);
            }

            $groups[] = ['label' => $bucket['group']?->labelFor($locale, $sourceLocale), 'rows' => $rows];
        }

        return $groups;
    }

    /**
     * @param array<array-key, string> $edited definition id => submitted label
     * @param list<string>             $added
     * @return list<FieldChange>
     */
    public function changes(Config $taxonomy, Axis $axis, string $locale, array $edited, array $added): array
    {
        $changes = [];
        foreach ($this->rows($taxonomy, $axis, $locale) as $id => $label) {
            $submitted = trim($edited[$id] ?? $label);
            if ($submitted === $label) {
                continue;
            }

            $changes[] = $submitted === ''
                ? new FieldChange(new ChangeField($axis, ChangeOperation::Remove, id: $id)->key(), $label, null)
                : new FieldChange(new ChangeField($axis, ChangeOperation::Rename, id: $id, locale: $locale)->key(), $label, $submitted);
        }

        $index = 0;
        foreach ($added as $label) {
            $trimmed = trim($label);
            if ($trimmed === '') {
                continue;
            }

            $changes[] = new FieldChange(new ChangeField($axis, ChangeOperation::Add, locale: $locale, index: $index)->key(), null, $trimmed);
            $index++;
        }

        return $changes;
    }
}
