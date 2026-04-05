<?php declare(strict_types=1);

namespace App\ValueObject;

use App\Entity\AdminSection;
use App\Entity\Link;

/**
 * Immutable value object holding all link slots a plugin can contribute.
 * Use the with*() fluent builders to populate only the slots you need.
 */
readonly class LinkCollection
{
    /**
     * @param list<Link>                  $navLinks
     * @param array<string, list<Link>>   $footerLinks         keyed by column name
     * @param array<string, string>       $footerColumnTitles  keyed by column name
     * @param list<Link>                  $profileDropdownLinks
     */
    public function __construct(
        private array $navLinks = [],
        private ?AdminSection $adminSection = null,
        private array $footerLinks = [],
        private array $footerColumnTitles = [],
        private array $profileDropdownLinks = [],
    ) {}

    public static function empty(): self
    {
        return new self();
    }

    /** @return list<Link> */
    public function getNavLinks(): array
    {
        return $this->navLinks;
    }

    public function getAdminSection(): ?AdminSection
    {
        return $this->adminSection;
    }

    /** @return list<Link> */
    public function getFooterLinks(string $column): array
    {
        return $this->footerLinks[$column] ?? [];
    }

    public function getFooterColumnTitle(string $column): ?string
    {
        return $this->footerColumnTitles[$column] ?? null;
    }

    /** @return list<Link> */
    public function getProfileDropdownLinks(): array
    {
        return $this->profileDropdownLinks;
    }

    /** @param list<Link> $links */
    public function withNavLinks(array $links): self
    {
        return new self($links, $this->adminSection, $this->footerLinks, $this->footerColumnTitles, $this->profileDropdownLinks);
    }

    public function withAdminSection(?AdminSection $section): self
    {
        return new self($this->navLinks, $section, $this->footerLinks, $this->footerColumnTitles, $this->profileDropdownLinks);
    }

    /** @param list<Link> $links */
    public function withFooterLinks(string $column, array $links): self
    {
        $footerLinks = $this->footerLinks;
        $footerLinks[$column] = $links;

        return new self($this->navLinks, $this->adminSection, $footerLinks, $this->footerColumnTitles, $this->profileDropdownLinks);
    }

    public function withFooterColumnTitle(string $column, string $title): self
    {
        $titles = $this->footerColumnTitles;
        $titles[$column] = $title;

        return new self($this->navLinks, $this->adminSection, $this->footerLinks, $titles, $this->profileDropdownLinks);
    }

    /** @param list<Link> $links */
    public function withProfileDropdownLinks(array $links): self
    {
        return new self($this->navLinks, $this->adminSection, $this->footerLinks, $this->footerColumnTitles, $links);
    }
}
