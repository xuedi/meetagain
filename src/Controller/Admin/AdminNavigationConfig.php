<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminLink;

/**
 * Configuration for a controller's admin navigation entry.
 *
 * Defines where and how a controller appears in the admin sidebar navigation.
 * Sections and links are sorted alphabetically.
 */
readonly class AdminNavigationConfig
{
    /**
     * @param list<AdminLink> $links
     */
    public function __construct(
        public string $section, // Section name (e.g., "System")
        public array $links, // Array of AdminLink objects
        public ?string $sectionRole = null, // Required role for entire section
    ) {}

    /**
     * Convenience factory for the common single-link case.
     */
    public static function single(
        string $section,
        string $label,
        string $route,
        ?string $active = null,
        ?string $linkRole = null,
        ?string $sectionRole = null,
    ): self {
        return new self(
            section: $section,
            links: [new AdminLink(label: $label, route: $route, active: $active, role: $linkRole)],
            sectionRole: $sectionRole,
        );
    }
}
