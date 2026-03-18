<?php declare(strict_types=1);

namespace App\Enum;

enum EventRsvpFilter: string
{
    case All = 'all';
    case My = 'my';
    case Friends = 'friends';
}
