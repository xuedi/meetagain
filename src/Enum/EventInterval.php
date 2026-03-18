<?php declare(strict_types=1);

namespace App\Enum;

enum EventInterval: int
{
    case Daily = 1;
    case Weekly = 2;
    case BiMonthly = 3;
    case Monthly = 4;
    case Yearly = 5;
}
