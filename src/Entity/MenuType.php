<?php declare(strict_types=1);

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

enum MenuType: int
{
    case Url = 0;
    case Cms = 1;
    case Event = 2;
    case Route = 3;

    public static function getChoices(TranslatorInterface $translator): array
    {
        return array_flip(self::getTranslatedList($translator));
    }

    public static function getTranslatedList(TranslatorInterface $translator): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->value] = $translator->trans('menu_type_' . strtolower($case->name));
        }

        return $choices;
    }
}
