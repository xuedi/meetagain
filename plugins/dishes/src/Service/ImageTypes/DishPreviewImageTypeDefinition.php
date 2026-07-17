<?php declare(strict_types=1);

namespace Plugin\Dishes\Service\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Service\Media\ImageTypes\AbstractImageTypeDefinition;
use Doctrine\DBAL\Connection;
use Plugin\Dishes\Repository\DishRepository;

/**
 * PluginDishesPreview covers two image sources that share one ImageType - dish gallery images and
 * the dish preview image. Discovery merges both in a single pass so sync() sees the complete set;
 * splitting them into two definitions would make each delete the other's rows.
 */
final class DishPreviewImageTypeDefinition extends AbstractImageTypeDefinition
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
        return ImageType::PluginDishesPreview;
    }

    protected function sizes(): array
    {
        return [[1024, 768], [600, 400], [400, 400], [350, 263]];
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_plugin_dishes_dish_show', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $pairs = [];

        $gallery = $this->connection->fetchAllAssociative('SELECT image_id, dish_id AS location_id FROM plg_dishes_dish_image');
        foreach ($gallery as $row) {
            $key = $row['image_id'] . ':' . $row['location_id'];
            $pairs[$key] = ['imageId' => (int) $row['image_id'], 'locationId' => (int) $row['location_id']];
        }

        $previews = $this->connection->fetchAllAssociative(
            'SELECT preview_image_id AS image_id, id AS location_id FROM plg_dishes_dish WHERE preview_image_id IS NOT NULL',
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
            $dishId = $this->connection->fetchOne('SELECT dish_id FROM plg_dishes_dish_image WHERE image_id = ? LIMIT 1', [$image->getId()]);
            $dish = $dishId !== false ? $this->dishRepository->find((int) $dishId) : null;
        }
        if ($dish === null) {
            return null;
        }

        return [
            'label' => sprintf('Dish: %s', $dish->getAnyTranslatedName()),
            'route' => 'app_plugin_dishes_dish_show',
            'params' => ['id' => $dish->getId()],
        ];
    }
}
