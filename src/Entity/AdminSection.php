<?php declare(strict_types=1);

namespace App\Entity;

/**
 * @deprecated since 2026-04-30, use {@see \App\Admin\Navigation\AdminSection} instead.
 *             This class will be removed once all admin controllers have migrated to the new
 *             Admin\Navigation module. See plan 2026-04-30_admin-top-component.md.
 */
readonly class AdminSection
{
    /**
     * @param list<AdminLink> $links
     * @param array<string, string> $sectionParams Translator params for the section label
     */
    public function __construct(
        private string $section,
        private array $links,
        private ?string $role = null,
        private array $sectionParams = [],
    ) {}

    public function getSection(): string
    {
        return $this->section;
    }

    /**
     * @return list<AdminLink>
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    /**
     * @return array<string, string>
     */
    public function getSectionParams(): array
    {
        return $this->sectionParams;
    }
}
