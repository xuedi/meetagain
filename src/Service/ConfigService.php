<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\ImageType;

// TODO: add caches config repo stuff
readonly class ConfigService
{
    public function getThumbnailSizes(ImageType $type): array
    {
        return match ($type) {
            ImageType::ProfilePicture => [[400, 400], [50, 50]],
            ImageType::EventTeaser => [[600, 400]],
            ImageType::EventUpload => [[1024, 768], [210, 140]],
        };
    }
}
