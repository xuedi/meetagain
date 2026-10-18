<?php declare(strict_types=1);

namespace App\Service\Config;

use App\Filter\Plugin\PluginListFilterInterface;
use App\Kernel;
use App\Repository\PluginActivationRepository;
use Doctrine\DBAL\Exception as DbalException;
use LogicException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;

class PluginService implements ResetInterface
{
    public const string NAVIGATION_KEY = 'core_navigation';
    private const string CACHE_KEY = 'plugin_activation';
    private const int CACHE_TTL = 3600;

    /** @var list<string>|null */
    private ?array $globallyActive = null;

    /**
     * @param array<string, array{name: string, version: string, description: string, icon: string, group_activatable: bool, system: bool, requires: list<string>}> $plugins
     * @param iterable<PluginListFilterInterface> $pluginListFilters
     */
    public function __construct(
        #[Autowire(param: Kernel::PLUGINS_PARAMETER)]
        private readonly array $plugins,
        private readonly PluginActivationRepository $activations,
        private readonly CacheInterface $cache,
        #[Autowire(service: 'cache.cms_page_cache')]
        private readonly CacheItemPoolInterface $cmsPageCache,
        #[Autowire(service: 'cache.messenger.restart_workers_signal')]
        private readonly CacheItemPoolInterface $workerRestartSignal,
        private readonly ClockInterface $clock,
        #[AutowireIterator(PluginListFilterInterface::class)]
        private readonly iterable $pluginListFilters = [],
    ) {}

    /**
     * @return list<array{key: string, name: string, version: string, description: string, system: bool, enabled: bool, requires: list<string>}>
     */
    public function getAdminList(): array
    {
        $active = $this->getGloballyActiveList();
        $list = [];
        foreach ($this->plugins as $key => $plugin) {
            $list[] = [
                'key' => $key,
                'name' => $plugin['name'],
                'version' => $plugin['version'],
                'description' => $plugin['description'],
                'system' => $plugin['system'],
                'enabled' => in_array($key, $active, true),
                'requires' => $plugin['requires'],
            ];
        }

        return $list;
    }

    /**
     * @return list<string>
     */
    public function getGloballyActiveList(): array
    {
        if ($this->globallyActive !== null) {
            return $this->globallyActive;
        }

        $state = $this->activationState();
        $active = [];
        foreach ($this->plugins as $key => $plugin) {
            if ($plugin['system'] || ($state === null ? true : $state[$key] ?? false)) {
                $active[] = $key;
            }
        }
        $active[] = self::NAVIGATION_KEY;

        if ($state === null) {
            return $active;
        }

        return $this->globallyActive = $active;
    }

    /**
     * @return array<string>
     */
    public function getActiveList(): array
    {
        $allActive = $this->getGloballyActiveList();

        $activatableKeys = array_column($this->getActivatableByGroupList(), 'key');
        $alwaysOn = array_values(array_diff($allActive, $activatableKeys));
        $groupManaged = array_values(array_intersect($allActive, $activatableKeys));

        foreach ($this->pluginListFilters as $filter) {
            $filtered = $filter->filterActivePlugins($groupManaged);
            if ($filtered !== null) {
                $groupManaged = array_values(array_intersect($groupManaged, $filtered));
            }
        }

        return array_merge($alwaysOn, $groupManaged);
    }

    /**
     * @return array<array{key: string, name: string, description: string, icon: string}>
     */
    public function getActivatableByGroupList(): array
    {
        $globallyActive = $this->getGloballyActiveList();
        $result = [];
        foreach ($this->plugins as $key => $plugin) {
            if (!$plugin['group_activatable'] || !in_array($key, $globallyActive, true)) {
                continue;
            }

            $result[] = [
                'key' => $key,
                'name' => $plugin['name'],
                'description' => $plugin['description'],
                'icon' => $plugin['icon'],
            ];
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public function getInstalledKeys(): array
    {
        return array_keys($this->plugins);
    }

    public function getName(string $pluginKey): string
    {
        return $this->plugins[$pluginKey]['name'] ?? $pluginKey;
    }

    public function isInstalled(string $pluginKey): bool
    {
        return isset($this->plugins[$pluginKey]);
    }

    public function isSystem(string $pluginKey): bool
    {
        return $this->plugins[$pluginKey]['system'] ?? false;
    }

    public function isEnabled(string $pluginKey): bool
    {
        return in_array($pluginKey, $this->getGloballyActiveList(), true);
    }

    /**
     * @return list<string> the plugins this one requires that are switched off
     */
    public function missingRequirements(string $pluginKey): array
    {
        return array_values(array_filter($this->plugins[$pluginKey]['requires'] ?? [], fn(string $required): bool => !$this->isEnabled($required)));
    }

    /**
     * @return list<string> the switched-on plugins that require this one
     */
    public function activeDependents(string $pluginKey): array
    {
        $dependents = [];
        foreach ($this->plugins as $key => $plugin) {
            if (in_array($pluginKey, $plugin['requires'], true) && $this->isEnabled($key)) {
                $dependents[] = $key;
            }
        }

        return $dependents;
    }

    public function toggle(string $pluginKey, bool $enabled): void
    {
        if (!$this->isInstalled($pluginKey) || $this->isSystem($pluginKey)) {
            throw new LogicException(sprintf('Plugin "%s" is not installed or cannot be switched.', $pluginKey));
        }
        $blockers = $enabled ? $this->missingRequirements($pluginKey) : $this->activeDependents($pluginKey);
        if ($blockers !== []) {
            throw new LogicException(sprintf(
                'Plugin "%s" cannot be switched %s because of: %s.',
                $pluginKey,
                $enabled ? 'on' : 'off',
                implode(', ', $blockers),
            ));
        }

        $this->activations->save($pluginKey, $enabled, $this->clock->now());
        $this->changed();
    }

    /**
     * @param array<string, bool> $state
     */
    public function adopt(array $state): void
    {
        foreach ($state as $pluginKey => $enabled) {
            if ($this->isInstalled($pluginKey) && !$this->isSystem($pluginKey)) {
                $this->activations->save($pluginKey, $enabled, $this->clock->now());
            }
        }
        $this->changed();
    }

    public function hasState(): bool
    {
        return $this->activationState() !== [];
    }

    public function reset(): void
    {
        $this->globallyActive = null;
    }

    private function changed(): void
    {
        $this->cache->delete(self::CACHE_KEY);
        $this->cmsPageCache->clear();
        $restart = $this->workerRestartSignal->getItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY);
        $this->workerRestartSignal->save($restart->set((float) $this->clock->now()->format('U.u')));
        $this->reset();
    }

    /**
     * @return array<string, bool>|null null while the table cannot be read, as during an image build or a first migration
     */
    private function activationState(): ?array
    {
        try {
            return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
                $item->expiresAfter(self::CACHE_TTL);

                return $this->activations->stateByKey();
            });
        } catch (DbalException) {
            return null;
        }
    }
}
