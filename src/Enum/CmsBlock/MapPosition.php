<?php declare(strict_types=1);

namespace App\Enum\CmsBlock;

enum MapPosition: string
{
    case Left = 'left';
    case Right = 'right';
    case Above = 'above';
    case Below = 'below';

    public function isStacked(): bool
    {
        return $this === self::Above || $this === self::Below;
    }
}
