<?php declare(strict_types=1);

namespace App\Service\Media\ImageLocations;

use App\Enum\ImageType;

final class ProfilePictureLocationProvider extends AbstractImageLocationProvider
{
    public function getType(): ImageType
    {
        return ImageType::ProfilePicture;
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_admin_member_edit', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT image_id, id AS location_id FROM `user` WHERE image_id IS NOT NULL',
        );

        return array_map(
            static fn(array $r) => ['imageId' => (int) $r['image_id'], 'locationId' => (int) $r['location_id']],
            $rows,
        );
    }
}
