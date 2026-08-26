<?php declare(strict_types=1);

namespace App\Enum;

enum CirculationHandoverStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'circulation.handover_status_open',
            self::Completed => 'circulation.handover_status_completed',
            self::Cancelled => 'circulation.handover_status_cancelled',
            self::Expired => 'circulation.handover_status_expired',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    public function tagVariant(): string
    {
        return match ($this) {
            self::Open => 'is-warning',
            self::Completed => 'is-success',
            self::Cancelled, self::Expired => 'is-light',
        };
    }
}
