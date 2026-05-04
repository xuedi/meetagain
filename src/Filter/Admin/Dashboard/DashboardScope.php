<?php declare(strict_types=1);

namespace App\Filter\Admin\Dashboard;

/**
 * Resolved dashboard data scope after intersecting all registered filters.
 *
 * Three states:
 *   - platform-wide: every filter returned null. `eventIds()` and `userIds()` return null.
 *   - empty:         at least one filter returned []. `isEmpty()` is true.
 *   - restricted:    the intersection of all filter results.
 */
final readonly class DashboardScope
{
    /**
     * @param array<int>|null $eventIds
     * @param array<int>|null $userIds
     */
    public function __construct(
        private ?array $eventIds,
        private ?array $userIds,
    ) {}

    public static function platformWide(): self
    {
        return new self(null, null);
    }

    public function isPlatformWide(): bool
    {
        return $this->eventIds === null && $this->userIds === null;
    }

    public function isEmpty(): bool
    {
        return $this->eventIds === [] || $this->userIds === [];
    }

    /**
     * @return array<int>|null
     */
    public function eventIds(): ?array
    {
        return $this->eventIds;
    }

    /**
     * @return array<int>|null
     */
    public function userIds(): ?array
    {
        return $this->userIds;
    }
}
