<?php declare(strict_types=1);

namespace Module\Trust\Tests\Stub;

use Module\Trust\Contract\AccessProviderInterface;
use Override;

final class AccessProvider implements AccessProviderInterface
{
    public ?int $administratorId = null;

    /** @var list<int> */
    public array $outsiderIds = [];

    #[Override]
    public function canView(string $context, int $userId): ?bool
    {
        return $context === ContextDescriber::CONTEXT ? !in_array($userId, $this->outsiderIds, true) : null;
    }

    #[Override]
    public function canAdminister(string $context, int $userId): ?bool
    {
        return $context === ContextDescriber::CONTEXT ? $userId === $this->administratorId : null;
    }
}
