<?php declare(strict_types=1);

namespace Plugin\Books\ValueObject;

use App\Item\Taxonomy\TaxonomyConfig;
use App\Publisher\PluginSettings\PluginSettingsData;

/**
 * Effective books settings: the shared category/tag taxonomy. The neutral default is taxonomy
 * disabled. Books had no settings surface before; this is the first, added solely to carry the
 * per-scope taxonomy definitions.
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
