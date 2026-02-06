<?php declare(strict_types=1);

namespace App\Controller\Admin;

/**
 * Configuration for a controller's admin navigation entry.
 *
 * Defines where and how a controller appears in the admin sidebar navigation.
 * Sections and links are sorted alphabetically.
 */
readonly class AdminNavigationConfig
{
    public function __construct(
        public string $section, // Section name (e.g., "System")
        public string $label, // Translation key (e.g., "menu_admin_system")
        public string $route, // Route name (e.g., "app_admin_system")
        public ?string $active = null, // Active state identifier (e.g., "system")
        public ?string $linkRole = null, // Required role for this link
        public ?string $sectionRole = null, // Required role for entire section
    ) {}
}
