<?php declare(strict_types=1);

namespace App\Admin\Navigation;

final readonly class AdminNavigationConfig
{
    /**
     * @param list<AdminLink>                          $links
     * @param array<string, array<string, mixed>>|null $modifies
     * @param array<string, string>|null               $sectionParams
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
