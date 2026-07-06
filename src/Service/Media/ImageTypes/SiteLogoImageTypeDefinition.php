<?php declare(strict_types=1);

namespace App\Service\Media\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageFitMode;
use App\Enum\ImageType;

final class SiteLogoImageTypeDefinition extends AbstractImageTypeDefinition
{
    public function getType(): ImageType
    {
        return ImageType::SiteLogo;
    }

    protected function sizes(): array
    {
        return [[400, 400], [350, 350]];
    }

    public function fitMode(): ImageFitMode
    {
        return ImageFitMode::Fit;
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_admin_system_theme', 'params' => []];
    }

    public function discoverImageIds(): array
    {
        $row = $this->connection->fetchAssociative("SELECT value FROM config WHERE name = 'site_logo_id' LIMIT 1");

        if ($row === false) {
            return [];
        }

        $imageId = (int) $row['value'];
        if ($imageId <= 0) {
            return [];
        }

        return [['imageId' => $imageId, 'locationId' => 0]];
    }

    public function locate(Image $image): ?array
    {
        $row = $this->connection->fetchAssociative("SELECT value FROM config WHERE name = 'site_logo_id' LIMIT 1");
        if ($row === false || (int) $row['value'] <= 0 || (int) $row['value'] !== $image->getId()) {
            return null;
        }

        return [
            'label' => 'Site logo',
            'route' => 'app_admin_system_theme',
            'params' => [],
        ];
    }
}
