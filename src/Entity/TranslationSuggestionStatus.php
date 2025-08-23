<?php declare(strict_types=1);

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

enum TranslationSuggestionStatus: int
{
    case Requested = 0;
    case Approved = 1;
    case Denied = 2;
    case Contested = 3;

    public static function getChoices(TranslatorInterface $translator): array
    {
        return array_flip(self::getTranslatedList($translator));
    }

    public static function getTranslatedList(TranslatorInterface $translator): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->value] = $translator->trans('translation_suggestion_status_' . strtolower($case->name));
        }

        return $choices;
    }
}
