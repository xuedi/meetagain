<?php declare(strict_types=1);

namespace App\Circulation;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class DashboardTabRegistry
{
    /**
     * @param iterable<DashboardTabInterface> $tabs
     */
    public function __construct(
        #[AutowireIterator(DashboardTabInterface::class)]
        private iterable $tabs,
    ) {}

    /**
     * @return list<DashboardTabInterface>
     */
    public function forType(string $itemType, string $context): array
    {
        $supported = [];
        foreach ($this->tabs as $tab) {
            if (!$tab->supports($itemType, $context)) {
                continue;
            }

            $supported[] = $tab;
        }

        usort($supported, static fn(DashboardTabInterface $a, DashboardTabInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $supported;
    }

    public function get(string $itemType, string $context, string $key): ?DashboardTabInterface
    {
        foreach ($this->forType($itemType, $context) as $tab) {
            if ($tab->getKey() === $key) {
                return $tab;
            }
        }

        return null;
    }
}
