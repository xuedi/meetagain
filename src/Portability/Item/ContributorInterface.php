<?php declare(strict_types=1);

namespace App\Portability\Item;

use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Serializes one item type out of and back into this instance. Every exported row carries a
 * 'ref' equal to its source item id; importItems() flushes before returning, maps every ref to
 * a real item id, and dispatches no item action.
 */
#[AutoconfigureTag]
interface ContributorInterface
{
    public function getPluginKey(): string;

    public function getItemType(): string;

    /**
     * @return list<int>
     */
    public function allItemIds(): array;

    /**
     * @param list<int> $itemIds
     * @return list<array<string, mixed>>
     */
    public function exportItems(array $itemIds, ImageWriterInterface $images): array;

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function importItems(array $rows, ImportContext $context): ImportResult;
}
