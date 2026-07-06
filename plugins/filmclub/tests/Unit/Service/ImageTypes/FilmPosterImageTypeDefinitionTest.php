<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Service\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Repository\FilmRepository;
use Plugin\Filmclub\Service\ImageTypes\FilmPosterImageTypeDefinition;

class FilmPosterImageTypeDefinitionTest extends TestCase
{
    private function repo(): ImageLocationRepository
    {
        return $this->createStub(ImageLocationRepository::class);
    }

    public function testIdentitySizesFitModeAndEditLink(): void
    {
        $definition = new FilmPosterImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $this->createStub(FilmRepository::class));

        static::assertSame(ImageType::PluginFilmclubPoster, $definition->getType());
        static::assertSame(ImageFitMode::Fit, $definition->fitMode());
        static::assertSame([[400, 600], [200, 300], [100, 150], [100, 100], [50, 50]], $definition->thumbnailSizes());
        static::assertSame(['route' => 'app_plugin_filmclub_film_show', 'params' => ['id' => 3]], $definition->getEditLink(3));
    }

    public function testDiscoverImageIds(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([['image_id' => '5', 'location_id' => '1']]);

        $definition = new FilmPosterImageTypeDefinition($this->repo(), $conn, $this->createStub(FilmRepository::class));

        static::assertSame([['imageId' => 5, 'locationId' => 1]], $definition->discoverImageIds());
    }

    public function testLocateResolvesFilm(): void
    {
        $film = $this->createStub(Film::class);
        $film->method('getTitle')->willReturn('Blade Runner');
        $film->method('getId')->willReturn(6);

        $filmRepo = $this->createStub(FilmRepository::class);
        $filmRepo->method('findOneBy')->willReturn($film);

        $definition = new FilmPosterImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $filmRepo);

        static::assertSame(
            ['label' => 'Film poster: Blade Runner', 'route' => 'app_plugin_filmclub_film_show', 'params' => ['id' => 6]],
            $definition->locate($this->createStub(Image::class)),
        );
    }

    public function testLocateReturnsNullWhenNoFilm(): void
    {
        $filmRepo = $this->createStub(FilmRepository::class);
        $filmRepo->method('findOneBy')->willReturn(null);

        $definition = new FilmPosterImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $filmRepo);

        static::assertNull($definition->locate($this->createStub(Image::class)));
    }
}
