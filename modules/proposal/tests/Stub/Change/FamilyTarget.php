<?php declare(strict_types=1);

namespace Module\Proposal\Tests\Stub\Change;

use Module\Proposal\Contract\Change\TargetFamilyInterface;
use Module\Proposal\Contract\Change\TargetProviderInterface;
use Override;

final class FamilyTarget extends Target implements TargetFamilyInterface
{
    public const string PREFIX = 'stub_family_';

    private string $boundType = self::PREFIX . 'root';

    #[Override]
    public function getTargetType(): string
    {
        return $this->boundType;
    }

    #[Override]
    public function handlesTargetType(string $targetType): bool
    {
        return str_starts_with($targetType, self::PREFIX);
    }

    #[Override]
    public function forTargetType(string $targetType): TargetProviderInterface
    {
        $bound = clone $this;
        $bound->boundType = $targetType;

        return $bound;
    }
}
