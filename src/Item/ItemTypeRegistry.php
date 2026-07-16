<?php declare(strict_types=1);

namespace App\Item;

use App\Service\Config\PluginService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Unions the item-type providers whose plugin is active and answers keyed lookups.
 * Mirrors the active-plugin guard used by PluginExtension and EventService.
 */
class ItemTypeRegistry
{
    /**
     * @var array<string, ItemTypeProviderInterface>|null
     */
    private ?array $active = null;

    /**
     * @param iterable<ItemTypeProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(ItemTypeProviderInterface::class)]
        private readonly iterable $providers,
        private readonly PluginService $pluginService,
    ) {}

    /**
     * @return list<ItemTypeProviderInterface> active providers ordered by priority
     */
    public function all(): array
    {
        return array_values($this->getActive());
    }

    public function has(string $itemType): bool
    {
        return isset($this->getActive()[$itemType]);
    }

    public function providerFor(string $itemType): ?ItemTypeProviderInterface
    {
        return $this->getActive()[$itemType] ?? null;
    }

    /**
     * @return array<string, ItemTypeProviderInterface>
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

        uasort($map, static fn(ItemTypeProviderInterface $a, ItemTypeProviderInterface $b): int => $a->getPriority() <=> $b->getPriority());

        return $this->active = $map;
    }
}
