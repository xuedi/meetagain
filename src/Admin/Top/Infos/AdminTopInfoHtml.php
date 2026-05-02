<?php declare(strict_types=1);

namespace App\Admin\Top\Infos;

use App\Admin\Top\AdminTopInfoInterface;

final readonly class AdminTopInfoHtml implements AdminTopInfoInterface
{
    // The renderer outputs $html with |raw. Callers MUST pass strings already composed from
    // translator output (auto-safe) plus htmlspecialchars-escaped user data; never feed raw user
    // input through this class. AdminTopInfoText is the default-safe path.
    public function __construct(public string $html) {}

    public function getTemplate(): string
    {
        return 'admin/_components/admin_top/_info_html.html.twig';
    }
}
