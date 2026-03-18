<?php declare(strict_types=1);

namespace App\Enum;

enum EventSortFilter: string
{
    case OldToNew = 'asc';
    case NewToOld = 'desc';
}
