<?php declare(strict_types=1);

namespace App\Filter\Location;

/**
 * Result from composing multiple location filters.
 */
readonly class LocationFilterResult
{
    /**
     * @param array<int>|null $locationIds Restricted location IDs, or null for no restriction
     * @param bool $hasActiveFilter Whether any filter is actively restricting
     */
    public function __construct(
        private ?array $locationIds,
        private bool $hasActiveFilter,
    ) {}

    /**
     * @return array<int>|null
     */
    public function getLocationIds(): ?array
    {
        return $this->locationIds;
    }

    public function hasActiveFilter(): bool
    {
        return $this->hasActiveFilter;
    }

    public function isEmpty(): bool
    {
        return $this->locationIds === [];
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
