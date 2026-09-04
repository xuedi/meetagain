<?php declare(strict_types=1);

namespace Plugin\Boardgames\Filter;

use App\Item\FilterInterface;
use Override;

final readonly class GameItemFilter implements FilterInterface
{
    #[Override]
    public function getAllowedItemIds(string $itemType): ?array
    {
        return null;
    }
}
