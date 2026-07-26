<?php declare(strict_types=1);

namespace App\Enum;

enum AttributionStatus
{
    case Pending;
    case Provided;
    case NotRequired;
}
