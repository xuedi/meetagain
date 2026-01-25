<?php declare(strict_types=1);

namespace App\Service\EventFilter;

/**
 * Result from composing multiple event filters.
 */
readonly class EventFilterResult
{
    /**
     * @param array<int>|null $eventIds Restricted event IDs, or null for no restriction
     * @param bool $hasActiveFilter Whether any filter is actively restricting
     */
    public function __construct(
        private ?array $eventIds,
        private bool $hasActiveFilter,
    ) {
    }

    /**
     * @return array<int>|null
     */
    public function getEventIds(): ?array
    {
        return $this->eventIds;
    }

    public function hasActiveFilter(): bool
    {
        return $this->hasActiveFilter;
    }

    public function isEmpty(): bool
    {
        return $this->eventIds === [];
    }

    public static function noFilter(): self
    {
        return new self(null, false);
    }

    public static function emptyResult(): self
    {
        return new self([], true);
    }
}
