<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

/**
 * One taxonomy row: a stable int id and its per-locale labels. labelFor resolves a display
 * label via a fallback chain - requested locale, source locale, first non-empty label, then '' -
 * so a partially-translated definition still renders something everywhere.
 */
abstract readonly class AbstractTaxonomyDefinition
{
    /** @param array<string, string> $labels locale => label */
    public function __construct(
        public int $id,
        public array $labels,
    ) {}

    public function labelFor(?string $locale, string $sourceLocale): string
    {
        if ($locale !== null && ($this->labels[$locale] ?? '') !== '') {
            return $this->labels[$locale];
        }

        if (($this->labels[$sourceLocale] ?? '') !== '') {
            return $this->labels[$sourceLocale];
        }

        foreach ($this->labels as $label) {
            if ($label !== '') {
                return $label;
            }
        }

        return '';
    }
}
