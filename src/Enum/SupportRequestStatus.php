<?php declare(strict_types=1);

namespace App\Enum;

enum SupportRequestStatus: string
{
    case New = 'new';
    case Read = 'read';
}
