<?php declare(strict_types=1);

namespace App\Filter\Admin\Location;

use App\Filter\Location\LocationFilterResult;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class AdminLocationListFilterService
{
    /**
     * @param iterable<AdminLocationListFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(AdminLocationListFilterInterface::class)]
        private iterable $filters,
    ) {}

    public function getLocationIdFilter(): LocationFilterResult
    {
        $resultSet = null;
        $hasActiveFilter = false;

        foreach ($this->getSortedFilters() as $filter) {
            $filterResult = $filter->getLocationIdFilter();

            if ($filterResult === null) {
                continue;
            }

            $hasActiveFilter = true;

            if ($filterResult === []) {
                return LocationFilterResult::emptyResult();
            }

            if ($resultSet === null) {
                $resultSet = $filterResult;
                continue;
            }
            $resultSet = array_values(array_intersect($resultSet, $filterResult));
            if ($resultSet === []) {
                return LocationFilterResult::emptyResult();
            }
        }

        return new LocationFilterResult($resultSet, $hasActiveFilter);
    }

    public function isLocationAccessible(int $locationId): bool
    {
        foreach ($this->getSortedFilters() as $filter) {
            $result = $filter->isLocationAccessible($locationId);

            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDebugContext(int $locationId): array
    {
        $context = [];

        foreach ($this->getSortedFilters() as $filter) {
            $filterContext = $filter->getDebugContext($locationId);
            if ($filterContext !== []) {
                $context[get_class($filter)] = $filterContext;
            }
        }

        return $context;
    }

    /**
     * @return array<AdminLocationListFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort($filters, static fn(AdminLocationListFilterInterface $a, AdminLocationListFilterInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $filters;
    }
}
