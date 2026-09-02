<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ItemTagExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('item_tag_labels', [ItemTagRuntime::class, 'tagLabels']),
            new TwigFunction('item_tag_choices', [ItemTagRuntime::class, 'tagChoices']),
            new TwigFunction('item_tag_levels', [ItemTagRuntime::class, 'tagLevels']),
            new TwigFunction('item_tag_pending', [ItemTagRuntime::class, 'pendingCount']),
        ];
    }
}
