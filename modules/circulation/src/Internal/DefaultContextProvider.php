<?php declare(strict_types=1);

namespace Module\Circulation\Internal;

use Module\Circulation\Contract\ContextProviderInterface;
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
