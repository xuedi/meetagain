<?php declare(strict_types=1);

namespace Plugin\Glossary\Config;

use App\Publisher\PluginSettings\PluginSettingsData;

/**
 * Effective shape of one glossary: whether the secondary transcription field is shown and
 * how the primary/secondary/definition fields and the category taxonomy are labelled. The
 * neutral default is term + definition only (secondary off, no categories, shipped labels).
 */
final class GlossaryConfig implements PluginSettingsData
{
    private bool $secondaryEnabled = false;
    private ?string $secondaryLabel = null;
    private ?string $primaryLabel = null;
    private ?string $definitionLabel = null;

    /**
     * Category rows. Ids are int once normalized (via normalizeCategories / fromArray) but may
     * transiently be an empty string (a freshly-added form row) before normalization.
     *
     * @var list<array{id: int|string, label: string}>
     */
    private array $categories = [];

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

    /** @return list<array{id: int|string, label: string}> */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /** @param iterable<array{id?: int|string|null, label?: string|null}> $categories */
    public function setCategories(iterable $categories): static
    {
        $rows = [];
        foreach ($categories as $category) {
            $rows[] = [
                'id' => $category['id'] ?? '',
                'label' => $category['label'] ?? '',
            ];
        }
        $this->categories = $rows;

        return $this;
    }

    public function hasCategories(): bool
    {
        return $this->categories !== [];
    }

    /** @return array<int, string> id => label, preserving order */
    public function getCategoryMap(): array
    {
        $map = [];
        foreach ($this->categories as $category) {
            $map[(int) $category['id']] = $category['label'];
        }

        return $map;
    }

    public function getCategoryLabel(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        return $this->getCategoryMap()[$id] ?? null;
    }

    /**
     * Assign a stable id to any row lacking one and drop rows with empty labels. Existing
     * ids are preserved so glossary entries keep pointing at the same category.
     */
    public function normalizeCategories(): void
    {
        $maxId = -1;
        foreach ($this->categories as $category) {
            if ($category['id'] !== '' && (int) $category['id'] > $maxId) {
                $maxId = (int) $category['id'];
            }
        }

        $normalized = [];
        foreach ($this->categories as $category) {
            $label = trim($category['label']);
            if ($label === '') {
                continue;
            }

            $normalized[] = [
                'id' => $category['id'] === '' ? ++$maxId : (int) $category['id'],
                'label' => $label,
            ];
        }

        $this->categories = $normalized;
    }

    public function toArray(): array
    {
        return [
            'secondaryEnabled' => $this->secondaryEnabled,
            'secondaryLabel' => $this->secondaryLabel,
            'primaryLabel' => $this->primaryLabel,
            'definitionLabel' => $this->definitionLabel,
            'categories' => $this->categories,
        ];
    }

    public static function fromArray(array $raw): static
    {
        $config = new self();
        $config->secondaryEnabled = (bool) ($raw['secondaryEnabled'] ?? false);
        $config->secondaryLabel = self::trimToNullStatic($raw['secondaryLabel'] ?? null);
        $config->primaryLabel = self::trimToNullStatic($raw['primaryLabel'] ?? null);
        $config->definitionLabel = self::trimToNullStatic($raw['definitionLabel'] ?? null);

        $categories = [];
        foreach ($raw['categories'] ?? [] as $category) {
            $categories[] = ['id' => (int) $category['id'], 'label' => (string) $category['label']];
        }
        $config->categories = $categories;

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
