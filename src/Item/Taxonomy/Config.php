<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

final class Config
{
    public const int MAX_TAG_DEPTH = 5;

    private bool $categoriesEnabled = false;
    private bool $tagsEnabled = false;
    private int $tagDepth = 1;

    /** @var list<array{id: int|string, labels: array<string, string>, group: int|string|null, parent: int|string|null}> */
    private array $categoryGroups = [];

    /** @var list<array{id: int|string, labels: array<string, string>, group: int|string|null, parent: int|string|null}> */
    private array $categories = [];

    /** @var list<array{id: int|string, labels: array<string, string>, group: int|string|null, parent: int|string|null}> */
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

    public function getTagDepth(): int
    {
        return $this->tagDepth;
    }

    public function setTagDepth(?int $tagDepth): static
    {
        $this->tagDepth = max(1, min(self::MAX_TAG_DEPTH, $tagDepth ?? 1));

        return $this;
    }

    /** @return list<array{id: int|string, labels: array<string, string>, group: int|string|null, parent: int|string|null}> */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /** @param iterable<array{id?: int|string|null, labels?: array<string, string>|null, group?: int|string|null, parent?: int|string|null}> $categories */
    public function setCategories(iterable $categories): static
    {
        $this->categories = $this->ingestRows($categories);

        return $this;
    }

    /** @return list<array{id: int|string, labels: array<string, string>, group: int|string|null, parent: int|string|null}> */
    public function getTags(): array
    {
        return $this->tags;
    }

    /** @param iterable<array{id?: int|string|null, labels?: array<string, string>|null, group?: int|string|null, parent?: int|string|null}> $tags */
    public function setTags(iterable $tags): static
    {
        $this->tags = $this->ingestRows($tags);

        return $this;
    }

    /** @return list<array{id: int|string, labels: array<string, string>, group: int|string|null, parent: int|string|null}> */
    public function getCategoryGroups(): array
    {
        return $this->categoryGroups;
    }

    /** @param iterable<array{id?: int|string|null, labels?: array<string, string>|null}> $categoryGroups */
    public function setCategoryGroups(iterable $categoryGroups): static
    {
        $this->categoryGroups = $this->ingestRows($categoryGroups);

        return $this;
    }

    public function normalize(): void
    {
        $this->normalizeAxis(Axis::Category);
        $this->normalizeAxis(Axis::Tag);
    }

    public function hasCategory(int $id): bool
    {
        return $this->hasRow($this->categories, $id);
    }

    public function hasTag(int $id): bool
    {
        return $this->hasRow($this->tags, $id);
    }

    public function isEnabled(Axis $axis): bool
    {
        return $axis === Axis::Category ? $this->categoriesEnabled : $this->tagsEnabled;
    }

    public function hasDefinition(Axis $axis, int $id): bool
    {
        return $this->hasRow($this->rows($axis), $id);
    }

    /** @return list<AbstractDefinition> */
    public function definitions(Axis $axis): array
    {
        return $axis === Axis::Category ? $this->categoryDefinitions() : $this->tagDefinitions();
    }

    /** @return list<array{group: ?GroupDefinition, definitions: list<AbstractDefinition>}> */
    public function groupedDefinitions(Axis $axis): array
    {
        if ($axis === Axis::Tag) {
            $tags = $this->tagTree()->ordered();

            return $tags === [] ? [] : [['group' => null, 'definitions' => $tags]];
        }

        $ungrouped = [];
        $members = [];
        foreach ($this->categoryDefinitions() as $definition) {
            if ($definition->group === null) {
                $ungrouped[] = $definition;

                continue;
            }

            $members[$definition->group][] = $definition;
        }

        $grouped = $ungrouped === [] ? [] : [['group' => null, 'definitions' => $ungrouped]];
        foreach ($this->categoryGroupDefinitions() as $group) {
            if (!isset($members[$group->id])) {
                continue;
            }

            $grouped[] = ['group' => $group, 'definitions' => $members[$group->id]];
        }

        return $grouped;
    }

    /** @return array<int, string> definition id => its label in that locale, skipping rows without one */
    public function labelsInLocale(Axis $axis, string $locale): array
    {
        $labels = [];
        foreach ($this->rows($axis) as $row) {
            $label = trim($row['labels'][$locale] ?? '');
            if (!is_numeric($row['id']) || $label === '') {
                continue;
            }

            $labels[(int) $row['id']] = $label;
        }

        return $labels;
    }

    public function addLabel(Axis $axis, string $locale, string $label, ?int $parent = null): static
    {
        $rows = $this->rows($axis);
        $rows[] = ['id' => '', 'labels' => [$locale => $label], 'group' => null, 'parent' => $parent];

        return $this->setRows($axis, $rows);
    }

    public function setLabel(Axis $axis, int $id, string $locale, string $label): static
    {
        $rows = $this->rows($axis);
        foreach ($rows as $index => $row) {
            if (!is_numeric($row['id']) || (int) $row['id'] !== $id) {
                continue;
            }

            $rows[$index]['labels'][$locale] = $label;
        }

        return $this->setRows($axis, $rows);
    }

