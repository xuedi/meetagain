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
            $translator->trans('glossary.category_greeting') => self::Greeting,
            $translator->trans('glossary.category_swearing') => self::Swearing,
            $translator->trans('glossary.category_flirting') => self::Flirting,
            $translator->trans('glossary.category_slang') => self::Slang,
            $translator->trans('glossary.category_abbreviation') => self::Abbreviation,
            $translator->trans('glossary.category_regular') => self::Regular,
            $translator->trans('glossary.category_idioms') => self::Idioms,
        ];
    }

    public static function getNames(): array
    {
        return [
            self::Greeting->value => 'glossary.category_greeting',
            self::Swearing->value => 'glossary.category_swearing',
            self::Flirting->value => 'glossary.category_flirting',
            self::Slang->value => 'glossary.category_slang',
            self::Abbreviation->value => 'glossary.category_abbreviation',
            self::Regular->value => 'glossary.category_regular',
            self::Idioms->value => 'glossary.category_idioms',
        ];
    }
}
