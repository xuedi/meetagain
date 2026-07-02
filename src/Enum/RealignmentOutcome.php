<?php declare(strict_types=1);

namespace App\Enum;

enum RealignmentOutcome: string
{
    case Moved = 'moved';
    case DateUnchanged = 'date_unchanged';
    case SkippedLocked = 'skipped_locked';
    case SkippedCanceled = 'skipped_canceled';

    public function label(): string
    {
        return match ($this) {
            self::Moved => 'admin_event.reschedule_outcome_moved',
            self::DateUnchanged => 'admin_event.reschedule_outcome_unchanged',
            self::SkippedLocked => 'admin_event.reschedule_outcome_locked',
            self::SkippedCanceled => 'admin_event.reschedule_outcome_canceled',
        };
    }
}
