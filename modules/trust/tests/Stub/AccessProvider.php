<?php declare(strict_types=1);

namespace Module\Trust\Tests\Stub;

use Module\Trust\Contract\AccessProviderInterface;
use Override;

final readonly class AccessProvider implements AccessProviderInterface
{
    public function __construct(
        private UserLocator $locator,
    ) {}

    #[Override]
    public function canView(string $context, int $userId): ?bool
    {
        return $context === ContextDescriber::CONTEXT ? true : null;
    }

    #[Override]
    public function canAdminister(string $context, int $userId): ?bool
    {
        if ($context !== ContextDescriber::CONTEXT) {
            return null;
        }

        return $userId === $this->locator->idFor(UserLocator::ROOT_EMAIL);
    }
}
