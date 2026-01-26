<?php declare(strict_types=1);

namespace App\Filter\Member;

/**
 * Result from composing multiple member filters.
 */
readonly class MemberFilterResult
{
    /**
     * @param array<int>|null $userIds Restricted user IDs, or null for no restriction
     * @param bool $hasActiveFilter Whether any filter is actively restricting
     */
    public function __construct(
        private ?array $userIds,
        private bool $hasActiveFilter,
    ) {}

    /**
     * @return array<int>|null
     */
    public function getUserIds(): ?array
    {
        return $this->userIds;
    }

    public function hasActiveFilter(): bool
    {
        return $this->hasActiveFilter;
    }

    public function isEmpty(): bool
    {
        return $this->userIds === [];
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
