<?php declare(strict_types=1);

namespace App\Enum;

enum ImageType: int
{
    case ProfilePicture = 1;
    case EventTeaser = 2;
    case EventUpload = 3;
    case CmsBlock = 4;
    case PluginDishesPreview = 5;
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
    case PluginPhotosPhoto = 18;
}
