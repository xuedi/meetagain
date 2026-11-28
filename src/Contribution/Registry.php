<?php declare(strict_types=1);

namespace App\Contribution;

use App\Entity\User;
use App\Service\Config\PluginService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\ResetInterface;

// Gated on getActiveList(), not getGloballyActiveList(): the correction forms are a UI surface, so a
// provider follows whether its plugin is on for this request
class Registry implements ResetInterface
{
    private const string CORE_PLUGIN_KEY = '';

    /**
     * @var list<TargetProviderInterface>|null
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

    /**
     * @return list<TargetProviderInterface>
     */
    public function all(): array
    {
        if ($this->active !== null) {
            return $this->active;
        }

        $enabledPlugins = $this->pluginService->getActiveList();
        $active = [];
        foreach ($this->providers as $provider) {
            $pluginKey = $provider->getPluginKey();
            if ($pluginKey !== self::CORE_PLUGIN_KEY && !in_array($pluginKey, $enabledPlugins, true)) {
                continue;
            }

            $active[] = $provider;
        }

        return $this->active = $active;
    }

    public function has(string $type): bool
    {
        return $this->providerFor($type) !== null;
    }

    public function providerFor(string $type): ?TargetProviderInterface
    {
        foreach ($this->all() as $provider) {
            if ($provider->getType() === $type) {
                return $provider;
            }
        }

        return null;
    }

    public function mayTouch(string $type, User $user, int|string $id): bool
    {
        return $this->providerFor($type)?->mayTouch($user, $id) ?? false;
    }

    public function reset(): void
    {
        $this->active = null;
    }
}
