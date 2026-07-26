<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

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
