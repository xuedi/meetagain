<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ItemViewExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('item_view_mode', [ItemViewRuntime::class, 'itemViewMode']),
            new TwigFunction('item_view_types', [ItemViewRuntime::class, 'itemViewTypes']),
            new TwigFunction('item_list_cell', [ItemViewRuntime::class, 'itemListCell'], ['is_safe' => ['html']]),
        ];
    }
}
