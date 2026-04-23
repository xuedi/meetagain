<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminLink;

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
