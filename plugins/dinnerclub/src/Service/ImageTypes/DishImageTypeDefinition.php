<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Service\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Service\Media\ImageTypes\AbstractImageTypeDefinition;
use Doctrine\DBAL\Connection;
use Plugin\Dinnerclub\Repository\DishRepository;

/**
 * PluginDish covers two image sources that share one ImageType - dish gallery images and the dish
 * preview image. Discovery merges both in a single pass so sync() sees the complete set; splitting
 * them into two definitions would make each delete the other's rows.
 */
final class DishImageTypeDefinition extends AbstractImageTypeDefinition
{
    public function __construct(
        ImageLocationRepository $repo,
        Connection $connection,
        private readonly DishRepository $dishRepository,
    ) {
        parent::__construct($repo, $connection);
    }

    public function getType(): ImageType
    {
        return ImageType::PluginDish;
    }

    protected function sizes(): array
    {
        return [[1024, 768], [600, 400], [400, 400], [350, 263]];
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'plugin_dinnerclub_item_show', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $pairs = [];

        // Gallery images from the join table
        $gallery = $this->connection->fetchAllAssociative('SELECT image_id, dish_id AS location_id FROM dinnerclub_dish_image');
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

    public function locate(Image $image): ?array
    {
        $dish = $this->dishRepository->findOneBy(['previewImage' => $image]);
        if ($dish === null) {
            $dishId = $this->connection->fetchOne('SELECT dish_id FROM dinnerclub_dish_image WHERE image_id = ? LIMIT 1', [$image->getId()]);
            $dish = $dishId !== false ? $this->dishRepository->find((int) $dishId) : null;
        }
        if ($dish === null) {
            return null;
        }

        return [
            'label' => sprintf('Dish: %s', $dish->getAnyTranslatedName()),
            'route' => 'plugin_dinnerclub_item_show',
            'params' => ['id' => $dish->getId()],
        ];
    }
}
