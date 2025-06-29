<?php declare(strict_types=1);

namespace Plugin\Glossary\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

enum Category: int
{
    case Greeting = 0;
    case Swearing = 1;
    case Flirting = 2;
    case InternetSlang = 3;

    public static function getChoices(TranslatorInterface $translator): array
    {
        return [
            $translator->trans('Greeting') => self::Greeting,
            $translator->trans('Swearing') => self::Swearing,
            $translator->trans('Flirting') => self::Flirting,
            $translator->trans('InternetSlang') => self::InternetSlang,
        ];
    }
}
