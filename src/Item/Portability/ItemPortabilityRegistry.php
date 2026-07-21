<?php declare(strict_types=1);

namespace App\Item\Portability;

use App\Service\Config\PluginService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Unions the portability contributors whose plugin is active and answers keyed lookups.
 * Mirrors CategorizableTypeRegistry's active-plugin guard on the portability axis.
 */
class ItemPortabilityRegistry
{
    /**
     * @var array<string, ItemPortabilityContributorInterface>|null
     */
    private ?array $active = null;

    /**
     * @param iterable<ItemPortabilityContributorInterface> $contributors
     */
    public function __construct(
        #[AutowireIterator(ItemPortabilityContributorInterface::class)]
        private readonly iterable $contributors,
        private readonly PluginService $pluginService,
    ) {}

    /**
     * @return list<ItemPortabilityContributorInterface>
     */
    public function all(): array
    {
        return array_values($this->getActive());
    }

    public function has(string $itemType): bool
    {
        return isset($this->getActive()[$itemType]);
    }

    public function contributorFor(string $itemType): ?ItemPortabilityContributorInterface
    {
        return $this->getActive()[$itemType] ?? null;
    }

    /**
     * @return array<string, ItemPortabilityContributorInterface>
     */
    private function getActive(): array
    {
        if ($this->active !== null) {
            return $this->active;
        }

        $enabledPlugins = $this->pluginService->getActiveList();
        $map = [];
        foreach ($this->contributors as $contributor) {
            if (!in_array($contributor->getPluginKey(), $enabledPlugins, true)) {
                continue;
            }

            $map[$contributor->getItemType()] = $contributor;
        }

        return $this->active = $map;
    }
}
