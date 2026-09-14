<?php declare(strict_types=1);

namespace App\Portability;

enum Outcome: string
{
    case Created = 'created';
    case Matched = 'matched';
    case Skipped = 'skipped';
    case Dropped = 'dropped';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'admin_system_import.col_created',
            self::Matched => 'admin_system_import.col_matched',
            self::Skipped => 'admin_system_import.col_skipped',
            self::Dropped => 'admin_system_import.col_dropped',
        };
    }

    public function isLoss(): bool
    {
        return $this === self::Skipped || $this === self::Dropped;
    }
}
