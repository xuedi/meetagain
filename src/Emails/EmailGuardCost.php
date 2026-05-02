<?php declare(strict_types=1);

namespace App\Emails;

enum EmailGuardCost: int
{
    case Free = 0;
    case InMemory = 1;
    case Database = 2;
    case Network = 3;
}
