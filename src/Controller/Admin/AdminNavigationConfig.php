<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminLink;

/**
 * @deprecated since 2026-04-30, use {@see \App\Admin\Navigation\AdminNavigationConfig} instead.
 *             This class will be removed once all admin controllers have migrated to the new
 *             Admin\Navigation module. See plan 2026-04-30_admin-top-component.md.
 */
readonly class AdminNavigationConfig
{
    /**
     * @param list<AdminLink> $links
     * @param array<string, array<string, mixed>>|null $modifies Route modifications (route => [field => value])
     * @param array<string, string>|null $sectionParams Translator params for the section label
     */
    public function __construct(
        public string $section,
        public array $links,
        public ?array $modifies = null,
        public ?string $sectionRole = null,
        public int $sectionPriority = 0,
        public ?array $sectionParams = null,
    ) {}
}
