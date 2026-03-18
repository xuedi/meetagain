<?php declare(strict_types=1);

namespace App\Enum;

enum ConsentType: string
{
    case Granted = 'granted';
    case Denied = 'denied';
    case Unknown = 'unknown';
}
