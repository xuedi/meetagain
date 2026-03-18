<?php declare(strict_types=1);

namespace App\Enum;

enum EventTimeFilter: int
{
    case All = 1;
    case Past = 2;
    case Future = 3;
}
