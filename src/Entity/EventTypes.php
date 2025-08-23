<?php declare(strict_types=1);

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

enum EventTypes: int
{
    case All = 1;
    case Regular = 2;
    case Outdoor = 3;
    case Dinner = 4;

    public static function getChoices(TranslatorInterface $translator): array
    {
        return array_flip(self::getTranslatedList($translator));
    }

    public static function getTranslatedList(TranslatorInterface $translator): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->value] = $translator->trans('event_type_' . strtolower($case->name));
        }

        return $choices;
    }
}
