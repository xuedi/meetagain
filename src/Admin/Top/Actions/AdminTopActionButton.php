<?php declare(strict_types=1);

namespace App\Admin\Top\Actions;

use App\Admin\Top\AdminTopActionInterface;

final readonly class AdminTopActionButton implements AdminTopActionInterface
{
    public function __construct(
        public string $label,
        public string $target,
        public ?string $icon = null,
        public ?string $variant = null,
        public ?string $confirm = null,
    ) {}

    public function getTemplate(): string
    {
        return 'admin/_components/admin_top/_action_button.html.twig';
    }
}
