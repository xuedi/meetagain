<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

/**
 * The composable taxonomy sub-object a plugin Config embeds: two independent enable flags and
 * two per-locale definition lists (categories, single-select; tags, controlled multi-select).
 *
 * Rows are held in form shape - array{id: int|string, labels: array<locale, label>} - so the
 * CollectionType binds directly. Ids are int once normalized (via normalize / fromArray) but may
 * transiently be an empty string for a freshly-added form row before normalization. Domain reads
 * go through the typed definition/label/option accessors.
 */
final class TaxonomyConfig
{
    private bool $categoriesEnabled = false;
    private bool $tagsEnabled = false;

    /** @var list<array{id: int|string, labels: array<string, string>}> */
    private array $categories = [];

    /** @var list<array{id: int|string, labels: array<string, string>}> */
    private array $tags = [];

    public function isCategoriesEnabled(): bool
    {
        return $this->categoriesEnabled;
    }

    public function setCategoriesEnabled(bool $categoriesEnabled): static
    {
        $this->categoriesEnabled = $categoriesEnabled;

        return $this;
    }

    public function isTagsEnabled(): bool
    {
        return $this->tagsEnabled;
    }

    public function setTagsEnabled(bool $tagsEnabled): static
    {
        $this->tagsEnabled = $tagsEnabled;

        return $this;
    }

    /** @return list<array{id: int|string, labels: array<string, string>}> */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /** @param iterable<array{id?: int|string|null, labels?: array<string, string>|null}> $categories */
    public function setCategories(iterable $categories): static
    {
        $this->categories = $this->ingestRows($categories);

        return $this;
    }

    /** @return list<array{id: int|string, labels: array<string, string>}> */
    public function getTags(): array
    {
        return $this->tags;
    }

    /** @param iterable<array{id?: int|string|null, labels?: array<string, string>|null}> $tags */
    public function setTags(iterable $tags): static
    {
        $this->tags = $this->ingestRows($tags);

        return $this;
    }

    /**
     * Assign a stable id to any new row, drop rows whose labels are all blank, and trim labels.
     * The per-language, two-list generalization of glossary's single-label normalizeCategories().
     */
    public function normalize(): void
    {
        $this->categories = $this->normalizeRows($this->categories);
        $this->tags = $this->normalizeRows($this->tags);
    }

    public function hasCategory(int $id): bool
    {
        return $this->hasRow($this->categories, $id);
    }

    public function hasTag(int $id): bool
    {
        return $this->hasRow($this->tags, $id);
    }

    /** @return list<CategoryDefinition> */
    public function categoryDefinitions(): array
    {
        return array_map(static fn(array $row): CategoryDefinition => new CategoryDefinition((int) $row['id'], $row['labels']), $this->categories);
    }

    /** @return list<TagDefinition> */
    public function tagDefinitions(): array
    {
        return array_map(static fn(array $row): TagDefinition => new TagDefinition((int) $row['id'], $row['labels']), $this->tags);
    }

    public function categoryLabel(int $id, ?string $locale, string $sourceLocale): ?string
    {
        foreach ($this->categoryDefinitions() as $definition) {
            if ($definition->id === $id) {
                return $definition->labelFor($locale, $sourceLocale);
            }
        }

        return null;
    }

    public function tagLabel(int $id, ?string $locale, string $sourceLocale): ?string
    {
        foreach ($this->tagDefinitions() as $definition) {
            if ($definition->id === $id) {
                return $definition->labelFor($locale, $sourceLocale);
            }
        }

        return null;
    }

    /** @return array<string, int> label => id, for a ChoiceType */
    public function categoryOptions(?string $locale, string $sourceLocale): array
    {
        $options = [];
        foreach ($this->categoryDefinitions() as $definition) {
            $options[$definition->labelFor($locale, $sourceLocale)] = $definition->id;
        }

        return $options;
    }

    /** @return array<string, int> label => id, for a ChoiceType */
    public function tagOptions(?string $locale, string $sourceLocale): array
    {
        $options = [];
        foreach ($this->tagDefinitions() as $definition) {
            $options[$definition->labelFor($locale, $sourceLocale)] = $definition->id;
        }

        return $options;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'categoriesEnabled' => $this->categoriesEnabled,
            'tagsEnabled' => $this->tagsEnabled,
            'categories' => $this->categories,
            'tags' => $this->tags,
        ];
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        $config = new self();
        $config->categoriesEnabled = (bool) ($raw['categoriesEnabled'] ?? false);
        $config->tagsEnabled = (bool) ($raw['tagsEnabled'] ?? false);
        $config->categories = self::rowsFromArray($raw['categories'] ?? []);
        $config->tags = self::rowsFromArray($raw['tags'] ?? []);

        return $config;
    }

    /**
     * @param iterable<array{id?: int|string|null, labels?: array<string, string>|null}> $rows
     * @return list<array{id: int|string, labels: array<string, string>}>
     */
    private function ingestRows(iterable $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $labels = [];
            foreach ((array) ($row['labels'] ?? []) as $locale => $label) {
                $labels[(string) $locale] = (string) ($label ?? '');
            }
            $clean[] = ['id' => $row['id'] ?? '', 'labels' => $labels];
        }

        return $clean;
    }

    /**
     * @param list<array{id: int|string, labels: array<string, string>}> $rows
     * @return list<array{id: int, labels: array<string, string>}>
     */
    private function normalizeRows(array $rows): array
    {
        $maxId = -1;
        foreach ($rows as $row) {
            if (!($row['id'] !== '' && (int) $row['id'] > $maxId)) {
                continue;
            }

            $maxId = (int) $row['id'];
        }

        $normalized = [];
        foreach ($rows as $row) {
            $labels = [];
            foreach ($row['labels'] as $locale => $label) {
                $trimmed = trim($label);
                if ($trimmed === '') {
                    continue;
                }
                $labels[$locale] = $trimmed;
            }

            if ($labels === []) {
                continue;
            }

            $normalized[] = [
                'id' => $row['id'] === '' ? ++$maxId : (int) $row['id'],
                'labels' => $labels,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array{id: int|string, labels: array<string, string>}> $rows
     */
    private function hasRow(array $rows, int $id): bool
    {
        foreach ($rows as $row) {
            if ($row['id'] !== '' && (int) $row['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param iterable<array{id?: int|string|null, labels?: array<string, string>|null}> $raw
     * @return list<array{id: int, labels: array<string, string>}>
     */
    private static function rowsFromArray(iterable $raw): array
    {
        $rows = [];
        foreach ($raw as $row) {
            $labels = [];
            foreach ((array) ($row['labels'] ?? []) as $locale => $label) {
                $labels[(string) $locale] = (string) $label;
            }
            $rows[] = ['id' => (int) ($row['id'] ?? 0), 'labels' => $labels];
        }

        return $rows;
    }
}
