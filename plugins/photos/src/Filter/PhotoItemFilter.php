<?php declare(strict_types=1);

namespace Plugin\Photos\Filter;

use App\Item\FilterInterface;
use Override;

final readonly class PhotoItemFilter implements FilterInterface
{
    #[Override]
    public function getAllowedItemIds(string $itemType): ?array
    {
        return null;
    }
}
