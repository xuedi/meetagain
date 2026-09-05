<?php declare(strict_types=1);

namespace Plugin\Boardgames\Enum;

enum RequestStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';

    public function isClosed(): bool
    {
        return $this !== self::Open;
    }
}
