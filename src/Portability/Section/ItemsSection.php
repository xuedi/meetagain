<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Item\Registry;
use App\Portability\Item\TagAssignments;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use Override;

readonly class ItemsSection implements SectionInterface
{
    public function __construct(
        private Registry $registry,
        private TagAssignments $tagAssignments,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'items';
    }

    #[Override]
    public function getOrder(): int
    {
        return 60;
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        $items = [];

        foreach ($this->registry->all() as $contributor) {
            $itemType = $contributor->getItemType();
            $itemIds = $scope->itemIds[$itemType] ?? [];
            if ($itemIds === []) {
                continue;
            }

            $rows = $contributor->exportItems($itemIds, $images);
            if ($rows === []) {
                continue;
            }

            usort($rows, static fn(array $a, array $b): int => (int) ($a['ref'] ?? 0) <=> (int) ($b['ref'] ?? 0));
            $items[$itemType] = ['rows' => $rows, ...$this->tagAssignments->export($itemType, $itemIds, $scope->tagIds[$itemType] ?? [])];
        }
        ksort($items);

        return $items;
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        foreach ($rows as $itemType => $block) {
            $itemType = (string) $itemType;
            $hasRows = is_array($block) && is_array($block['rows'] ?? null);
            $itemRows = $hasRows ? array_values(array_filter($block['rows'], is_array(...))) : [];

            $contributor = $this->registry->contributorFor($itemType);
            if ($contributor === null || !is_array($block)) {
                $context->count($itemType, Outcome::Skipped, count($itemRows));
                continue;
            }

            $result = $contributor->importItems($itemRows, $context);
            $context->mapItems($itemType, $result->refToItemId);

            $context->count($itemType, Outcome::Created, $result->created);
            $context->count($itemType, Outcome::Matched, $result->matched);
            $this->tagAssignments->import($itemType, $block, $result->refToItemId, $context);
        }
    }
}
