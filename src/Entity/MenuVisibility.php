<?php declare(strict_types=1);

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

enum MenuVisibility: int
{
    case Everyone = 0;
    case User = 1;
    case Manager = 2;
    case Admin = 3;

    public static function getChoices(TranslatorInterface $translator): array
    {
        return array_flip(self::getTranslatedList($translator));
    }

    public static function getTranslatedList(TranslatorInterface $translator): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->value] = $translator->trans('menu_visibility_' . $case->name);
        }

        return $choices;
    }
}
