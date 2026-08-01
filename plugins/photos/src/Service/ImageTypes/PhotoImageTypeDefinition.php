<?php declare(strict_types=1);

namespace Plugin\Photos\Service\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Service\Media\ImageTypes\AbstractImageTypeDefinition;
use Doctrine\DBAL\Connection;
use Plugin\Photos\Repository\PhotoRepository;

final class PhotoImageTypeDefinition extends AbstractImageTypeDefinition
{
    public function __construct(
        ImageLocationRepository $repo,
        Connection $connection,
        private readonly PhotoRepository $photoRepository,
    ) {
        parent::__construct($repo, $connection);
    }

    public function getType(): ImageType
    {
        return ImageType::PluginPhotosPhoto;
    }

    protected function sizes(): array
    {
        return [[1600, self::FREE_AXIS], [1024, 768], [600, 400], [400, 400], [350, 263]];
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_plugin_photos_photo_show', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT image_id, id AS location_id FROM plg_photos_photo');

        return array_values(array_map(
            static fn(array $row): array => ['imageId' => (int) $row['image_id'], 'locationId' => (int) $row['location_id']],
            $rows,
        ));
    }

    public function locate(Image $image): ?array
    {
        $photo = $this->photoRepository->findOneBy(['image' => $image]);
        if ($photo === null) {
            return null;
        }

        $title = $photo->getAnyTranslatedTitle();

        return [
            'label' => sprintf('Photo: %s', $title !== '' ? $title : '#' . $photo->getId()),
            'route' => 'app_plugin_photos_photo_show',
            'params' => ['id' => $photo->getId()],
        ];
    }
}
