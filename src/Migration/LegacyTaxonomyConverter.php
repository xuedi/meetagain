<?php declare(strict_types=1);

namespace App\Migration;

use App\Entity\ItemTag;
use App\Entity\ItemTagAssignment;
use App\Repository\ItemTagAssignmentRepository;
use App\Repository\ItemTagRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

readonly class LegacyTaxonomyConverter
{
    public const string GROUP_AXIS = 'group';
    public const string CATEGORY_AXIS = 'category';
    public const string TAG_AXIS = 'tag';

    public function __construct(
        private EntityManagerInterface $em,
        private ItemTagRepository $tagRepo,
        private ItemTagAssignmentRepository $assignmentRepo,
        private Connection $connection,
    ) {}

    public function mapKey(string $itemType, string $scope, string $axis, int $oldId): string
    {
        return sprintf('%s|%s|%s|%d', $itemType, $scope, $axis, $oldId);
    }

    /** @param array<string, int> $map */
    public function isConverted(string $itemType, string $scope, array $map): bool
    {
        $prefix = sprintf('%s|%s|', $itemType, $scope);

        return array_any(array_keys($map), static fn(string $key): bool => str_starts_with($key, $prefix));
    }

    /**
     * @param  array<string, mixed>                            $taxonomy the stored taxonomy block
     * @return array{map: array<string, int>, tags: list<ItemTag>}
     */
    public function convertVocabulary(string $itemType, string $scope, array $taxonomy): array
    {
        $position = $this->tagRepo->nextPosition($itemType);
        $keyed = [];

        $groups = [];
        foreach ($this->rowsOf($taxonomy, 'categoryGroups') as $row) {
            $tag = $this->createTag($itemType, $row['labels'], null, $position++);
            $groups[$row['id']] = $tag;
            $keyed[$this->mapKey($itemType, $scope, self::GROUP_AXIS, $row['id'])] = $tag;
        }

        foreach ($this->rowsOf($taxonomy, 'categories') as $row) {
            $parent = $row['parent'] === null ? null : ($groups[$row['parent']] ?? null);
            $tag = $this->createTag($itemType, $row['labels'], $parent, $position++);
            $keyed[$this->mapKey($itemType, $scope, self::CATEGORY_AXIS, $row['id'])] = $tag;
        }

        $created = [];
        foreach ($this->rowsOf($taxonomy, 'tags') as $row) {
            $tag = $this->createTag($itemType, $row['labels'], null, $position++);
            $created[$row['id']] = $tag;
            $keyed[$this->mapKey($itemType, $scope, self::TAG_AXIS, $row['id'])] = $tag;
        }

        foreach ($this->rowsOf($taxonomy, 'tags') as $row) {
            $tag = $created[$row['id']];
            $parent = $row['parent'] === null ? null : ($created[$row['parent']] ?? null);
            $tag->setParent($parent === $tag ? null : $parent);
        }

        $this->em->flush();

        return [
            'map' => array_map(static fn(ItemTag $tag): int => (int) $tag->getId(), $keyed),
            'tags' => array_values($keyed),
        ];
    }

    /** @return array<string, list<int>> "<itemType>|<itemId>" => the tag ids assigned to it */
    public function snapshotAssignments(): array
    {
        $snapshot = [];
        foreach ($this->connection->fetchAllAssociative('SELECT item_type, item_id, tag_id FROM item_tag_assignment') as $row) {
            $snapshot[$row['item_type'] . '|' . $row['item_id']][] = (int) $row['tag_id'];
        }

        return $snapshot;
    }

    /** @return array<string, int> "<itemType>|<itemId>" => the category id assigned to it */
    public function legacyCategories(): array
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['item_category_assignment'])) {
            return [];
        }

        $categories = [];
        foreach ($this->connection->fetchAllAssociative('SELECT item_type, item_id, category_id FROM item_category_assignment') as $row) {
            $categories[$row['item_type'] . '|' . $row['item_id']] = (int) $row['category_id'];
        }

        return $categories;
    }

    /**
     * @param array<string, int> $map
     * @param list<int>          $originalTagIds
     */
    public function rewriteAssignments(string $itemType, int $itemId, string $scope, ?int $categoryId, array $originalTagIds, array $map): void
    {
        $wanted = [];
        $lookups = [];
        if ($categoryId !== null) {
            $lookups[] = $map[$this->mapKey($itemType, $scope, self::CATEGORY_AXIS, $categoryId)] ?? null;
        }
        foreach ($originalTagIds as $oldId) {
            $lookups[] = $map[$this->mapKey($itemType, $scope, self::TAG_AXIS, $oldId)] ?? null;
        }

        foreach (array_filter($lookups) as $newId) {
            $tag = $this->em->find(ItemTag::class, $newId);
            if ($tag === null) {
                continue;
            }

            foreach ([$tag, ...$tag->getAncestors()] as $node) {
                $wanted[(int) $node->getId()] = $node;
            }
        }

        $this->assignmentRepo->deleteFor($itemType, $itemId);
        foreach ($wanted as $tag) {
            $assignment = new ItemTagAssignment();
            $assignment->setItemType($itemType);
            $assignment->setItemId($itemId);
            $assignment->setTag($tag);
            $this->em->persist($assignment);
        }
        $this->em->flush();
    }

    /** @param array<string, string> $labels */
    private function createTag(string $itemType, array $labels, ?ItemTag $parent, int $position): ItemTag
    {
        $tag = new ItemTag();
        $tag->setItemType($itemType);
        $tag->setLabels($labels);
        $tag->setParent($parent);
        $tag->setPosition($position);
        $this->em->persist($tag);

        return $tag;
    }

    /**
     * @param  array<string, mixed> $taxonomy
     * @return list<array{id: int, labels: array<string, string>, parent: ?int}>
     */
    private function rowsOf(array $taxonomy, string $key): array
    {
        $rows = [];
        foreach ((array) ($taxonomy[$key] ?? []) as $raw) {
            $labels = [];
            foreach ((array) ($raw['labels'] ?? []) as $locale => $label) {
                $trimmed = trim((string) $label);
                if ($trimmed === '') {
                    continue;
                }

                $labels[(string) $locale] = $trimmed;
            }

            if ($labels === [] || !is_numeric($raw['id'] ?? null)) {
                continue;
            }

            $parent = $key === 'categories' ? ($raw['group'] ?? null) : ($raw['parent'] ?? null);
            $rows[] = [
                'id' => (int) $raw['id'],
                'labels' => $labels,
                'parent' => is_numeric($parent) ? (int) $parent : null,
            ];
        }

        return $rows;
    }
}
