<?php declare(strict_types=1);

namespace App\Admin\Top\Infos;

use App\Admin\Top\AdminTopInfoInterface;

final readonly class AdminTopInfoText implements AdminTopInfoInterface
{
    public function __construct(public string $text) {}

    public function getTemplate(): string
    {
        return 'admin/_components/admin_top/_info_text.html.twig';
    }
}
