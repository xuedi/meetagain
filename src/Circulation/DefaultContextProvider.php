<?php declare(strict_types=1);

namespace App\Circulation;

use Override;

final readonly class DefaultContextProvider implements ContextProviderInterface
{
    public const int PRIORITY = -1000;

    #[Override]
    public function getContext(string $itemType): ?string
    {
        return $itemType;
    }

    #[Override]
    public function getPriority(): int
    {
        return self::PRIORITY;
    }
}
