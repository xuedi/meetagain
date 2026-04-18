<?php declare(strict_types=1);

namespace App\Filter\Admin\Host;

use App\Filter\Host\HostFilterResult;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composite admin host list filter service.
 * Collects all registered AdminHostListFilterInterface implementations.
 * Combines multiple filters using AND logic for host ID restrictions.
 */
readonly class AdminHostListFilterService
{
    /**
     * @param iterable<AdminHostListFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(AdminHostListFilterInterface::class)]
        private iterable $filters,
    ) {}

    /**
     * Get the combined host ID filter from all registered filters.
     * Uses intersection (AND) logic: a host must pass ALL filters.
     */
    public function getHostIdFilter(): HostFilterResult
    {
        $resultSet = null;
        $hasActiveFilter = false;

        foreach ($this->getSortedFilters() as $filter) {
            $filterResult = $filter->getHostIdFilter();

            if ($filterResult === null) {
                continue;
            }

            $hasActiveFilter = true;

            if ($filterResult === []) {
                return HostFilterResult::emptyResult();
            }

            if ($resultSet === null) {
                $resultSet = $filterResult;
                continue;
            }
            $resultSet = array_values(array_intersect($resultSet, $filterResult));
            if ($resultSet === []) {
                return HostFilterResult::emptyResult();
            }
        }

        return new HostFilterResult($resultSet, $hasActiveFilter);
    }

    /**
     * Check if a host is accessible according to all registered filters.
     * Any filter returning false will deny access.
     * Returns true only if all filters allow (or have no opinion).
     */
    public function isHostAccessible(int $hostId): bool
    {
        foreach ($this->getSortedFilters() as $filter) {
            $result = $filter->isHostAccessible($hostId);

            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get combined debug context from all registered filters.
     * @return array<string, mixed>
     */
    public function getDebugContext(int $hostId): array
    {
        $context = [];

        foreach ($this->getSortedFilters() as $filter) {
            $filterContext = $filter->getDebugContext($hostId);
            if ($filterContext !== []) {
                $context[get_class($filter)] = $filterContext;
            }
        }

        return $context;
    }

    /**
     * @return array<AdminHostListFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort(
            $filters,
            static fn(
                AdminHostListFilterInterface $a,
                AdminHostListFilterInterface $b,
            ): int => $b->getPriority() <=> $a->getPriority(),
        );

        return $filters;
    }
}
