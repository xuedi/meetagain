<?php declare(strict_types=1);

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

enum ImageReported: int
{
    case Privacy = 1;
    case Copyright = 2;
    case Inappropriate = 3;

    // TODO: should be separate translator not here in enum
    public static function getChoices(TranslatorInterface $translator): array
    {
        return [
            $translator->trans('image_report_reason_privacy') => self::Privacy,
            $translator->trans('image_report_reason_copyright') => self::Copyright,
            $translator->trans('image_report_reason_inappropriate') => self::Inappropriate,
        ];
    }
}
