<?php declare(strict_types=1);

namespace App\Filter\Menu;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composite menu filter service that combines multiple filters.
 * Filters are combined with AND logic (intersection of allowed IDs).
 */
readonly class MenuFilterService
{
    /**
     * @param iterable<MenuFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(MenuFilterInterface::class)]
        private iterable $filters,
    ) {
    }

    /**
     * Get combined menu ID filter from all registered filters.
     */
    public function getMenuIdFilter(): MenuFilterResult
    {
        // Collect filters ordered by priority (highest first)
        $orderedFilters = iterator_to_array($this->filters);
        usort($orderedFilters, static fn(MenuFilterInterface $a, MenuFilterInterface $b) => $b->getPriority() <=> $a->getPriority());

        $allowedIds = null;
        $hasActiveFilter = false;

        foreach ($orderedFilters as $filter) {
            $filterIds = $filter->getMenuIdFilter();

            if ($filterIds === null) {
                continue; // Filter has no opinion
            }

            $hasActiveFilter = true;

            if ($filterIds === []) {
                // Empty filter = no menus allowed
                return MenuFilterResult::emptyResult();
            }

            // Intersect with previous filters (AND logic)
            if ($allowedIds === null) {
                $allowedIds = $filterIds;
            } else {
                $allowedIds = array_values(array_intersect($allowedIds, $filterIds));
                if ($allowedIds === []) {
                    return MenuFilterResult::emptyResult();
                }
            }
        }

        return new MenuFilterResult($allowedIds, $hasActiveFilter);
    }

    /**
     * Check if a specific menu is accessible according to all filters.
     */
    public function isMenuAccessible(int $menuId): bool
    {
        // Collect filters ordered by priority (highest first)
        $orderedFilters = iterator_to_array($this->filters);
        usort($orderedFilters, static fn(MenuFilterInterface $a, MenuFilterInterface $b) => $b->getPriority() <=> $a->getPriority());

        foreach ($orderedFilters as $filter) {
            $accessible = $filter->isMenuAccessible($menuId);

            if ($accessible === false) {
                return false; // Any filter saying "no" blocks access
            }
        }

        return true; // All filters say "yes" or "no opinion"
    }
}
