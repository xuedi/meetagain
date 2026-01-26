<?php declare(strict_types=1);

namespace App\Service\CmsFilter;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composite CMS filter service that collects all registered CmsFilterInterface implementations.
 * Combines multiple filters using AND logic for CMS page ID restrictions.
 */
readonly class CmsFilterService
{
    /**
     * @param iterable<CmsFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(CmsFilterInterface::class)]
        private iterable $filters,
    ) {
    }

    /**
     * Get the combined CMS ID filter from all registered filters.
     * Uses intersection (AND) logic: a CMS page must pass ALL filters.
     */
    public function getCmsIdFilter(): CmsFilterResult
    {
        $resultSet = null;
        $hasActiveFilter = false;

        foreach ($this->getSortedFilters() as $filter) {
            $filterResult = $filter->getCmsIdFilter();

            if ($filterResult === null) {
                continue; // No filtering from this filter
            }

            $hasActiveFilter = true;

            if ($filterResult === []) {
                return CmsFilterResult::emptyResult();
            }

            if ($resultSet === null) {
                $resultSet = $filterResult;
            } else {
                // Intersect: CMS page must pass ALL filters
                $resultSet = array_values(array_intersect($resultSet, $filterResult));
                if ($resultSet === []) {
                    return CmsFilterResult::emptyResult();
                }
            }
        }

        return new CmsFilterResult($resultSet, $hasActiveFilter);
    }

    /**
     * Check if a CMS page is accessible according to all registered filters.
     * Any filter returning false will deny access.
     * Returns true only if all filters allow (or have no opinion).
     */
    public function isCmsAccessible(int $cmsId): bool
    {
        foreach ($this->getSortedFilters() as $filter) {
            $result = $filter->isCmsAccessible($cmsId);

            if ($result === false) {
                return false; // Explicit deny
            }
        }

        return true; // All filters allow or have no opinion
    }

    /**
     * @return array<CmsFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort($filters, static fn (CmsFilterInterface $a, CmsFilterInterface $b): int =>
            $b->getPriority() <=> $a->getPriority()
        );

        return $filters;
    }
}
