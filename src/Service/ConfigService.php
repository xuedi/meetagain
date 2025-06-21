<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\ImageType;

// TODO: add caches config repo stuff
readonly class ConfigService
{
    public function getThumbnailSizes(ImageType $type): array
    {
        return match ($type) {
            ImageType::ProfilePicture => [[400, 400], [80, 80], [50, 50]],
            ImageType::EventTeaser => [[1024, 768], [600, 400], [210, 140]], // included EventUpload
            ImageType::EventUpload => [[1024, 768], [210, 140]],
            ImageType::CmsBlock => [[432, 432], [80, 80]],
        };
    }

    public function getHost(): string
    {
        return $_ENV['APP_HOST'] ?? 'http://localhost';
    }
}
