<?php declare(strict_types=1);

namespace App\Item\Tag;

use App\Service\Config\PluginService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class TypeRegistry
{
    /**
     * @var array<string, TaggableTypeProviderInterface>|null
     */
    private ?array $active = null;

    /**
     * @param iterable<TaggableTypeProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(TaggableTypeProviderInterface::class)]
        private readonly iterable $providers,
        private readonly PluginService $pluginService,
    ) {}

    /**
     * @return list<TaggableTypeProviderInterface>
     */
    public function all(): array
    {
        return array_values($this->getActive());
    }

    public function has(string $typeKey): bool
    {
        return isset($this->getActive()[$typeKey]);
    }

    public function providerFor(string $typeKey): ?TaggableTypeProviderInterface
    {
        return $this->getActive()[$typeKey] ?? null;
    }

    public function providerForIncludingInactive(string $typeKey): ?TaggableTypeProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->getTypeKey() !== $typeKey) {
                continue;
            }

            return $provider;
        }

        return null;
    }

    /**
     * @return array<string, TaggableTypeProviderInterface>
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
