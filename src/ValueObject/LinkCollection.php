<?php declare(strict_types=1);

namespace App\ValueObject;

use App\Entity\Link;

readonly class LinkCollection
{
    /**
     * @param list<Link>                $leadingNavLinks      rendered before all other navbar-start entries
     * @param list<Link>                $navLinks
     * @param array<string, list<Link>> $footerLinks         keyed by column name
     * @param array<string, string>     $footerColumnTitles  keyed by column name
     * @param list<Link>                $profileDropdownLinks
     * @param list<Link>                $profileConfigLinks   action-button links rendered on /profile/config
     * @param list<string>              $navbarPillsHtml      pre-rendered HTML fragments injected next to the user dropdown
     */
    public function __construct(
        private array $leadingNavLinks = [],
        private array $navLinks = [],
        private array $footerLinks = [],
        private array $footerColumnTitles = [],
        private array $profileDropdownLinks = [],
        private array $profileConfigLinks = [],
        private array $navbarPillsHtml = [],
    ) {}

    public static function empty(): self
    {
        return new self();
    }

    /** @return list<Link> */
    public function getLeadingNavLinks(): array
    {
        return $this->leadingNavLinks;
    }

    /** @return list<Link> */
    public function getNavLinks(): array
    {
        return $this->navLinks;
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

    /** @return list<Link> */
    public function getProfileConfigLinks(): array
    {
        return $this->profileConfigLinks;
    }

    /** @param list<Link> $links */
    public function withLeadingNavLinks(array $links): self
    {
        return new self($links, $this->navLinks, $this->footerLinks, $this->footerColumnTitles, $this->profileDropdownLinks, $this->profileConfigLinks, $this->navbarPillsHtml);
    }

    /** @param list<Link> $links */
    public function withNavLinks(array $links): self
    {
        return new self($this->leadingNavLinks, $links, $this->footerLinks, $this->footerColumnTitles, $this->profileDropdownLinks, $this->profileConfigLinks, $this->navbarPillsHtml);
    }

    /** @param list<Link> $links */
    public function withFooterLinks(string $column, array $links): self
    {
        $footerLinks = $this->footerLinks;
        $footerLinks[$column] = $links;

        return new self(
            $this->leadingNavLinks,
            $this->navLinks,
            $footerLinks,
            $this->footerColumnTitles,
            $this->profileDropdownLinks,
            $this->profileConfigLinks,
            $this->navbarPillsHtml,
        );
    }

    public function withFooterColumnTitle(string $column, string $title): self
    {
        $titles = $this->footerColumnTitles;
        $titles[$column] = $title;

        return new self($this->leadingNavLinks, $this->navLinks, $this->footerLinks, $titles, $this->profileDropdownLinks, $this->profileConfigLinks, $this->navbarPillsHtml);
    }

    /** @param list<Link> $links */
    public function withProfileDropdownLinks(array $links): self
    {
        return new self($this->leadingNavLinks, $this->navLinks, $this->footerLinks, $this->footerColumnTitles, $links, $this->profileConfigLinks, $this->navbarPillsHtml);
    }

    /** @param list<Link> $links */
    public function withProfileConfigLinks(array $links): self
    {
        return new self($this->leadingNavLinks, $this->navLinks, $this->footerLinks, $this->footerColumnTitles, $this->profileDropdownLinks, $links, $this->navbarPillsHtml);
    }

    /** @return list<string> */
    public function getNavbarPillsHtml(): array
    {
        return $this->navbarPillsHtml;
    }

    /** @param list<string> $html */
    public function withNavbarPillsHtml(array $html): self
    {
        return new self($this->leadingNavLinks, $this->navLinks, $this->footerLinks, $this->footerColumnTitles, $this->profileDropdownLinks, $this->profileConfigLinks, $html);
    }
}
