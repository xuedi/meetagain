<?php declare(strict_types=1);

namespace App\Enum;

enum ModerationReportStatus: string
{
    case Open = 'open';
    case Dismissed = 'dismissed';
    case Actioned = 'actioned';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'admin_support_moderation.status_open',
            self::Dismissed => 'admin_support_moderation.status_dismissed',
            self::Actioned => 'admin_support_moderation.status_actioned',
        };
    }

    public function tagVariant(): string
    {
        return match ($this) {
            self::Open => 'is-warning',
            self::Actioned => 'is-success',
            self::Dismissed => 'is-light',
        };
    }
}
