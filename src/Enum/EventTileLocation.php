<?php declare(strict_types=1);

namespace App\Enum;

enum EventTileLocation: string
{
    case Sidebar = 'sidebar';
    case Center = 'center';
    case BottomSidebar = 'bottomSidebar';
}
