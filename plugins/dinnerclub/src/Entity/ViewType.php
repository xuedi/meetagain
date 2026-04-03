<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Entity;

enum ViewType: string
{
    case List = 'list';
    case Tiles = 'tiles';
    case Grid = 'grid';
    case Gallery = 'gallery';
}
