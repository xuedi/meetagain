<?php declare(strict_types=1);

namespace App\Enum;

enum SupportAudience: string
{
    case Organizer = 'organizer';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Organizer => 'support.audience_organizer',
            self::Admin => 'support.audience_admin',
        };
    }

    public function help(): string
    {
        return match ($this) {
            self::Organizer => 'support.audience_organizer_help',
            self::Admin => 'support.audience_admin_help',
        };
    }
}
