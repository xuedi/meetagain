<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service\ImageLocations;

use App\Enum\ImageType;
use App\Service\Media\ImageLocations\AbstractImageLocationProvider;

final class FilmPosterLocationProvider extends AbstractImageLocationProvider
{
    public function getType(): ImageType
    {
        return ImageType::PluginFilmclubPoster;
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_plugin_filmclub_film_show', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT poster_image_id AS image_id, id AS location_id FROM film WHERE poster_image_id IS NOT NULL',
        );

        return array_map(
            static fn(array $r) => ['imageId' => (int) $r['image_id'], 'locationId' => (int) $r['location_id']],
            $rows,
        );
    }
}
