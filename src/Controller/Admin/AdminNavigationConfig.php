<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminLink;

/**
 * Configuration for a controller's admin navigation entry.
 *
 * Defines where and how a controller appears in the admin sidebar navigation.
 * Sections and links are sorted alphabetically.
 *
 * Role Filtering:
 * - sectionRole: Hides entire section if user lacks this role
 * - AdminLink role: Filters individual links within the section
 *
 * Link Modification Feature:
 * The optional $modifies parameter allows controllers to modify existing navigation
 * links by route name. This is useful for plugins that want to override navigation
 * from base controllers without modifying base code.
 *
 * Example:
 * ```php
 * return new AdminNavigationConfig(
 *     section: 'My Section',
 *     links: [
 *         new AdminLink(label: 'menu_label', route: 'app_route', active: 'state', role: 'ROLE_ADMIN'),
 *     ],
 *     sectionRole: 'ROLE_USER',
 *     modifies: [
 *         'app_admin_cms' => [  // Route to modify
 *             'section' => 'GROUP NAME',  // Change section
 *             'label' => 'CMS Pages',     // Change label
 *         ],
 *     ],
 * );
 * ```
 */
readonly class AdminNavigationConfig
{
    /**
     * @param list<AdminLink> $links
     * @param array<string, array<string, string>>|null $modifies Route modifications (route => [field => value])
     */
    public function __construct(
        public string $section, // Section name (e.g., "System")
        public array $links, // Array of AdminLink objects
        public ?array $modifies = null, // Route modifications
        public ?string $sectionRole = null, // Required role for entire section
    ) {}
}
