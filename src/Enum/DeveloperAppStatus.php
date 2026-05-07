<?php declare(strict_types=1);

namespace App\Enum;

enum DeveloperAppStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'developer_apps.status_pending',
            self::Approved => 'developer_apps.status_approved',
            self::Denied => 'developer_apps.status_denied',
            self::Revoked => 'developer_apps.status_revoked',
        };
    }

    public function tagClass(): string
    {
        return match ($this) {
            self::Pending => 'is-warning',
            self::Approved => 'is-success',
            self::Denied => 'is-danger',
            self::Revoked => 'is-light',
        };
    }
}
