<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Service;

use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Service\Media\ImageLocationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\ExternalSource;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Repository\FilmRepository;
use Plugin\Filmclub\Service\FilmMetadata;
use Plugin\Filmclub\Service\FilmService;
use Plugin\Filmclub\Service\PosterImageService;
use ReflectionProperty;

class FilmServiceTest extends TestCase
{
    public function testCreateFromMetadataDispatchesCreateFilm(): void
    {
        // Arrange
        $filmRepo = $this->createStub(FilmRepository::class);
        $filmRepo->method('findByExternalId')->willReturn(null);

        $groupFilter = $this->createStub(FilmGroupFilterService::class);
        $posterImageService = $this->createStub(PosterImageService::class);
        $posterImageService->method('downloadAndSave')->willReturn(null);
        $imageLocationService = $this->createStub(ImageLocationService::class);

        $idProp = new ReflectionProperty(Film::class, 'id');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use ($idProp): void {
            if ($entity instanceof Film && $entity->getId() === null) {
                $idProp->setValue($entity, 1);
            }
        });

        $dispatchedActions = [];
        $dispatcher = $this->createMock(EntityActionDispatcher::class);
        $dispatcher->expects(static::once())
            ->method('dispatch')
            ->willReturnCallback(static function (EntityAction $action, int $id) use (&$dispatchedActions): void {
                $dispatchedActions[] = $action;
            });

        $metadata = new FilmMetadata(
            externalId: 'tt123',
            source: ExternalSource::Tmdb,
            title: 'Test Film',
        );

        $service = $this->makeService(
            em: $em,
            filmRepo: $filmRepo,
            groupFilter: $groupFilter,
            dispatcher: $dispatcher,
            posterImageService: $posterImageService,
            imageLocationService: $imageLocationService,
        );

        // Act
        $service->createFromMetadata($metadata, userId: 1);

        // Assert
        static::assertSame([EntityAction::CreateFilm], $dispatchedActions);
    }

    public function testGetListPassesNullFilterWhenNoOpinion(): void
    {
        // Arrange
        $groupFilter = $this->createStub(FilmGroupFilterService::class);
        $groupFilter->method('getAllowedFilmIds')->willReturn(null);

        $filmRepo = $this->createMock(FilmRepository::class);
        $filmRepo->expects(static::once())
            ->method('findAll')
            ->with(null)
            ->willReturn([]);

        $service = $this->makeService(filmRepo: $filmRepo, groupFilter: $groupFilter);

        // Act
        $service->getList();

        // Assert — mock verifies findAll(null) was called
    }

    public function testGetListPassesEmptyArrayWhenBlockAll(): void
    {
        // Arrange
        $groupFilter = $this->createStub(FilmGroupFilterService::class);
        $groupFilter->method('getAllowedFilmIds')->willReturn([]);

        $filmRepo = $this->createMock(FilmRepository::class);
        $filmRepo->expects(static::once())
            ->method('findAll')
            ->with([])
            ->willReturn([]);

        $service = $this->makeService(filmRepo: $filmRepo, groupFilter: $groupFilter);

        // Act
        $result = $service->getList();

        // Assert
        static::assertSame([], $result);
    }

    private function makeService(
        ?EntityManagerInterface $em = null,
        ?FilmRepository $filmRepo = null,
        ?FilmGroupFilterService $groupFilter = null,
        ?EntityActionDispatcher $dispatcher = null,
        ?PosterImageService $posterImageService = null,
        ?ImageLocationService $imageLocationService = null,
    ): FilmService {
        return new FilmService(
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            filmRepo: $filmRepo ?? $this->createStub(FilmRepository::class),
            groupFilter: $groupFilter ?? $this->createStub(FilmGroupFilterService::class),
            dispatcher: $dispatcher ?? $this->createStub(EntityActionDispatcher::class),
            posterImageService: $posterImageService ?? $this->createStub(PosterImageService::class),
            imageLocationService: $imageLocationService ?? $this->createStub(ImageLocationService::class),
        );
    }
}
