<?php declare(strict_types=1);

namespace Plugin\Books\Filter;

use App\Item\ItemFilterInterface;
use Override;

final readonly class BookItemFilter implements ItemFilterInterface
{
    #[Override]
    public function getAllowedItemIds(string $itemType): ?array
    {
        return null;
    }
}
