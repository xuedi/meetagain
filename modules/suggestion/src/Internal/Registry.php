<?php declare(strict_types=1);

namespace Module\Suggestion\Internal;

use App\Service\Config\PluginService;
use Module\Suggestion\Contract\TargetProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class Registry
{
    private const string CORE_PLUGIN_KEY = '';

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
        private readonly PluginService $pluginService,
    ) {}

    public function has(string $targetType): bool
    {
        return isset($this->getActive()[$targetType]);
    }

    public function providerFor(string $targetType): ?TargetProviderInterface
    {
        return $this->getActive()[$targetType] ?? null;
    }

    /**
     * @return array<string, TargetProviderInterface>
     */
    private function getActive(): array
    {
        if ($this->active !== null) {
            return $this->active;
        }

        $enabledPlugins = $this->pluginService->getGloballyActiveList();
        $map = [];
        foreach ($this->providers as $provider) {
            $pluginKey = $provider->getPluginKey();
            if ($pluginKey !== self::CORE_PLUGIN_KEY && !in_array($pluginKey, $enabledPlugins, true)) {
                continue;
            }

            $map[$provider->getTargetType()] = $provider;
        }

        return $this->active = $map;
    }
}
