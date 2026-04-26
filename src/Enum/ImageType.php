<?php declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Classifies the purpose of an uploaded image.
 *
 * Each case doubles as a location discriminator: because one image type is always used
 * in exactly one place, the type value is sufficient to identify where an image is used.
 * This is the key the ImageLocation discovery system relies on — see
 * src/Service/Media/ImageLocationService.php and src/Service/Media/ImageLocations/.
 */
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
    case SiteLogo = 11;
    case GroupLogo = 12;

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
