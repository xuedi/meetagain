<?php declare(strict_types=1);

namespace Plugin\Filmclub\Enum;

enum ViewType: string
{
    case List = 'list';
    case Tiles = 'tiles';
    case Grid = 'grid';
    case Gallery = 'gallery';

    public function icon(): string
    {
        return match ($this) {
            self::List => 'list',
            self::Tiles => 'grip',
            self::Grid => 'table-cells',
            self::Gallery => 'images',
        };
    }
}
