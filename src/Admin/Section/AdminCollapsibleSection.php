<?php declare(strict_types=1);

namespace App\Admin\Section;

final readonly class AdminCollapsibleSection
{
    /**
     * @param list<AdminSectionItemInterface> $left
     * @param list<AdminSectionItemInterface> $right
     */
    public function __construct(
        public string $id,
        public array $left = [],
        public array $right = [],
        public bool $openByDefault = false,
    ) {}
}
