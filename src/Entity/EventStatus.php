<?php declare(strict_types=1);

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

enum EventStatus: string
{
    case Draft     = 'draft';
    case Published = 'published';
    case Locked    = 'locked';

    public static function getChoices(TranslatorInterface $translator): array
    {
        return [
            $translator->trans('published') => self::Published,
            $translator->trans('locked')    => self::Locked,
            $translator->trans('draft')     => self::Draft,
        ];
    }
}
