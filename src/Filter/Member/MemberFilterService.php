<?php declare(strict_types=1);

namespace App\Filter\Member;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class MemberFilterService
{
    /**
     * @param iterable<MemberFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(MemberFilterInterface::class)]
        private iterable $filters,
    ) {}

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
     * @return array<MemberFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort($filters, static fn(MemberFilterInterface $a, MemberFilterInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $filters;
    }
}
