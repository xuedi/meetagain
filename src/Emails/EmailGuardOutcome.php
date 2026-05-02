<?php declare(strict_types=1);

namespace App\Emails;

enum EmailGuardOutcome: string
{
    case Pass = 'pass';
    case Skip = 'skip';
    case Error = 'error';
}
