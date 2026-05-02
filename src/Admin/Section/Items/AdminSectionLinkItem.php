<?php declare(strict_types=1);

namespace App\Admin\Section\Items;

use App\Admin\Section\AdminSectionItemInterface;

final readonly class AdminSectionLinkItem implements AdminSectionItemInterface
{
    public function __construct(
        public string $href,
        public string $icon,
        public ?string $title = null,
    ) {}

    public function getTemplate(): string
    {
        return 'admin/_components/admin_section/_link.html.twig';
    }
}
