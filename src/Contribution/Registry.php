<?php declare(strict_types=1);

namespace App\Contribution;

use App\Entity\User;
use App\Service\Config\PluginService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

// Gated on getActiveList(), not getGloballyActiveList(): the hub is a UI surface, so a section
// follows whether its plugin is on for this request
class Registry
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
        private readonly ScopeFilterService $scope,
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

    /**
     * @return list<Section>
     */
    public function sectionsFor(User $user): array
    {
        $sections = [];
        foreach ($this->all() as $provider) {
            $sections[] = new Section($provider->getType(), $provider->getLabelKey(), $provider->getIcon(), $this->entriesFor($provider->getType(), $user));
        }

        return $sections;
    }

    /**
     * @return list<Entry>
     */
    public function entriesFor(string $type, User $user): array
    {
        $provider = $this->providerFor($type);
        if ($provider === null) {
            return [];
        }

        $entries = $provider->listForMember($user);
        $reachable = array_fill_keys($this->scope->narrow($type, array_map(static fn(Entry $entry): int|string => $entry->id, $entries), $user), true);

        return array_values(array_filter($entries, static fn(Entry $entry): bool => isset($reachable[$entry->id])));
    }

    public function mayTouch(string $type, User $user, int|string $id): bool
    {
        return $this->providerFor($type)?->mayTouch($user, $id) ?? false;
    }
}
