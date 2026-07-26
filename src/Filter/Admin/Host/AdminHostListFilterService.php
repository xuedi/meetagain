<?php declare(strict_types=1);

namespace App\Filter\Admin\Host;

use App\Filter\Host\HostFilterResult;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class AdminHostListFilterService
{
    /**
     * @param iterable<AdminHostListFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(AdminHostListFilterInterface::class)]
        private iterable $filters,
    ) {}

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

        usort($filters, static fn(AdminHostListFilterInterface $a, AdminHostListFilterInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $filters;
    }
}
