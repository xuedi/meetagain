<?php declare(strict_types=1);

namespace App\Enum;

enum CirculationRequestStatus: string
{
    case Waiting = 'waiting';
    case Offered = 'offered';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'circulation.request_status_waiting',
            self::Offered => 'circulation.request_status_offered',
            self::Fulfilled => 'circulation.request_status_fulfilled',
            self::Cancelled => 'circulation.request_status_cancelled',
            self::Expired => 'circulation.request_status_expired',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Waiting || $this === self::Offered;
    }
}
