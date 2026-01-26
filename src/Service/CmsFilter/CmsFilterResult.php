<?php declare(strict_types=1);

namespace App\Service\CmsFilter;

/**
 * Result from composing multiple CMS page filters.
 */
readonly class CmsFilterResult
{
    /**
     * @param array<int>|null $cmsIds Restricted CMS page IDs, or null for no restriction
     * @param bool $hasActiveFilter Whether any filter is actively restricting
     */
    public function __construct(
        private ?array $cmsIds,
        private bool $hasActiveFilter,
    ) {
    }

    /**
     * @return array<int>|null
     */
    public function getCmsIds(): ?array
    {
        return $this->cmsIds;
    }

    public function hasActiveFilter(): bool
    {
        return $this->hasActiveFilter;
    }

    public function isEmpty(): bool
    {
        return $this->cmsIds === [];
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
