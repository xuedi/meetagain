<?php declare(strict_types=1);

namespace App\Item\Portability;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Serializes one item type out of and back into this instance. Both directions live on one
 * interface because they must agree on the row shape.
 *
 * Implementations promise:
 *   - every exported row carries a 'ref' equal to the source item id; the rest of the row is
 *     opaque to core
 *   - the caller decides which ids to export; the contributor never asks who owns them
 *   - importItems() persists and flushes before returning, and maps every incoming ref to a real
 *     item id - a row resolved to an already-present item maps to that item and counts as matched
 *   - importItems() dispatches no item action: an import is not a user action
 */
#[AutoconfigureTag]
interface ItemPortabilityContributorInterface
{
    public function getPluginKey(): string;

    public function getItemType(): string;

    /**
     * @param list<int> $itemIds
     * @return list<array<string, mixed>>
     */
    public function exportItems(array $itemIds, PortableImageWriterInterface $images): array;

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function importItems(array $rows, ItemImportContext $context): ItemImportResult;
}
