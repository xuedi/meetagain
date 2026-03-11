<?php declare(strict_types=1);

namespace Plugin\Bookclub\Entity;

enum ViewType: string
{
    case Tiles = 'tiles';
    case List = 'list';
}
