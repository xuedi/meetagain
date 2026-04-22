<?php declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatorInterface;

enum UserStatus: int
{
    case Registered = 0;
    case EmailVerified = 1;
    case Active = 2;
    case Blocked = 3;
    case Deleted = 4;
    case Denied = 5;

    // TODO: should be separate translator not here in enum
    public static function getChoices(TranslatorInterface $translator): array
    {
        return [
            $translator->trans('admin_member.status_registered') => self::Registered,
            $translator->trans('admin_member.status_email_verified') => self::EmailVerified,
            $translator->trans('admin_member.status_active') => self::Active,
            $translator->trans('admin_member.status_blocked') => self::Blocked,
            $translator->trans('admin_member.status_deleted') => self::Deleted,
            $translator->trans('admin_member.status_denied') => self::Denied,
        ];
    }
}
