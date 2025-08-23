<?php declare(strict_types=1);

namespace Plugin\Dishes\Entity;

enum ViewType: string
{
    case List = 'list';
    case Tiles = 'tiles';
    case Grid = 'grid';
    case Gallery = 'gallery';
}
