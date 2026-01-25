<?php declare(strict_types=1);

namespace App\Service\MemberFilter;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composite member filter service that collects all registered MemberFilterInterface implementations.
 * Combines multiple filters using AND logic for user ID restrictions.
 */
readonly class MemberFilterService
{
    /**
     * @param iterable<MemberFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(MemberFilterInterface::class)]
        private iterable $filters,
    ) {
    }

    /**
     * Get the combined user ID filter from all registered filters.
     * Uses intersection (AND) logic: a member must pass ALL filters.
     */
    public function getUserIdFilter(): MemberFilterResult
    {
        $resultSet = null;
        $hasActiveFilter = false;

        foreach ($this->getSortedFilters() as $filter) {
            $filterResult = $filter->getUserIdFilter();

            if ($filterResult === null) {
                continue; // No filtering from this filter
            }

            $hasActiveFilter = true;

            if ($filterResult === []) {
                return MemberFilterResult::emptyResult();
            }

            if ($resultSet === null) {
                $resultSet = $filterResult;
            } else {
                // Intersect: member must pass ALL filters
                $resultSet = array_values(array_intersect($resultSet, $filterResult));
                if ($resultSet === []) {
                    return MemberFilterResult::emptyResult();
                }
            }
        }

        return new MemberFilterResult($resultSet, $hasActiveFilter);
    }

    /**
     * Check if a member is accessible according to all registered filters.
     * Any filter returning false will deny access.
     * Returns true only if all filters allow (or have no opinion).
     */
    public function isMemberAccessible(int $userId): bool
    {
        foreach ($this->getSortedFilters() as $filter) {
            $result = $filter->isMemberAccessible($userId);

            if ($result === false) {
                return false; // Explicit deny
            }
        }

        return true; // All filters allow or have no opinion
    }

    /**
     * @return array<MemberFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort($filters, static fn (MemberFilterInterface $a, MemberFilterInterface $b): int =>
            $b->getPriority() <=> $a->getPriority()
        );

        return $filters;
    }
}
