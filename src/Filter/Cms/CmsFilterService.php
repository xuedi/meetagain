<?php declare(strict_types=1);

namespace App\Filter\Cms;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class CmsFilterService
{
    /**
     * @param iterable<CmsFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(CmsFilterInterface::class)]
        private iterable $filters,
    ) {}

    public function getCmsIdFilter(): CmsFilterResult
    {
        $resultSet = null;
        $hasActiveFilter = false;

        foreach ($this->getSortedFilters() as $filter) {
            $filterResult = $filter->getCmsIdFilter();

            if ($filterResult === null) {
                continue;
            }

            $hasActiveFilter = true;

            if ($filterResult === []) {
                return CmsFilterResult::emptyResult();
            }

            if ($resultSet === null) {
                $resultSet = $filterResult;
                continue;
            }
            $resultSet = array_values(array_intersect($resultSet, $filterResult));
            if ($resultSet === []) {
                return CmsFilterResult::emptyResult();
            }
        }

        return new CmsFilterResult($resultSet, $hasActiveFilter);
    }

    public function isCmsAccessible(int $cmsId): bool
    {
        foreach ($this->getSortedFilters() as $filter) {
            $result = $filter->isCmsAccessible($cmsId);

            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<CmsFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort($filters, static fn(CmsFilterInterface $a, CmsFilterInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $filters;
    }
}
