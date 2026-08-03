<?php declare(strict_types=1);

namespace App\Enum;

enum TownHallTileLocation: string
{
    case FullWidth = 'fullWidth';
    case Main = 'main';
    case Sidebar = 'sidebar';
}
