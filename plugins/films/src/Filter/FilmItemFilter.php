<?php declare(strict_types=1);

namespace Plugin\Films\Filter;

use App\Item\FilterInterface;
use Override;

final readonly class FilmItemFilter implements FilterInterface
{
    #[Override]
    public function getAllowedItemIds(string $itemType): ?array
    {
        return null;
    }
}
