<?php declare(strict_types=1);

namespace App\Filter\Admin\Cms;

use App\Filter\Cms\CmsFilterResult;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composite admin CMS list filter service.
 * Collects all registered AdminCmsListFilterInterface implementations.
 * Combines multiple filters using AND logic for CMS page ID restrictions.
 */
readonly class AdminCmsListFilterService
{
    /**
     * @param iterable<AdminCmsListFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(AdminCmsListFilterInterface::class)]
        private iterable $filters,
    ) {}

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
                return false;
            }
        }

        return true;
    }

    /**
     * Get combined debug context from all registered filters.
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

        usort(
            $filters,
            static fn(
                AdminCmsListFilterInterface $a,
                AdminCmsListFilterInterface $b,
            ): int => $b->getPriority() <=> $a->getPriority(),
        );

        return $filters;
    }
}
