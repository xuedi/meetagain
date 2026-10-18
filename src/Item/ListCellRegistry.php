<?php declare(strict_types=1);

namespace App\Item;

use App\Service\Config\PluginService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\ResetInterface;

class ListCellRegistry implements ResetInterface
{
    private const string CORE_PLUGIN_KEY = '';

    /**
     * @var array<string, ListCellProviderInterface>|null
     */
    private ?array $active = null;

    /**
     * @param iterable<ListCellProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(ListCellProviderInterface::class)]
        private readonly iterable $providers,
        private readonly PluginService $pluginService,
    ) {}

    public function has(string $itemType): bool
    {
        return isset($this->getActive()[$itemType]);
    }

    public function providerFor(string $itemType): ?ListCellProviderInterface
    {
        return $this->getActive()[$itemType] ?? null;
    }

    /**
     * @return array<string, ListCellProviderInterface>
     */
    private function getActive(): array
    {
        if ($this->active !== null) {
            return $this->active;
        }

        $enabledPlugins = $this->pluginService->getActiveList();
        $map = [];
        foreach ($this->providers as $provider) {
            $pluginKey = $provider->getPluginKey();
            if ($pluginKey !== self::CORE_PLUGIN_KEY && !in_array($pluginKey, $enabledPlugins, true)) {
                continue;
            }

            $map[$provider->getKey()] = $provider;
        }

        return $this->active = $map;
    }

    public function reset(): void
    {
        $this->active = null;
    }
}
