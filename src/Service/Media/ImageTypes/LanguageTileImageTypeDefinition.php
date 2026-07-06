<?php declare(strict_types=1);

namespace App\Service\Media\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Repository\LanguageRepository;
use Doctrine\DBAL\Connection;

final class LanguageTileImageTypeDefinition extends AbstractImageTypeDefinition
{
    public function __construct(
        ImageLocationRepository $repo,
        Connection $connection,
        private readonly LanguageRepository $languageRepository,
    ) {
        parent::__construct($repo, $connection);
    }

    public function getType(): ImageType
    {
        return ImageType::LanguageTile;
    }

    protected function sizes(): array
    {
        return [[600, 400], [350, 233], [300, 200]];
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_admin_language_edit', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT tile_image_id AS image_id, id AS location_id FROM language WHERE tile_image_id IS NOT NULL');

        return array_map(static fn(array $r) => [
            'imageId' => (int) $r['image_id'],
            'locationId' => (int) $r['location_id'],
        ], $rows);
    }

    public function locate(Image $image): ?array
    {
        $language = $this->languageRepository->findOneBy(['tileImage' => $image]);
        if ($language === null) {
            return null;
        }

        return [
            'label' => sprintf('Language tile: %s', $language->getName()),
            'route' => 'app_admin_language_edit',
            'params' => ['id' => $language->getId()],
        ];
    }
}
