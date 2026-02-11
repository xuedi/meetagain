<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminLink;

/**
 * Configuration for a controller's admin navigation entry.
 *
 * Defines where and how a controller appears in the admin sidebar navigation.
 * Sections and links are sorted alphabetically.
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
 *     links: [...],
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
        public ?string $linkRole = null,
    ) {}

    /**
     * Convenience factory for the common single-link case.
     *
     * @param array<string, array<string, string>>|null $modifies Route modifications
     */
    public static function single(
        string $section,
        string $label,
        string $route,
        ?string $active = null,
        ?string $linkRole = null,
        ?string $sectionRole = null,
        ?array $modifies = null,
    ): self {
        return new self(
            section: $section,
            links: [new AdminLink(label: $label, route: $route, active: $active, role: $linkRole)],
            modifies: $modifies,
            sectionRole: $sectionRole,
        );
    }
}
