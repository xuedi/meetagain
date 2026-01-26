<?php declare(strict_types=1);

namespace App\Filter\Menu;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for menu filtering services.
 * Multiple filters can be registered and will be combined using AND logic.
 */
#[AutoconfigureTag]
interface MenuFilterInterface
{
    /**
     * Get priority for filter ordering (higher = runs first).
     */
    public function getPriority(): int;

    /**
     * Get allowed menu IDs for current context.
     *
     * @return array<int>|null null = no filtering, [] = no menus, [1,2,3] = specific menus
     */
    public function getMenuIdFilter(): ?array;

    /**
     * Check if a specific menu is accessible in current context.
     *
     * @return bool|null true = accessible, false = not accessible, null = no opinion
     */
    public function isMenuAccessible(int $menuId): ?bool;
}
