<?php declare(strict_types=1);

namespace Plugin\Dishes\Filter;

use App\Item\FilterInterface;
use Override;

final readonly class DishItemFilter implements FilterInterface
{
    #[Override]
    public function getAllowedItemIds(string $itemType): ?array
    {
        return null;
    }
}
