<?php declare(strict_types=1);

namespace App\DependencyInjection;

use App\Service\Config\PluginService;
use Closure;
use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/**
 * @template T of object
 *
 * @implements IteratorAggregate<array-key, T>
 */
final readonly class ActivePluginMembers implements IteratorAggregate, Countable
{
    /**
     * @param iterable<array-key, T> $services
     * @param array<array-key, string> $pluginByKey the plugin of each member a plugin contributes, by its iterator key
     * @param Closure(): PluginService $plugins
     */
    public function __construct(
        private iterable $services,
        private array $pluginByKey,
        private Closure $plugins,
    ) {}

    #[Override]
    public function getIterator(): Traversable
    {
        $active = ($this->plugins)()->getGloballyActiveList();
        foreach ($this->services as $key => $service) {
            $plugin = $this->pluginByKey[$key] ?? null;
            if ($plugin === null || in_array($plugin, $active, true)) {
                yield $key => $service;
            }
        }
    }

    #[Override]
    public function count(): int
    {
        return iterator_count($this->getIterator());
    }
}
