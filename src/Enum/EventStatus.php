<?php declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatorInterface;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Locked = 'locked';

    public static function getChoices(TranslatorInterface $translator): array
    {
        return [
            $translator->trans('events.status_published') => self::Published,
            $translator->trans('events.status_locked') => self::Locked,
            $translator->trans('events.status_draft') => self::Draft,
        ];
    }
}
