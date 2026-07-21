<?php declare(strict_types=1);

namespace Plugin\Films\ValueObject;

use App\Item\Taxonomy\TaxonomyConfig;
use App\Publisher\PluginSettings\PluginSettingsData;

/**
 * Films category/tag taxonomy settings. Kept separate from the SecretBox-backed API-key Settings
 * entity (which owns the 'films' settings key and needs a custom global-only store): the taxonomy
 * is a plain JSON blob, so it rides the generic per-scope store under its own key. The neutral
 * default is taxonomy disabled.
 */
final class Config implements PluginSettingsData
{
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

    public function toArray(): array
    {
        return [
            'taxonomy' => $this->taxonomy->toArray(),
        ];
    }

    public static function fromArray(array $raw): static
    {
        $config = new self();
        $config->taxonomy = TaxonomyConfig::fromArray($raw['taxonomy'] ?? []);

        return $config;
    }
}
