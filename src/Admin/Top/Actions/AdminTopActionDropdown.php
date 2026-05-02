<?php declare(strict_types=1);

namespace App\Admin\Top\Actions;

use App\Admin\Top\AdminTopActionInterface;

final readonly class AdminTopActionDropdown implements AdminTopActionInterface
{
    /**
     * @param list<AdminTopActionDropdownOption> $options
     */
    public function __construct(
        public string $label,
        public array $options,
        public ?string $icon = null,
    ) {}

    public function getTemplate(): string
    {
        return 'admin/_components/admin_top/_action_dropdown.html.twig';
    }
}
