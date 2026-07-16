<?php declare(strict_types=1);

namespace App\Enum;

enum ItemViewType: string
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

    public function label(): string
    {
        return match ($this) {
            self::List => 'item.view_list',
            self::Tiles => 'item.view_tiles',
            self::Grid => 'item.view_grid',
            self::Gallery => 'item.view_gallery',
        };
    }
}
