<?php declare(strict_types=1);

namespace App\Enum;

enum SupportRequestStatus: string
{
    case New = 'new';
    case Read = 'read';
    case Replied = 'replied';
    case Reopened = 'reopened';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::New => 'admin_support.status_new',
            self::Read => 'admin_support.status_read',
            self::Replied => 'admin_support.status_replied',
            self::Reopened => 'admin_support.status_reopened',
            self::Resolved => 'admin_support.status_resolved',
        };
    }

    public function tagVariant(): string
    {
        return match ($this) {
            self::New, self::Reopened => 'is-warning',
            self::Replied => 'is-success',
            self::Read, self::Resolved => 'is-light',
        };
    }
}
