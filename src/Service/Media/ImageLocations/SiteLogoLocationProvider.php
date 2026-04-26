<?php declare(strict_types=1);

namespace App\Service\Media\ImageLocations;

use App\Enum\ImageType;

final class SiteLogoLocationProvider extends AbstractImageLocationProvider
{
    public function getType(): ImageType
    {
        return ImageType::SiteLogo;
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_admin_system_theme', 'params' => []];
    }

    public function discoverImageIds(): array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT value FROM config WHERE name = 'site_logo_id' LIMIT 1",
        );

        if ($row === false) {
            return [];
        }

        $imageId = (int) $row['value'];
        if ($imageId <= 0) {
            return [];
        }

        return [['imageId' => $imageId, 'locationId' => 0]];
    }
}
