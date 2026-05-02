<?php declare(strict_types=1);

namespace App\Admin\Section\Items;

use App\Admin\Section\AdminSectionItemInterface;

final readonly class AdminSectionTextItem implements AdminSectionItemInterface
{
    public function __construct(
        public string $text,
        public string $extraClass = '',
    ) {}

    public function getTemplate(): string
    {
        return 'admin/_components/admin_section/_text.html.twig';
    }
}
