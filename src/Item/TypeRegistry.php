<?php declare(strict_types=1);

namespace App\Item;

use App\Service\Config\PluginService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class TypeRegistry
{
    /**
     * @var array<string, TypeProviderInterface>|null
     */
    private ?array $active = null;

    /**
     * @param iterable<TypeProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(TypeProviderInterface::class)]
        private readonly iterable $providers,
        private readonly PluginService $pluginService,
    ) {}

    /**
     * @return list<TypeProviderInterface> active providers ordered by priority
     */
    public function all(): array
    {
        return array_values($this->getActive());
    }

    public function has(string $itemType): bool
    {
        return isset($this->getActive()[$itemType]);
    }

    public function providerFor(string $itemType): ?TypeProviderInterface
    {
        return $this->getActive()[$itemType] ?? null;
    }

    /**
     * @return array<string, TypeProviderInterface>
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

        uasort($map, static fn(TypeProviderInterface $a, TypeProviderInterface $b): int => $a->getPriority() <=> $b->getPriority());

        return $this->active = $map;
    }
}
