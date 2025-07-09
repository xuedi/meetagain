<?php declare(strict_types=1);

namespace Plugin\Glossary\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

enum Category: int
{
    case Greeting = 0;
    case Swearing = 1;
    case Flirting = 2;
    case Slang = 3;
    case Abbreviation = 4;
    case Regular = 5;
    case Idioms = 6;

    public static function getChoices(TranslatorInterface $translator): array
    {
        return [
            $translator->trans('Greeting') => self::Greeting,
            $translator->trans('Swearing') => self::Swearing,
            $translator->trans('Flirting') => self::Flirting,
            $translator->trans('Slang') => self::Slang,
            $translator->trans('Abbreviation') => self::Abbreviation,
            $translator->trans('Regular') => self::Regular,
            $translator->trans('Idioms') => self::Idioms,
        ];
    }

    public static function getNames(): array
    {
        return [
            self::Greeting->value => 'Greeting',
            self::Swearing->value => 'Swearing',
            self::Flirting->value => 'Flirting',
            self::Slang->value => 'Slang',
            self::Abbreviation->value => 'Abbreviation',
            self::Regular->value => 'Regular',
            self::Idioms->value => 'Idioms',
        ];
    }
}
