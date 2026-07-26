<?php declare(strict_types=1);

namespace Plugin\Books\ValueObject;

use App\Item\Taxonomy\Config as TaxonomyConfig;
use App\Publisher\PluginSettings\Data;
final class Config implements Data
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
