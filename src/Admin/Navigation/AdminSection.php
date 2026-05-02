<?php declare(strict_types=1);

namespace App\Admin\Navigation;

final readonly class AdminSection
{
    /**
     * @param list<AdminLink>       $links
     * @param array<string, string> $sectionParams
     */
    public function __construct(
        public string $section,
        public array $links,
        public ?string $role = null,
        public array $sectionParams = [],
    ) {}
}
