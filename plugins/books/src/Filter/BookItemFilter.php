<?php declare(strict_types=1);

namespace Plugin\Books\Filter;

use App\Item\ItemFilterInterface;
use Override;

/**
 * The books plugin's slot in the item visibility chain. It applies no restriction of its own
 * (returns null = no opinion, so the AND-intersection bypasses it); any external visibility
 * filter narrows the allowed books instead. This is the reserved seam for a future books-owned
 * visibility rule.
 */
final readonly class BookItemFilter implements ItemFilterInterface
{
    #[Override]
    public function getAllowedItemIds(string $itemType): ?array
    {
        return null;
    }
}
