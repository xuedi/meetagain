<?php declare(strict_types=1);

namespace Plugin\Bookclub\Service\ImageLocations;

use App\Enum\ImageType;
use App\Service\Media\ImageLocations\AbstractImageLocationProvider;

final class BookCoverLocationProvider extends AbstractImageLocationProvider
{
    public function getType(): ImageType
    {
        return ImageType::PluginBookclubCover;
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_plugin_bookclub_book_show', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT cover_image_id AS image_id, id AS location_id FROM book WHERE cover_image_id IS NOT NULL',
        );

        return array_map(
            static fn(array $r) => ['imageId' => (int) $r['image_id'], 'locationId' => (int) $r['location_id']],
            $rows,
        );
    }
}
