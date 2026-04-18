<?php declare(strict_types=1);

namespace App\Filter\Event;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composite event filter service that collects all registered EventFilterInterface implementations.
 * Combines multiple filters using AND logic for event ID restrictions.
 */
readonly class EventFilterService
{
    /**
     * @param iterable<EventFilterInterface> $filters
     * @param iterable<UserProfileEventFilterInterface> $userProfileFilters
     */
    public function __construct(
        #[AutowireIterator(EventFilterInterface::class)]
        private iterable $filters,
        #[AutowireIterator(UserProfileEventFilterInterface::class)]
        private iterable $userProfileFilters,
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
                continue; // No filtering from this filter
            }

            $hasActiveFilter = true;

            if ($filterResult === []) {
                return EventFilterResult::emptyResult();
            }

            if ($resultSet === null) {
                $resultSet = $filterResult;
                continue;
            }
            // Intersect: event must pass ALL filters
            $resultSet = array_values(array_intersect($resultSet, $filterResult));
            if ($resultSet === []) {
                return EventFilterResult::emptyResult();
            }
        }

        return new EventFilterResult($resultSet, $hasActiveFilter);
    }

    /**
     * Get the combined event ID filter for a specific user's profile page.
     * Intersects the context filter with any registered user-scoped filters.
     */
    public function getEventIdFilterForUserProfile(User $user): EventFilterResult
    {
        $contextFilter = $this->getEventIdFilter();

        $userResultSet = null;
        $hasUserFilter = false;

        foreach ($this->userProfileFilters as $filter) {
            $filterResult = $filter->getEventIdFilterForUser($user);

            if ($filterResult === null) {
                continue;
            }

            $hasUserFilter = true;

            if ($filterResult === []) {
                return EventFilterResult::emptyResult();
            }

            if ($userResultSet === null) {
                $userResultSet = $filterResult;
                continue;
            }
            $userResultSet = array_values(array_intersect($userResultSet, $filterResult));
            if ($userResultSet === []) {
                return EventFilterResult::emptyResult();
            }
        }

        if (!$hasUserFilter) {
            return $contextFilter;
        }

        // Intersect user-scoped result with the context filter
        $contextIds = $contextFilter->getEventIds();
        if ($contextIds === null) {
            return new EventFilterResult($userResultSet, true);
        }

        $intersection = array_values(array_intersect($contextIds, $userResultSet));

        return new EventFilterResult($intersection, true);
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

        usort(
            $filters,
            static fn(EventFilterInterface $a, EventFilterInterface $b): int => $b->getPriority() <=> $a->getPriority(),
        );

        return $filters;
    }
}
