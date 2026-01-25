<?php declare(strict_types=1);

namespace App\Service\EventFilter;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composite event filter service that collects all registered EventFilterInterface implementations.
 * Combines multiple filters using AND logic for event ID restrictions.
 */
readonly class EventFilterService
{
    /**
     * @param iterable<EventFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(EventFilterInterface::class)]
        private iterable $filters,
    ) {
    }

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
                continue; // No filtering from this filter
            }

            $hasActiveFilter = true;

            if ($filterResult === []) {
                return EventFilterResult::emptyResult();
            }

            if ($resultSet === null) {
                $resultSet = $filterResult;
            } else {
                // Intersect: event must pass ALL filters
                $resultSet = array_values(array_intersect($resultSet, $filterResult));
                if ($resultSet === []) {
                    return EventFilterResult::emptyResult();
                }
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
                return false; // Explicit deny
            }
        }

        return true; // All filters allow or have no opinion
    }

    /**
     * @return array<EventFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort($filters, static fn (EventFilterInterface $a, EventFilterInterface $b): int =>
            $b->getPriority() <=> $a->getPriority()
        );

        return $filters;
    }
}
