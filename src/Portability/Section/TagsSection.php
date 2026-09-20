<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Entity\ItemTag;
use App\Item\Tag\TypeRegistry;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use App\Repository\ItemTagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class TagsSection implements SectionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ItemTagRepository $tagRepository,
        private TypeRegistry $typeRegistry,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'tags';
    }

    #[Override]
    public function getOrder(): int
    {
        return 55;
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        $tagIds = $scope->tagIds;
        ksort($tagIds);

        $rows = [];
        foreach ($tagIds as $itemType => $ids) {
            if ($ids === []) {
                continue;
            }

            $tags = $this->tagRepository->findBy(['itemType' => $itemType, 'id' => $ids], ['id' => 'ASC']);
            $exportedIds = array_map(static fn(ItemTag $tag): int => (int) $tag->getId(), $tags);

            foreach ($tags as $tag) {
                $labels = $tag->getLabels();
                ksort($labels);
                $parentId = $tag->getParent()?->getId();

                $rows[] = [
                    'ref' => (int) $tag->getId(),
                    'item_type' => $itemType,
                    'parent_ref' => $parentId !== null && in_array($parentId, $exportedIds, true) ? $parentId : null,
                    'position' => $tag->getPosition(),
                    'labels' => $labels,
                    'managed' => $tag->isManaged(),
                ];
            }
        }

        return $rows;
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        $rowsByType = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowsByType[(string) ($row['item_type'] ?? '')][(int) ($row['ref'] ?? 0)] = $row;
        }

        foreach ($rowsByType as $itemType => $typeRows) {
            if (!$this->typeRegistry->has($itemType)) {
                $context->count($this->getKey(), Outcome::Skipped, count($typeRows));
                continue;
            }

            $candidates = $this->tagRepository->findForType($itemType);
            foreach (array_keys($typeRows) as $ref) {
                $this->importTag($itemType, $ref, $typeRows, $candidates, $context, []);
            }
        }
    }

    /**
     * @param array<int, array<array-key, mixed>> $rows
     * @param list<ItemTag> $candidates
     * @param array<int, true> $visiting
     */
    private function importTag(string $itemType, int $ref, array $rows, array &$candidates, ImportContext $context, array $visiting): ?ItemTag
    {
        $mapped = $context->resolveRef(ItemTag::class, $ref);
        if ($mapped instanceof ItemTag) {
            return $mapped;
        }

        $row = $rows[$ref] ?? null;
        if ($row === null || isset($visiting[$ref])) {
            return null;
        }
        $visiting[$ref] = true;

        $parentRef = $row['parent_ref'] ?? null;
        $parent = $parentRef === null ? null : $this->importTag($itemType, (int) $parentRef, $rows, $candidates, $context, $visiting);
        $labels = $this->labels($row['labels'] ?? null);

        $tag = array_find($candidates, fn(ItemTag $candidate): bool => $candidate->getParent() === $parent && $this->sharesLabel($candidate, $labels));

        if ($tag instanceof ItemTag) {
            $context->count($this->getKey(), Outcome::Matched);
        } else {
            $tag = new ItemTag()
                ->setItemType($itemType)
                ->setLabels($labels)
                ->setParent($parent)
                ->setManaged((bool) ($row['managed'] ?? false))
                ->setPosition((int) ($row['position'] ?? 0));
            $this->em->persist($tag);
            $candidates[] = $tag;
            $context->count($this->getKey(), Outcome::Created);
        }

        $context->mapRef(ItemTag::class, $ref, $tag);

        return $tag;
    }

    /**
     * @return array<string, string>
     */
    private function labels(mixed $labels): array
    {
        $trimmed = [];
        foreach (is_array($labels) ? $labels : [] as $locale => $label) {
            $label = trim((string) $label);
            if ($label !== '') {
                $trimmed[(string) $locale] = $label;
            }
        }

        return $trimmed;
    }

    /**
     * @param array<string, string> $labels
     */
    private function sharesLabel(ItemTag $candidate, array $labels): bool
    {
        $candidateLabels = $candidate->getLabels();
        foreach ($labels as $locale => $label) {
            if (trim($candidateLabels[$locale] ?? '') === $label) {
                return true;
            }
        }

        return false;
    }
}
