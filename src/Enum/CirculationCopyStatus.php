<?php declare(strict_types=1);

namespace App\Enum;

enum CirculationCopyStatus: string
{
    case Available = 'available';
    case Held = 'held';
    case InHandover = 'in_handover';
    case Retired = 'retired';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'circulation.copy_status_available',
            self::Held => 'circulation.copy_status_held',
            self::InHandover => 'circulation.copy_status_in_handover',
            self::Retired => 'circulation.copy_status_retired',
            self::Lost => 'circulation.copy_status_lost',
        };
    }

    public function tagVariant(): string
    {
        return match ($this) {
            self::Available => 'is-success',
            self::Held => 'is-info',
            self::InHandover => 'is-warning',
            self::Retired, self::Lost => 'is-light',
        };
    }

    public function isHeld(): bool
    {
        return $this === self::Held;
    }

    public function isCirculating(): bool
    {
        return $this === self::Available || $this === self::Held || $this === self::InHandover;
    }
}
