<?php declare(strict_types=1);

namespace App\Filter\Admin\Event;

use App\Filter\Event\EventFilterResult;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composite admin event list filter service.
 * Collects all registered AdminEventListFilterInterface implementations.
 * Combines multiple filters using AND logic for event ID restrictions.
 */
readonly class AdminEventListFilterService
{
    /**
     * @param iterable<AdminEventListFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(AdminEventListFilterInterface::class)]
        private iterable $filters,
    ) {}

    /**
     * Get the combined event ID filter from all registered filters.
     * Uses intersection (AND) logic: an event must pass ALL filters.
     */
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

    /**
     * Check if an event is accessible according to all registered filters.
     * Any filter returning false will deny access.
     * Returns true only if all filters allow (or have no opinion).
     */
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
     * Get combined debug context from all registered filters.
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

        usort(
            $filters,
            static fn(
                AdminEventListFilterInterface $a,
                AdminEventListFilterInterface $b,
            ): int => $b->getPriority() <=> $a->getPriority(),
        );

        return $filters;
    }
}
