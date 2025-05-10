<?php declare(strict_types=1);

namespace App\Entity\Session;

enum ConsentType: string
{
    case Granted = 'granted';
    case Denied = 'denied';
    case Unknown = 'unknown';
}
