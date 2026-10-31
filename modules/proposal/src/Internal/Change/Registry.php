<?php declare(strict_types=1);

namespace Module\Proposal\Internal\Change;

use Module\Proposal\Contract\Change\TargetFamilyInterface;
use Module\Proposal\Contract\Change\TargetProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\ResetInterface;

// Gated on the global plugin list, never the request context: a proposal must stay resolvable on
// every host a reviewer works from
class Registry implements ResetInterface
{
    /**
     * @var array<string, TargetProviderInterface>|null
     */
    private ?array $active = null;

    /**
     * @param iterable<TargetProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(TargetProviderInterface::class)]
        private readonly iterable $providers,
    ) {}

    public function has(string $targetType): bool
    {
        return $this->providerFor($targetType) !== null;
    }

    public function providerFor(string $targetType): ?TargetProviderInterface
    {
        $exact = $this->getActive()[$targetType] ?? null;
        if ($exact !== null) {
            return $exact;
        }

        foreach ($this->providers as $provider) {
            if (!$provider instanceof TargetFamilyInterface || !$provider->handlesTargetType($targetType)) {
                continue;
            }

            return $provider->forTargetType($targetType);
        }

        return null;
    }

    /**
     * @return array<string, TargetProviderInterface>
     */
    private function getActive(): array
    {
        if ($this->active !== null) {
            return $this->active;
        }

        $map = [];
        foreach ($this->providers as $provider) {
            $map[$provider->getTargetType()] = $provider;
        }

        return $this->active = $map;
    }

    public function reset(): void
    {
        $this->active = null;
    }
}
