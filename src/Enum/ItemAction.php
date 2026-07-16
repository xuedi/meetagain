<?php declare(strict_types=1);

namespace App\Enum;

enum ItemAction
{
    case Created;
    case Updated;
    case Deleted;
}
