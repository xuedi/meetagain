<?php declare(strict_types=1);

namespace App\Filter\Admin\Dashboard;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composes all registered DashboardScopeFilterInterface implementations into a single
 * DashboardScope value object. Higher priority filters are intersected first.
 *
 * - All filters return null  -> platform-wide scope
 * - Any filter returns []    -> empty scope
 * - Otherwise                -> intersection of every non-null result
 */
readonly class DashboardScopeFilterService
{
    /**
     * @param iterable<DashboardScopeFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(DashboardScopeFilterInterface::class)]
        private iterable $filters,
    ) {}

    public function resolveScope(): DashboardScope
    {
        $sortedFilters = $this->getSortedFilters();
        $eventIds = $this->intersect($sortedFilters, static fn(DashboardScopeFilterInterface $f) => $f->getEventIdFilter());
        $userIds = $this->intersect($sortedFilters, static fn(DashboardScopeFilterInterface $f) => $f->getUserIdFilter());

        return new DashboardScope($eventIds, $userIds);
    }

    /**
     * @param array<DashboardScopeFilterInterface> $filters
     * @param callable(DashboardScopeFilterInterface): ?array<int> $extractor
     * @return array<int>|null
     */
    private function intersect(array $filters, callable $extractor): ?array
    {
        $result = null;

        foreach ($filters as $filter) {
            $current = $extractor($filter);
            if ($current === null) {
                continue;
            }
            if ($current === []) {
                return [];
            }
            if ($result === null) {
                $result = array_values($current);
                continue;
            }
            $result = array_values(array_intersect($result, $current));
            if ($result === []) {
                return [];
            }
        }

        return $result;
    }

    /**
     * @return array<DashboardScopeFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort($filters, static fn(DashboardScopeFilterInterface $a, DashboardScopeFilterInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $filters;
    }
}
