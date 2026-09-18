<?php declare(strict_types=1);

namespace App\Enum;

enum SecurityMeasureOutcome: string
{
    case Passed = 'passed';
    case Blocked = 'blocked';
}
