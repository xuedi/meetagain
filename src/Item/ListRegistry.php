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
