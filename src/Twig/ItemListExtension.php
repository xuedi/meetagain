<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ItemListExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('item_list_body', [ItemListRuntime::class, 'listBody'], ['is_safe' => ['html']]),
            new TwigFunction('item_list_markup', [ItemListRuntime::class, 'listMarkup'], ['is_safe' => ['html']]),
            new TwigFunction('item_list_url', [ItemListRuntime::class, 'listUrl']),
            new TwigFunction('item_list_count', [ItemListRuntime::class, 'listCount']),
        ];
    }
}
