<?php declare(strict_types=1);

namespace App\Enum;

enum SecurityEventType: string
{
    case NotFound = 'not_found';
    case RateLimit = 'rate_limit';
    case AccessDenied = 'access_denied';
}
