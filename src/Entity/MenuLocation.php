<?php declare(strict_types=1);

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

enum MenuLocation: int
{
    case TopBar = 0;
    case BottomCol1 = 1;
    case BottomCol2 = 2;
    case BottomCol3 = 3;
    case BottomCol4 = 4;

    public static function getChoices(TranslatorInterface $translator): array
    {
        return array_flip(self::getTranslatedList($translator));
    }

    public static function getTranslatedList(TranslatorInterface $translator): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->value] = $translator->trans('menu_location_' . strtolower($case->name));
        }

        return $choices;
    }
}
