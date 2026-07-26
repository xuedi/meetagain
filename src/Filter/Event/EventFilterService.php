<?php declare(strict_types=1);

namespace App\Filter\Event;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

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

        $contextIds = $contextFilter->getEventIds();
        if ($contextIds === null) {
            return new EventFilterResult($userResultSet, true);
        }

        $intersection = array_values(array_intersect($contextIds, $userResultSet));

        return new EventFilterResult($intersection, true);
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
     * @param int[] $eventIds
     * @return int[]
     */
    public function getAccessibleEventIds(array $eventIds): array
    {
        $accessible = array_values($eventIds);

        foreach ($this->getSortedFilters() as $filter) {
            if ($accessible === []) {
                return [];
            }

            $filterResult = $filter->narrowAccessibleEventIds($accessible);
            if ($filterResult === null) {
                continue;
            }

            $accessible = array_values(array_intersect($accessible, $filterResult));
        }

        return $accessible;
    }

    /**
     * @return array<EventFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort($filters, static fn(EventFilterInterface $a, EventFilterInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $filters;
    }
}
