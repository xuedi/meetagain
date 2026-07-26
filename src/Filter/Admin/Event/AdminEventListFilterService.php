<?php declare(strict_types=1);

namespace App\Filter\Admin\Event;

use App\Filter\Event\EventFilterResult;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class AdminEventListFilterService
{
    /**
     * @param iterable<AdminEventListFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(AdminEventListFilterInterface::class)]
        private iterable $filters,
    ) {}

    public function getEventIdFilter(): EventFilterResult
    {
        $resultSet = null;
        $hasActiveFilter = false;

        foreach ($this->getSortedFilters() as $filter) {
            $filterResult = $filter->getEventIdFilter();

            if ($filterResult === null) {
                continue;
            }

            $hasActiveFilter = true;

            if ($filterResult === []) {
                return EventFilterResult::emptyResult();
            }

            if ($resultSet === null) {
                $resultSet = $filterResult;
                continue;
            }
            $resultSet = array_values(array_intersect($resultSet, $filterResult));
            if ($resultSet === []) {
                return EventFilterResult::emptyResult();
            }
        }

        return new EventFilterResult($resultSet, $hasActiveFilter);
    }

    public function isEventAccessible(int $eventId): bool
    {
        foreach ($this->getSortedFilters() as $filter) {
            $result = $filter->isEventAccessible($eventId);

            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDebugContext(int $eventId): array
    {
        $context = [];

        foreach ($this->getSortedFilters() as $filter) {
            $filterContext = $filter->getDebugContext($eventId);
            if ($filterContext !== []) {
                $context[get_class($filter)] = $filterContext;
            }
        }

        return $context;
    }

    /**
     * @return array<AdminEventListFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort($filters, static fn(AdminEventListFilterInterface $a, AdminEventListFilterInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $filters;
    }
}
