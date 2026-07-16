<?php declare(strict_types=1);

namespace App\Enum;

/**
 * Classifies the purpose of an uploaded image.
 *
 * Each case doubles as a location discriminator: because one image type is always used
 * in exactly one place, the type value is sufficient to identify where an image is used.
 * This is the key the image-type-definition system relies on - see
 * src/Service/Media/ImageTypes/.
 */
enum ImageType: int
{
    case ProfilePicture = 1;
    case EventTeaser = 2;
    case EventUpload = 3;
    case CmsBlock = 4;
    case PluginDishesPreview = 5;
    case LanguageTile = 7;
    case PluginBooksCover = 8;
    case CmsGallery = 9;
    case CmsCardImage = 10;
    case SiteLogo = 11;
    case GroupLogo = 12;
    case GroupPromotion = 13;
    case GroupPreview = 14;
    case WebsiteImage = 15;
    case DeveloperAppLogo = 16;
    case PluginFilmsPoster = 17;
}
