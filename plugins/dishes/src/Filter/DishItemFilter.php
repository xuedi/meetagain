<?php declare(strict_types=1);

namespace Plugin\Dishes\Filter;

use App\Item\ItemFilterInterface;
use Override;

final readonly class DishItemFilter implements ItemFilterInterface
{
    #[Override]
    public function getAllowedItemIds(string $itemType): ?array
    {
        return null;
    }
}
