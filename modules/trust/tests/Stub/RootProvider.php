<?php declare(strict_types=1);

namespace Module\Trust\Tests\Stub;

use Module\Trust\Contract\RootProviderInterface;
use Override;

final readonly class RootProvider implements RootProviderInterface
{
    public function __construct(
        private UserLocator $locator,
    ) {}

    #[Override]
    public function getRootPoints(string $context, int $userId): ?int
    {
        if ($context !== ContextDescriber::CONTEXT) {
            return null;
        }

        return $userId === $this->locator->idFor(UserLocator::ROOT_EMAIL) ? 1000 : null;
    }

    #[Override]
    public function getRootUserIds(string $context): iterable
    {
        if ($context !== ContextDescriber::CONTEXT) {
            return;
        }

        $rootId = $this->locator->idFor(UserLocator::ROOT_EMAIL);
        if ($rootId !== null) {
            yield $rootId;
        }
    }
}
