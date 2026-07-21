<?php declare(strict_types=1);

namespace Plugin\Glossary\ValueObject;

use App\Item\Taxonomy\TaxonomyConfig;
use App\Publisher\PluginSettings\PluginSettingsData;

/**
 * Effective shape of one glossary: whether the secondary transcription field is shown, how the
 * primary/secondary/definition fields are labelled, and the shared per-language category taxonomy
 * (tags unused, but available without further schema work). The neutral default is term +
 * definition only (secondary off, no categories, shipped labels).
 */
final class Config implements PluginSettingsData
{
    private bool $secondaryEnabled = false;
    private ?string $secondaryLabel = null;
    private ?string $primaryLabel = null;
    private ?string $definitionLabel = null;

    private TaxonomyConfig $taxonomy;

    public function __construct()
    {
        $this->taxonomy = new TaxonomyConfig();
    }

    public function isSecondaryEnabled(): bool
    {
        return $this->secondaryEnabled;
    }

    public function setSecondaryEnabled(bool $secondaryEnabled): static
    {
        $this->secondaryEnabled = $secondaryEnabled;

        return $this;
    }

    public function getSecondaryLabel(): ?string
    {
        return $this->secondaryLabel;
    }

    public function setSecondaryLabel(?string $secondaryLabel): static
    {
        $this->secondaryLabel = $this->trimToNull($secondaryLabel);

        return $this;
    }

    public function getPrimaryLabel(): ?string
    {
        return $this->primaryLabel;
    }

    public function setPrimaryLabel(?string $primaryLabel): static
    {
        $this->primaryLabel = $this->trimToNull($primaryLabel);

        return $this;
    }

    public function getDefinitionLabel(): ?string
    {
        return $this->definitionLabel;
    }

    public function setDefinitionLabel(?string $definitionLabel): static
    {
        $this->definitionLabel = $this->trimToNull($definitionLabel);

        return $this;
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

    public function hasCategories(): bool
    {
        return $this->taxonomy->isCategoriesEnabled() && $this->taxonomy->categoryDefinitions() !== [];
    }

    public function toArray(): array
    {
        return [
            'secondaryEnabled' => $this->secondaryEnabled,
            'secondaryLabel' => $this->secondaryLabel,
            'primaryLabel' => $this->primaryLabel,
            'definitionLabel' => $this->definitionLabel,
            'taxonomy' => $this->taxonomy->toArray(),
        ];
    }

    public static function fromArray(array $raw): static
    {
        $config = new self();
        $config->secondaryEnabled = (bool) ($raw['secondaryEnabled'] ?? false);
        $config->secondaryLabel = self::trimToNullStatic($raw['secondaryLabel'] ?? null);
        $config->primaryLabel = self::trimToNullStatic($raw['primaryLabel'] ?? null);
        $config->definitionLabel = self::trimToNullStatic($raw['definitionLabel'] ?? null);
        $config->taxonomy = TaxonomyConfig::fromArray($raw['taxonomy'] ?? []);

        return $config;
    }

    private function trimToNull(?string $value): ?string
    {
        return self::trimToNullStatic($value);
    }

    private static function trimToNullStatic(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
