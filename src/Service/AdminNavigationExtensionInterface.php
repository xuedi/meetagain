<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\AdminSection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for plugins to add admin navigation sections.
 *
 * Plugins implement this interface to extend the admin sidebar navigation
 * with dynamic sections. The core AdminNavigationService automatically
 * collects and merges all implementations using autowiring.
 *
 * Example:
 * ```php
 * #[AsService]
 * readonly class MyPluginAdminNavigation implements AdminNavigationExtensionInterface
 * {
 *     public function getPriority(): int
 *     {
 *         return 300; // Higher = appears first
 *     }
 *
 *     public function getAdminSections(): array
 *     {
 *         return [
 *             new AdminSection(
 *                 section: 'My Plugin',
 *                 links: [
 *                     new AdminLink('Dashboard', 'app_plugin_dashboard', 'dashboard'),
 *                     new AdminLink('Settings', 'app_plugin_settings', 'settings'),
 *                 ],
 *             ),
 *         ];
 *     }
 * }
 * ```
 */
#[AutoconfigureTag]
interface AdminNavigationExtensionInterface
{
    /**
     * Get the priority for section ordering.
     * Higher values appear first in the navigation.
     *
     * Recommended ranges:
     * - 500+ : Critical system sections
     * - 400-499: Core admin sections
     * - 300-399: Plugin sections
     * - 200-299: Less important sections
     */
    public function getPriority(): int;

    /**
     * Get admin sections to add to the navigation.
     *
     * This method is called on every admin page load to build the sidebar.
     * Keep logic lightweight and consider caching if doing expensive checks.
     *
     * @return list<AdminSection>
     */
    public function getAdminSections(): array;
}
