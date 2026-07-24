<?php declare(strict_types=1);

namespace App\Review;

use App\Service\Config\PluginService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Unions the change-target providers whose plugin is globally enabled and answers keyed lookups.
 * Deliberately not gated on the request-context plugin list: proposals must stay resolvable on
 * every host a reviewer works from, and per-context enforcement already lives in the provider's
 * canPropose/canReview plus its target visibility.
 */
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
        return isset($this->getActive()[$targetType]);
    }

    public function providerFor(string $targetType): ?ChangeTargetProviderInterface
    {
        return $this->getActive()[$targetType] ?? null;
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
