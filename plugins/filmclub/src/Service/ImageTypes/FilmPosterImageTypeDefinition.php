<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Service\Media\ImageTypes\AbstractImageTypeDefinition;
use Doctrine\DBAL\Connection;
use Plugin\Filmclub\Repository\FilmRepository;

final class FilmPosterImageTypeDefinition extends AbstractImageTypeDefinition
{
    public function __construct(
        ImageLocationRepository $repo,
        Connection $connection,
        private readonly FilmRepository $filmRepository,
    ) {
        parent::__construct($repo, $connection);
    }

    public function getType(): ImageType
    {
        return ImageType::PluginFilmclubPoster;
    }

    protected function sizes(): array
    {
        return [[400, 600], [200, 300], [100, 150]];
    }

    public function fitMode(): ImageFitMode
    {
        return ImageFitMode::Fit;
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_plugin_filmclub_film_show', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT poster_image_id AS image_id, id AS location_id FROM film WHERE poster_image_id IS NOT NULL');

        return array_map(static fn(array $r) => [
            'imageId' => (int) $r['image_id'],
            'locationId' => (int) $r['location_id'],
        ], $rows);
    }

    public function locate(Image $image): ?array
    {
        $film = $this->filmRepository->findOneBy(['posterImage' => $image]);
        if ($film === null) {
            return null;
        }

        return [
            'label' => sprintf('Film poster: %s', $film->getTitle() ?? ''),
            'route' => 'app_plugin_filmclub_film_show',
            'params' => ['id' => $film->getId()],
        ];
    }
}
