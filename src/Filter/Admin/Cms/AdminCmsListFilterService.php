<?php declare(strict_types=1);

namespace App\Filter\Admin\Cms;

use App\Filter\Cms\CmsFilterResult;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class AdminCmsListFilterService
{
    /**
     * @param iterable<AdminCmsListFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(AdminCmsListFilterInterface::class)]
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
     * @return array<string, mixed>
     */
    public function getDebugContext(int $cmsId): array
    {
        $context = [];

        foreach ($this->getSortedFilters() as $filter) {
            $filterContext = $filter->getDebugContext($cmsId);
            if ($filterContext !== []) {
                $context[get_class($filter)] = $filterContext;
            }
        }

        return $context;
    }

    /**
     * @return array<AdminCmsListFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort($filters, static fn(AdminCmsListFilterInterface $a, AdminCmsListFilterInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $filters;
    }
}
