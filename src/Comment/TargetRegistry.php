<?php declare(strict_types=1);

namespace App\Comment;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class TargetRegistry
{
    /**
     * @param iterable<TargetProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(TargetProviderInterface::class)]
        private iterable $providers,
    ) {}

    public function providerFor(string $targetType): ?TargetProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->getTypeKey() === $targetType) {
                return $provider;
            }
        }

        return null;
    }

    public function has(string $targetType): bool
    {
        return $this->providerFor($targetType) !== null;
    }
}