    public function removeDefinition(Axis $axis, int $id): static
    {
        $rows = array_values(array_filter($this->rows($axis), static fn(array $row): bool => !is_numeric($row['id']) || (int) $row['id'] !== $id));

        return $this->setRows($axis, $rows);
    }

    /** @return list<CategoryDefinition> */
    public function categoryDefinitions(): array
    {
        return array_map(
            static fn(array $row): CategoryDefinition => new CategoryDefinition((int) $row['id'], $row['labels'], self::groupOf($row)),
            $this->categories,
        );
    }

    /** @return list<TagDefinition> */
    public function tagDefinitions(): array
    {
        $known = [];
        foreach ($this->tags as $row) {
            if (!is_numeric($row['id'])) {
                continue;
            }

            $known[(int) $row['id']] = true;
        }

        return array_map(static function (array $row) use ($known): TagDefinition {
            $parent = is_numeric($row['parent']) ? (int) $row['parent'] : null;

            return new TagDefinition(
                (int) $row['id'],
                $row['labels'],
                $parent !== null && $parent !== (int) $row['id'] && isset($known[$parent]) ? $parent : null,
            );
        }, $this->tags);
    }

    public function tagTree(): TagTree
    {
        return new TagTree($this->tagDefinitions());
    }

    /** @return list<GroupDefinition> */
    public function categoryGroupDefinitions(): array
    {
        return array_map(
            static fn(array $row): GroupDefinition => new GroupDefinition((int) $row['id'], $row['labels']),
            $this->categoryGroups,
        );
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

    /** @return array<string, int> label => id, depth-first so a sub-tag follows the tag it hangs from */
    public function tagOptions(?string $locale, string $sourceLocale): array
    {
        $options = [];
        foreach ($this->tagTree()->ordered() as $definition) {
            $options[$definition->labelFor($locale, $sourceLocale)] = $definition->id;
        }

        return $options;
    }

    /** @return array<string, int|array<string, int>> label => id, group label => nested options, for a ChoiceType */
    public function groupedCategoryOptions(?string $locale, string $sourceLocale): array
    {
        $options = [];
        foreach ($this->groupedDefinitions(Axis::Category) as $bucket) {
            $choices = [];
            foreach ($bucket['definitions'] as $definition) {
                $choices[$definition->labelFor($locale, $sourceLocale)] = $definition->id;
            }

            if ($bucket['group'] === null) {
                $options = [...$options, ...$choices];

                continue;
            }

            $options[$bucket['group']->labelFor($locale, $sourceLocale)] = $choices;
        }

        return $options;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'categoriesEnabled' => $this->categoriesEnabled,
            'tagsEnabled' => $this->tagsEnabled,
            'tagDepth' => $this->tagDepth,
            'categoryGroups' => self::exportRows($this->categoryGroups),
            'categories' => self::exportRows($this->categories),
            'tags' => self::exportRows($this->tags),
        ];
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        $config = new self();
        $config->categoriesEnabled = (bool) ($raw['categoriesEnabled'] ?? false);
        $config->tagsEnabled = (bool) ($raw['tagsEnabled'] ?? false);
        $config->setTagDepth(isset($raw['tagDepth']) ? (int) $raw['tagDepth'] : null);
        $config->categoryGroups = self::rowsFromArray($raw['categoryGroups'] ?? []);
        $config->categories = self::rowsFromArray($raw['categories'] ?? []);
        $config->tags = self::rowsFromArray($raw['tags'] ?? []);

        return $config;
    }

    /** @return list<array{id: int|string, labels: array<string, string>, group: int|string|null, parent: int|string|null}> */
    private function rows(Axis $axis): array
    {
        return $axis === Axis::Category ? $this->categories : $this->tags;
    }

    /** @param list<array{id: int|string, labels: array<string, string>, group: int|string|null, parent: int|string|null}> $rows */
    private function setRows(Axis $axis, array $rows): static
    {
        if ($axis === Axis::Category) {
            $this->categories = $rows;

            return $this;
        }

        $this->tags = $rows;

        return $this;
    }

    /**
     * @param iterable<array{id?: int|string|null, labels?: array<string, string>|null, group?: int|string|null, parent?: int|string|null}> $rows
     * @return list<array{id: int|string, labels: array<string, string>, group: int|string|null, parent: int|string|null}>
     */
    private function ingestRows(iterable $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $labels = [];
            foreach ((array) ($row['labels'] ?? []) as $locale => $label) {
                $labels[(string) $locale] = (string) ($label ?? '');
            }
            $clean[] = [
                'id' => $row['id'] ?? '',
                'labels' => $labels,
                'group' => $row['group'] ?? null,
                'parent' => $row['parent'] ?? null,
            ];
        }

