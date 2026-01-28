<?php declare(strict_types=1);

namespace App\AdminModules;

use App\Entity\AdminLink;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for admin modules.
 * Each module provides a self-contained admin feature.
 */
#[AutoconfigureTag]
interface AdminModuleInterface
{
    /**
     * Unique identifier for this module.
     */
    public function getKey(): string;

    /**
     * Priority for ordering in sidebar (higher = earlier).
     */
    public function getPriority(): int;

    /**
     * Section name for sidebar grouping (e.g., "System", "Tables", "CMS").
     * Modules with the same section name are grouped together.
     */
    public function getSectionName(): string;

    /**
     * Links to show in the sidebar.
     *
     * @return list<AdminLink>
     */
    public function getLinks(): array;

    /**
     * Route definitions for this module.
     *
     * @return array<array{name: string, path: string, controller: array, methods?: string[]}>
     */
    public function getRoutes(): array;

    /**
     * Check if the current user can access this module.
     */
    public function isAccessible(): bool;
}
