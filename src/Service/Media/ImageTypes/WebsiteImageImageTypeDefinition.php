<?php declare(strict_types=1);

namespace App\Service\Media\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageType;

final class WebsiteImageImageTypeDefinition extends AbstractImageTypeDefinition
{
    public function getType(): ImageType
    {
        return ImageType::WebsiteImage;
    }

    protected function sizes(): array
    {
        return [[1200, 630], [350, 184]];
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_admin_system_config', 'params' => []];
    }

    public function discoverImageIds(): array
    {
        $row = $this->connection->fetchAssociative("SELECT value FROM config WHERE name = 'website_image_id' LIMIT 1");

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
        $row = $this->connection->fetchAssociative("SELECT value FROM config WHERE name = 'website_image_id' LIMIT 1");
        if ($row === false || (int) $row['value'] <= 0 || (int) $row['value'] !== $image->getId()) {
            return null;
        }

        return [
            'label' => 'Website image',
            'route' => 'app_admin_system_config',
            'params' => [],
        ];
    }
}
