<?php declare(strict_types=1);

namespace Module\Trust\Tests\Stub;

use Module\Trust\Contract\RootProviderInterface;
use Override;

final class RootProvider implements RootProviderInterface
{
    public const int POINTS = 1000;

    public ?int $rootUserId = null;

    #[Override]
    public function getRootPoints(string $context, int $userId): ?int
    {
        return $context === ContextDescriber::CONTEXT && $userId === $this->rootUserId ? self::POINTS : null;
    }

    #[Override]
    public function getRootUserIds(string $context): iterable
    {
        return $context === ContextDescriber::CONTEXT && $this->rootUserId !== null ? [$this->rootUserId] : [];
    }
}
