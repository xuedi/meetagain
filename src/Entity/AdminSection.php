<?php declare(strict_types=1);

namespace App\Entity;

readonly class AdminSection
{
    /**
     * @param list<AdminLink> $links
     */
    public function __construct(
        private string $section,
        private array $links,
    ) {
    }

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
}
