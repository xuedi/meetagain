<?php declare(strict_types=1);

namespace App\Item;

use App\Service\Config\PluginService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class ListRegistry
{
    /**
     * @var array<string, ListProviderInterface>|null
     */
    private ?array $active = null;

    /**
     * @var array<string, ListProviderInterface>|null
     */
    private ?array $byDetailRoute = null;

    /**
     * @param iterable<ListProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(ListProviderInterface::class)]
        private readonly iterable $providers,
        private readonly PluginService $pluginService,
    ) {}

    public function has(string $itemType): bool
    {
        return isset($this->getActive()[$itemType]);
    }

    public function providerFor(string $itemType): ?ListProviderInterface
    {
        return $this->getActive()[$itemType] ?? null;
    }

    public function itemTypeForDetailRouteIncludingInactive(string $route): ?string
    {
        $provider = $this->getByDetailRoute()[$route] ?? null;

        return $provider?->getKey();
    }

    public function isDetailRouteIndexable(string $route): bool
    {
        $provider = $this->getByDetailRoute()[$route] ?? null;

        return $provider?->isDetailIndexable() ?? true;
    }

    /**
     * @return array<string, ListProviderInterface>
     */
    public function activeProviders(): array
    {
        return $this->getActive();
    }

    /**
     * @return array<string, ListProviderInterface>
     */
    private function getByDetailRoute(): array
    {
        if ($this->byDetailRoute !== null) {
            return $this->byDetailRoute;
        }

        $map = [];
        foreach ($this->providers as $provider) {
            $route = $provider->getDetailRoute();
            if ($route !== null) {
                $map[$route] = $provider;
            }
        }

        return $this->byDetailRoute = $map;
    }

    /**
     * @return array<string, ListProviderInterface>
     */
    private function getActive(): array
    {
        if ($this->active !== null) {
            return $this->active;
        }

        $enabledPlugins = $this->pluginService->getActiveList();
        $map = [];
        foreach ($this->providers as $provider) {
            if (!in_array($provider->getPluginKey(), $enabledPlugins, true)) {
                continue;
            }

            $map[$provider->getKey()] = $provider;
        }

        return $this->active = $map;
    }
}
