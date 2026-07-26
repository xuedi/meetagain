<?php declare(strict_types=1);

namespace Plugin\Books\Filter;

use App\Item\FilterInterface;
use Override;

final readonly class BookItemFilter implements FilterInterface
{
    #[Override]
    public function getAllowedItemIds(string $itemType): ?array
    {
        return null;
    }
}