        return $clean;
    }

    private function normalizeAxis(Axis $axis): void
    {
        $groupIds = $axis === Axis::Category ? $this->normalizeCategoryGroups() : [];

        $definitions = [];
        $definitionIds = [];
        $nextId = self::maxId($this->rows($axis));
        foreach ($this->rows($axis) as $row) {
            $labels = self::trimmedLabels($row['labels']);
            if ($labels === []) {
                continue;
            }

            $id = is_numeric($row['id']) ? (int) $row['id'] : ++$nextId;
            $definitionIds[(string) $row['id']] = $id;
            $definitionIds[(string) $id] = $id;
            $definitions[] = [
                'id' => $id,
                'labels' => $labels,
                'group' => $groupIds[(string) $row['group']] ?? null,
                'parent' => $axis === Axis::Tag ? $row['parent'] : null,
            ];
        }
        unset($definitionIds['']);

        $this->setRows($axis, self::normalizedForest($definitions, $definitionIds, $this->tagDepth));
    }

    /** @return array<string, int> submitted id or client token => the id it was allocated */
    private function normalizeCategoryGroups(): array
    {
        $ids = [];
        $groups = [];
        $nextId = self::maxId($this->categoryGroups);
        foreach ($this->categoryGroups as $row) {
            $labels = self::trimmedLabels($row['labels']);
            if ($labels === []) {
                continue;
            }

            $id = is_numeric($row['id']) ? (int) $row['id'] : ++$nextId;
            $ids[(string) $row['id']] = $id;
            $ids[(string) $id] = $id;
            $groups[] = ['id' => $id, 'labels' => $labels, 'group' => null, 'parent' => null];
        }
        unset($ids['']);

        $this->categoryGroups = $groups;

        return $ids;
    }

    /**
     * @param list<array{id: int, labels: array<string, string>, group: ?int, parent: int|string|null}> $definitions
     * @param array<string, int> $ids submitted id or client token => the id it was allocated
     * @return list<array{id: int, labels: array<string, string>, group: ?int, parent: ?int}>
     */
    private static function normalizedForest(array $definitions, array $ids, int $maxDepth): array
    {
        $parents = [];
        foreach ($definitions as $index => $row) {
            $parent = $ids[(string) $row['parent']] ?? null;
            $definitions[$index]['parent'] = $parent === $row['id'] ? null : $parent;
            $parents[$row['id']] = $definitions[$index]['parent'];
        }

        foreach ($definitions as $index => $row) {
            $depth = 1;
            $walked = [$row['id']];
            for ($ancestor = $parents[$row['id']]; $ancestor !== null; $ancestor = $parents[$ancestor] ?? null) {
                $depth++;
                if ($depth > $maxDepth || in_array($ancestor, $walked, true)) {
                    $definitions[$index]['parent'] = null;
                    $parents[$row['id']] = null;

                    break;
                }

                $walked[] = $ancestor;
            }
        }

        return $definitions;
    }

    /**
     * @param array<string, string> $labels
     * @return array<string, string>
     */
    private static function trimmedLabels(array $labels): array
    {
        $trimmed = [];
        foreach ($labels as $locale => $label) {
            $value = trim($label);
            if ($value === '') {
                continue;
            }

            $trimmed[$locale] = $value;
        }

        return $trimmed;
    }

    /** @param list<array{id: int|string, labels: array<string, string>, group: int|string|null, parent: int|string|null}> $rows */
    private static function maxId(array $rows): int
    {
        $max = -1;
        foreach ($rows as $row) {
            if (!is_numeric($row['id']) || (int) $row['id'] <= $max) {
                continue;
            }

            $max = (int) $row['id'];
        }

        return $max;
    }

    /** @param array{id: int|string, labels: array<string, string>, group: int|string|null, parent: int|string|null} $row */
    private static function groupOf(array $row): ?int
    {
        return is_numeric($row['group']) ? (int) $row['group'] : null;
    }

    /** @param list<array{id: int|string, labels: array<string, string>, group: int|string|null, parent: int|string|null}> $rows */
    private function hasRow(array $rows, int $id): bool
    {
        foreach ($rows as $row) {
            if (is_numeric($row['id']) && (int) $row['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{id: int|string, labels: array<string, string>, group: int|string|null, parent: int|string|null}> $rows
     * @return list<array{id: int|string, labels: array<string, string>, group?: int, parent?: int}>
     */
    private static function exportRows(array $rows): array
    {
        $exported = [];
        foreach ($rows as $row) {
            $export = ['id' => $row['id'], 'labels' => $row['labels']];
            $group = self::groupOf($row);
            if ($group !== null) {
                $export['group'] = $group;
            }
            if (is_numeric($row['parent'])) {
                $export['parent'] = (int) $row['parent'];
            }
            $exported[] = $export;
        }

        return $exported;
    }

    /**
     * @param iterable<array{id?: int|string|null, labels?: array<string, string>|null, group?: int|string|null, parent?: int|string|null}> $raw
     * @return list<array{id: int, labels: array<string, string>, group: ?int, parent: ?int}>
     */
    private static function rowsFromArray(iterable $raw): array
    {
        $rows = [];
        foreach ($raw as $row) {
            $labels = [];
            foreach ((array) ($row['labels'] ?? []) as $locale => $label) {
                $labels[(string) $locale] = (string) $label;
            }
            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'labels' => $labels,
                'group' => isset($row['group']) ? (int) $row['group'] : null,
                'parent' => isset($row['parent']) ? (int) $row['parent'] : null,
            ];
        }

        return $rows;
    }
}
