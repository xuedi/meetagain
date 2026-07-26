<?php declare(strict_types=1);

namespace App\Item\Portability;

use App\Service\Config\PluginService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class Registry
{
    /**
     * @var array<string, ContributorInterface>|null
     */
    private ?array $active = null;

    /**
     * @param iterable<ContributorInterface> $contributors
     */
    public function __construct(
        #[AutowireIterator(ContributorInterface::class)]
        private readonly iterable $contributors,
        private readonly PluginService $pluginService,
    ) {}

    /**
     * @return list<ContributorInterface>
     */
    public function all(): array
    {
        return array_values($this->getActive());
    }

    public function has(string $itemType): bool
    {
        return isset($this->getActive()[$itemType]);
    }

    public function contributorFor(string $itemType): ?ContributorInterface
    {
        return $this->getActive()[$itemType] ?? null;
    }

    /**
     * @return array<string, ContributorInterface>
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
