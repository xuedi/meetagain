<?php declare(strict_types=1);

namespace Plugin\Filmclub\Publisher;

use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\Publisher\ImageThumbnail\ImageThumbnailSizeProviderInterface;

readonly class FilmclubThumbnailSizeProvider implements ImageThumbnailSizeProviderInterface
{
    public function getThumbnailSizes(ImageType $type): ?array
    {
        return match ($type) {
            ImageType::PluginFilmclubPoster => [[400, 600], [200, 300], [100, 150], [50, 50]],
            default => null,
        };
    }

    public function getFitMode(ImageType $type): ?ImageFitMode
    {
        return match ($type) {
            ImageType::PluginFilmclubPoster => ImageFitMode::Fit,
            default => null,
        };
    }
}
