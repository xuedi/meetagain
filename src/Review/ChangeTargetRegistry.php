<?php declare(strict_types=1);

namespace App\Review;

use App\Service\Config\PluginService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

// Gated on the global plugin list, never the request context: a proposal must stay resolvable on
// every host a reviewer works from
class ChangeTargetRegistry
{
    /**
     * @var array<string, ChangeTargetProviderInterface>|null
     */
    private ?array $active = null;

    /**
     * @param iterable<ChangeTargetProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(ChangeTargetProviderInterface::class)]
        private readonly iterable $providers,
        private readonly PluginService $pluginService,
    ) {}

    public function has(string $targetType): bool
    {
        return $this->providerFor($targetType) !== null;
    }

    public function providerFor(string $targetType): ?ChangeTargetProviderInterface
    {
        $exact = $this->getActive()[$targetType] ?? null;
        if ($exact !== null) {
            return $exact;
        }

        foreach ($this->providers as $provider) {
            if (!$provider instanceof ChangeTargetFamilyInterface || !$provider->handlesTargetType($targetType)) {
                continue;
            }

            return $provider->forTargetType($targetType);
        }

        return null;
    }

    /**
     * @return array<string, ChangeTargetProviderInterface>
     */
    private function getActive(): array
    {
        if ($this->active !== null) {
            return $this->active;
        }

        $enabledPlugins = $this->pluginService->getGloballyActiveList();
        $map = [];
        foreach ($this->providers as $provider) {
            if (!in_array($provider->getPluginKey(), $enabledPlugins, true)) {
                continue;
            }

            $map[$provider->getTargetType()] = $provider;
        }

        return $this->active = $map;
    }
}
