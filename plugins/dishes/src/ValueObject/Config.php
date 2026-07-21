<?php declare(strict_types=1);

namespace Plugin\Dishes\ValueObject;

use App\Item\Taxonomy\TaxonomyConfig;
use App\Publisher\PluginSettings\PluginSettingsData;

/**
 * Effective dishes settings: an optional footer text shown at the bottom of the dish list
 * (held per locale), whether the phonetic column is shown in the list, and the shared category/tag
 * taxonomy. The neutral default is no footer, no phonetic column, and taxonomy disabled.
 */
final class Config implements PluginSettingsData
{
    /** @var array<string, string> locale => footer text */
    private array $footerText = [];

    private bool $phoneticInList = false;

    private TaxonomyConfig $taxonomy;

    public function __construct()
    {
        $this->taxonomy = new TaxonomyConfig();
    }

    public function getTaxonomy(): TaxonomyConfig
    {
        return $this->taxonomy;
    }

    public function setTaxonomy(TaxonomyConfig $taxonomy): static
    {
        $this->taxonomy = $taxonomy;

        return $this;
    }

    /** @return array<string, string> */
    public function getFooterText(): array
    {
        return $this->footerText;
    }

    /** @param array<array-key, mixed> $footerText */
    public function setFooterText(array $footerText): static
    {
        $clean = [];
        foreach ($footerText as $locale => $text) {
            $trimmed = trim((string) $text);
            if ($trimmed === '') {
                continue;
            }
            $clean[(string) $locale] = $trimmed;
        }
        $this->footerText = $clean;

        return $this;
    }

    public function getFooterFor(string $locale): string
    {
        return $this->footerText[$locale] ?? '';
    }

    public function isPhoneticInList(): bool
    {
        return $this->phoneticInList;
    }

    public function setPhoneticInList(bool $phoneticInList): static
    {
        $this->phoneticInList = $phoneticInList;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'footerText' => $this->footerText,
            'phoneticInList' => $this->phoneticInList,
            'taxonomy' => $this->taxonomy->toArray(),
        ];
    }

    public static function fromArray(array $raw): static
    {
        $config = new self();
        $footer = $raw['footerText'] ?? [];
        if (is_array($footer)) {
            $config->setFooterText($footer);
        }
        $config->setPhoneticInList((bool) ($raw['phoneticInList'] ?? false));
        $config->taxonomy = TaxonomyConfig::fromArray($raw['taxonomy'] ?? []);

        return $config;
    }
}
