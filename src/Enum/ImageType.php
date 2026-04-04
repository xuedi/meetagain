<?php declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatorInterface;

enum ImageType: int
{
    case ProfilePicture = 1;
    case EventTeaser = 2;
    case EventUpload = 3;
    case CmsBlock = 4;
    case PluginDish = 5;
    case LanguageTile = 7;
    case PluginBookclubCover = 8;
    case CmsGallery = 9;
    case CmsCardImage = 10;

    public static function getChoices(TranslatorInterface $translator): array
    {
        return array_flip(self::getTranslatedList($translator));
    }

    public static function getTranslatedList(TranslatorInterface $translator): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->value] = $translator->trans('image_type_' . strtolower($case->name));
        }

        return $choices;
    }
}
