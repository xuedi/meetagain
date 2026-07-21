<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use App\Service\Config\PluginService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Unions the categorizable-type providers whose plugin is active and answers keyed lookups.
 * Mirrors ItemTypeRegistry's active-plugin guard on the separate categorizable axis.
 */
class CategorizableTypeRegistry
{
    /**
     * @var array<string, CategorizableTypeProviderInterface>|null
     */
    private ?array $active = null;

    /**
     * @param iterable<CategorizableTypeProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(CategorizableTypeProviderInterface::class)]
        private readonly iterable $providers,
        private readonly PluginService $pluginService,
    ) {}

    /**
     * @return list<CategorizableTypeProviderInterface>
     */
    public function all(): array
    {
        return array_values($this->getActive());
    }

    public function has(string $typeKey): bool
    {
        return isset($this->getActive()[$typeKey]);
    }

    public function providerFor(string $typeKey): ?CategorizableTypeProviderInterface
    {
        return $this->getActive()[$typeKey] ?? null;
    }

    /**
     * @return array<string, CategorizableTypeProviderInterface>
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

            $map[$provider->getTypeKey()] = $provider;
        }

        return $this->active = $map;
    }
}
