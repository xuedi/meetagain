<?php declare(strict_types=1);

namespace App\Filter\Admin\Member;

use App\Filter\Member\MemberFilterResult;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composite admin member list filter service.
 * Collects all registered AdminMemberListFilterInterface implementations.
 * Combines multiple filters using AND logic for user ID restrictions.
 */
readonly class AdminMemberListFilterService
{
    /**
     * @param iterable<AdminMemberListFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(AdminMemberListFilterInterface::class)]
        private iterable $filters,
    ) {}

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
                continue;
            }

            $hasActiveFilter = true;

            if ($filterResult === []) {
                return MemberFilterResult::emptyResult();
            }

            if ($resultSet === null) {
                $resultSet = $filterResult;
                continue;
            }
            $resultSet = array_values(array_intersect($resultSet, $filterResult));
            if ($resultSet === []) {
                return MemberFilterResult::emptyResult();
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
                return false;
            }
        }

        return true;
    }

    /**
     * Get combined debug context from all registered filters.
     * @return array<string, mixed>
     */
    public function getDebugContext(int $userId): array
    {
        $context = [];

        foreach ($this->getSortedFilters() as $filter) {
            $filterContext = $filter->getDebugContext($userId);
            if ($filterContext !== []) {
                $context[get_class($filter)] = $filterContext;
            }
        }

        return $context;
    }

    /**
     * @return array<AdminMemberListFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort(
            $filters,
            static fn(
                AdminMemberListFilterInterface $a,
                AdminMemberListFilterInterface $b,
            ): int => $b->getPriority() <=> $a->getPriority(),
        );

        return $filters;
    }
}
