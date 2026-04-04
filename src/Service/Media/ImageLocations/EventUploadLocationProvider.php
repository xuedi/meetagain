<?php declare(strict_types=1);

namespace App\Service\Media\ImageLocations;

use App\Enum\ImageType;

final class EventUploadLocationProvider extends AbstractImageLocationProvider
{
    public function getType(): ImageType
    {
        return ImageType::EventUpload;
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_admin_event_edit', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id AS image_id, event_id AS location_id FROM image WHERE event_id IS NOT NULL',
        );

        return array_map(
            static fn(array $r) => ['imageId' => (int) $r['image_id'], 'locationId' => (int) $r['location_id']],
            $rows,
        );
    }
}
