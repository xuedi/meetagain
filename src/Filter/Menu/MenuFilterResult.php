<?php declare(strict_types=1);

namespace App\Filter\Menu;

/**
 * Value object representing menu filter results.
 */
readonly class MenuFilterResult
{
    /**
     * @param array<int>|null $menuIds Allowed menu IDs (null = no filtering)
     * @param bool $hasActiveFilter Whether any filter is active
     */
    public function __construct(
        public ?array $menuIds,
        public bool $hasActiveFilter,
    ) {}

    public function getMenuIds(): ?array
    {
        return $this->menuIds;
    }

    /**
     * Create result representing "no menus allowed".
     */
    public static function emptyResult(): self
    {
        return new self([], true);
    }
}
