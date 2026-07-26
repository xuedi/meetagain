<?php declare(strict_types=1);

namespace Plugin\Films\Filter;

use App\Item\ItemFilterInterface;
use Override;

final readonly class FilmItemFilter implements ItemFilterInterface
{
    #[Override]
    public function getAllowedItemIds(string $itemType): ?array
    {
        return null;
    }
}
