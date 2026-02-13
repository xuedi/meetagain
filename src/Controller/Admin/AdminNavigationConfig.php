<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminLink;

/**
 * Admin sidebar navigation configuration.
 *
 * Sections and links are sorted alphabetically.
 * Optional $modifies allows plugins to override existing navigation links by route name.
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
