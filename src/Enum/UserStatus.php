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
            $translator->trans('Registered') => self::Registered,
            $translator->trans('EmailVerified') => self::EmailVerified,
            $translator->trans('Active') => self::Active,
            $translator->trans('Blocked') => self::Blocked,
            $translator->trans('Deleted') => self::Deleted,
            $translator->trans('Denied') => self::Denied,
        ];
    }
}
