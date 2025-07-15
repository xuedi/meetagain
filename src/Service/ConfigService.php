<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\ImageType;
use App\Repository\ConfigRepository;

// TODO: add caches config repo stuff
readonly class ConfigService
{
    public function __construct(private ConfigRepository $repo)
    {
    }

    public function getThumbnailSizes(ImageType $type): array
    {
        return match ($type) {
            ImageType::ProfilePicture => [[400, 400], [80, 80], [50, 50]],
            ImageType::EventTeaser => [[1024, 768], [600, 400], [210, 140]], // included EventUpload
            ImageType::EventUpload => [[1024, 768], [210, 140]],
            ImageType::CmsBlock => [[432, 432], [80, 80]],
        };
    }

    public function getThumbnailSizeList(): array
    {
        return [
            '1024x768' => 0, // gallery image bit
            '600x400' => 0,  // event preview image
            '432x432' => 0,  // cmsBlock image
            '400x400' => 0,  // profile big
            '210x140' => 0,  // gallery image preview
            '80x80' => 0,    // ?
            '50x50' => 0,    // ?
        ];
    }

    public function isValidThumbnailSize(ImageType $type, int $checkWidth, int $checkHeight): bool
    {
        foreach ($this->getThumbnailSizes($type) as list($width, $height)) {
            if ($checkWidth == $width && $checkHeight == $height) {
                return true;
            }
        }

        return false;
    }

    public function getHost(): string
    {
        return $_ENV['APP_HOST'] ?? 'http://localhost';
    }

    // TODO: implement caching
    public function getBoolean(string $name, bool $default = false): bool
    {
        $setting = $this->repo->findOneBy(['name' => $name]);
        if ($setting === null) {
            return $default;
        }

        return $setting->getValue() === 'true';
    }
}
