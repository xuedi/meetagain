<?php declare(strict_types=1);

namespace App\Filter\Host;

/**
 * Result from composing multiple host filters.
 */
readonly class HostFilterResult
{
    /**
     * @param array<int>|null $hostIds Restricted host IDs, or null for no restriction
     * @param bool $hasActiveFilter Whether any filter is actively restricting
     */
    public function __construct(
        private ?array $hostIds,
        private bool $hasActiveFilter,
    ) {}

    /**
     * @return array<int>|null
     */
    public function getHostIds(): ?array
    {
        return $this->hostIds;
    }

    public function hasActiveFilter(): bool
    {
        return $this->hasActiveFilter;
    }

    public function isEmpty(): bool
    {
        return $this->hostIds === [];
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
