<?php declare(strict_types=1);

namespace Plugin\Films\Tests\Unit\Portability;

use App\Entity\User;
use App\Item\Portability\ItemImportContext;
use App\Item\Portability\PortableImageWriterInterface;
use App\Service\Media\ImageLocationService;
use App\Service\System\PortableImageImporter;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Films\Entity\ExternalSource;
use Plugin\Films\Entity\Film;
use Plugin\Films\Portability\FilmPortabilityContributor;
use Plugin\Films\Repository\FilmRepository;
use ReflectionProperty;

class FilmPortabilityContributorTest extends TestCase
{
    public function testExportCarriesTheExternalIdentity(): void
    {
        // Arrange
        $film = $this->film(3, 'Parasite', 2019);
        $film->setExternalId('496243');
        $film->setExternalSource(ExternalSource::Tmdb);
        $film->setGenres(['Drama']);

        $repo = $this->createStub(FilmRepository::class);
        $repo->method('findBy')->willReturn([$film]);

        $contributor = $this->contributor($this->createStub(EntityManagerInterface::class), $repo);

        // Act
        $rows = $contributor->exportItems([3], $this->createStub(PortableImageWriterInterface::class));

        // Assert
        self::assertSame(3, $rows[0]['ref']);
        self::assertSame('496243', $rows[0]['external_id']);
        self::assertSame('tmdb', $rows[0]['external_source']);
        self::assertSame(['Drama'], $rows[0]['genres']);
        self::assertNull($rows[0]['poster_image']);
    }

    public function testExternalIdentityMatchesAnExistingFilm(): void
    {
        // Arrange
        $existing = $this->film(77, 'Parasite', 2019);
        $criteria = null;
        $repo = $this->createStub(FilmRepository::class);
        $repo->method('findOneBy')->willReturnCallback(static function (array $by) use (&$criteria, $existing): Film {
            $criteria = $by;
            return $existing;
        });

        $contributor = $this->contributor($this->createStub(EntityManagerInterface::class), $repo);
        $rows = [['ref' => 3, 'title' => 'Parasite', 'year' => 2019, 'external_id' => '496243', 'external_source' => 'tmdb']];

        // Act
        $result = $contributor->importItems($rows, $this->context());

        // Assert
        self::assertSame(['externalSource' => ExternalSource::Tmdb, 'externalId' => '496243'], $criteria);
        self::assertSame([3 => 77], $result->refToItemId);
        self::assertSame(1, $result->matched);
    }

    public function testManualFilmFallsBackToTitleAndYear(): void
    {
        // Arrange
        $criteria = null;
        $repo = $this->createStub(FilmRepository::class);
        $repo->method('findOneBy')->willReturnCallback(static function (array $by) use (&$criteria): ?Film {
            $criteria = $by;
            return null;
        });

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity): void {
            if ($entity instanceof Film) {
                new ReflectionProperty(Film::class, 'id')->setValue($entity, 55);
            }
        });

        $contributor = $this->contributor($em, $repo);
        $rows = [['ref' => 3, 'title' => 'Home Movie', 'year' => 2024, 'external_source' => 'manual']];

        // Act
        $result = $contributor->importItems($rows, $this->context());

        // Assert
        self::assertSame(['title' => 'Home Movie', 'year' => 2024], $criteria);
        self::assertSame([3 => 55], $result->refToItemId);
        self::assertSame(1, $result->created);
    }

    private function film(int $id, string $title, int $year): Film
    {
        $film = new Film();
        new ReflectionProperty(Film::class, 'id')->setValue($film, $id);
        $film->setTitle($title);
        $film->setYear($year);
        $film->setCreatedBy(1);
        $film->setCreatedAt(new DateTimeImmutable());

        return $film;
    }

    private function context(): ItemImportContext
    {
        return new ItemImportContext($this->createStub(PortableImageImporter::class), '/tmp', new User());
    }

    private function contributor(EntityManagerInterface $em, FilmRepository $repo): FilmPortabilityContributor
    {
        return new FilmPortabilityContributor($em, $repo, $this->createStub(ImageLocationService::class));
    }
}
