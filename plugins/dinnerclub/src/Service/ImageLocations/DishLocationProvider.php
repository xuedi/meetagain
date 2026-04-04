<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Service\ImageLocations;

use App\Enum\ImageType;
use App\Service\Media\ImageLocations\AbstractImageLocationProvider;

/**
 * Discovers all PluginDish image locations — both gallery images and preview images.
 * Both share ImageType::PluginDish and are merged here so that sync() operates on the
 * complete set in one pass, preventing each sub-query from deleting the other's rows.
 *
 * TODO: should be split into two providers, and two ImageTypes even tough it is technically the same
 */
final class DishLocationProvider extends AbstractImageLocationProvider
{
    public function getType(): ImageType
    {
        return ImageType::PluginDish;
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'plugin_dinnerclub_item_show', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $pairs = [];

        // Gallery images from the join table
        $gallery = $this->connection->fetchAllAssociative(
            'SELECT image_id, dish_id AS location_id FROM dinnerclub_dish_image',
        );
        foreach ($gallery as $row) {
            $key = $row['image_id'] . ':' . $row['location_id'];
            $pairs[$key] = ['imageId' => (int) $row['image_id'], 'locationId' => (int) $row['location_id']];
        }

        // Preview images from the dish table
        $previews = $this->connection->fetchAllAssociative(
            'SELECT preview_image_id AS image_id, id AS location_id FROM dish WHERE preview_image_id IS NOT NULL',
        );
        foreach ($previews as $row) {
            $key = $row['image_id'] . ':' . $row['location_id'];
            $pairs[$key] = ['imageId' => (int) $row['image_id'], 'locationId' => (int) $row['location_id']];
        }

        return array_values($pairs);
    }
}
