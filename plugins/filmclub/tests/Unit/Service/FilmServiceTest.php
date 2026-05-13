<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Service;

use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Service\Media\ImageLocationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\ExternalSource;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Entity\FilmSuggestion;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Repository\FilmRepository;
use Plugin\Filmclub\Repository\FilmSuggestionRepository;
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

        $suggestionRepo = $this->createStub(FilmSuggestionRepository::class);
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
            suggestionRepo: $suggestionRepo,
            groupFilter: $groupFilter,
            dispatcher: $dispatcher,
            posterImageService: $posterImageService,
            imageLocationService: $imageLocationService,
        );

        // Act
        $service->createFromMetadata($metadata, userId: 1, isManager: true);

        // Assert
        static::assertSame([EntityAction::CreateFilm], $dispatchedActions);
    }

    public function testCreateFromMetadataDispatchesCreateFilmAndSuggestionForNonManager(): void
    {
        // Arrange
        $filmRepo = $this->createStub(FilmRepository::class);
        $filmRepo->method('findByExternalId')->willReturn(null);

        $suggestionRepo = $this->createStub(FilmSuggestionRepository::class);
        $groupFilter = $this->createStub(FilmGroupFilterService::class);
        $posterImageService = $this->createStub(PosterImageService::class);
        $posterImageService->method('downloadAndSave')->willReturn(null);
        $imageLocationService = $this->createStub(ImageLocationService::class);

        $filmIdProp = new ReflectionProperty(Film::class, 'id');
        $suggestionIdProp = new ReflectionProperty(FilmSuggestion::class, 'id');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(
            static function (object $entity) use ($filmIdProp, $suggestionIdProp): void {
                if ($entity instanceof Film && $entity->getId() === null) {
                    $filmIdProp->setValue($entity, 1);
                }
                if ($entity instanceof FilmSuggestion && $entity->getId() === null) {
                    $suggestionIdProp->setValue($entity, 10);
                }
            },
        );

        $dispatchedActions = [];
        $dispatcher = $this->createMock(EntityActionDispatcher::class);
        $dispatcher->expects(static::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (EntityAction $action, int $id) use (&$dispatchedActions): void {
                $dispatchedActions[] = $action;
            });

        $metadata = new FilmMetadata(
            externalId: 'tt456',
            source: ExternalSource::Tmdb,
            title: 'Test Film Non-Manager',
        );

        $service = $this->makeService(
            em: $em,
            filmRepo: $filmRepo,
            suggestionRepo: $suggestionRepo,
            groupFilter: $groupFilter,
            dispatcher: $dispatcher,
            posterImageService: $posterImageService,
            imageLocationService: $imageLocationService,
        );

        // Act
        $service->createFromMetadata($metadata, userId: 2, isManager: false);

        // Assert
        static::assertContains(EntityAction::CreateFilm, $dispatchedActions);
        static::assertContains(EntityAction::CreateFilmSuggestion, $dispatchedActions);
    }

    public function testGetApprovedListPassesNullFilterWhenNoOpinion(): void
    {
        // Arrange
        $groupFilter = $this->createStub(FilmGroupFilterService::class);
        $groupFilter->method('getAllowedFilmIds')->willReturn(null);

        $filmRepo = $this->createMock(FilmRepository::class);
        $filmRepo->expects(static::once())
            ->method('findApproved')
            ->with(null)
            ->willReturn([]);

        $service = $this->makeService(filmRepo: $filmRepo, groupFilter: $groupFilter);

        // Act
        $service->getApprovedList();

        // Assert — mock verifies findApproved(null) was called
    }

    public function testGetApprovedListPassesEmptyArrayWhenBlockAll(): void
    {
        // Arrange
        $groupFilter = $this->createStub(FilmGroupFilterService::class);
        $groupFilter->method('getAllowedFilmIds')->willReturn([]);

        $filmRepo = $this->createMock(FilmRepository::class);
        $filmRepo->expects(static::once())
            ->method('findApproved')
            ->with([])
            ->willReturn([]);

        $service = $this->makeService(filmRepo: $filmRepo, groupFilter: $groupFilter);

        // Act
        $result = $service->getApprovedList();

        // Assert
        static::assertSame([], $result);
    }

    public function testRejectDispatchesDeleteFilm(): void
    {
        // Arrange
        $film = $this->createStub(Film::class);
        $film->method('isApproved')->willReturn(false);

        $filmRepo = $this->createStub(FilmRepository::class);
        $filmRepo->method('find')->willReturn($film);

        $suggestionRepo = $this->createStub(FilmSuggestionRepository::class);
        $suggestionRepo->method('findBy')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $dispatcher = $this->createMock(EntityActionDispatcher::class);
        $dispatcher->expects(static::once())
            ->method('dispatch')
            ->with(EntityAction::DeleteFilm, 99);

        $service = $this->makeService(
            em: $em,
            filmRepo: $filmRepo,
            suggestionRepo: $suggestionRepo,
            dispatcher: $dispatcher,
        );

        // Act
        $service->reject(filmId: 99);

        // Assert — mock verifies dispatch called with DeleteFilm
    }

    private function makeService(
        ?EntityManagerInterface $em = null,
        ?FilmRepository $filmRepo = null,
        ?FilmSuggestionRepository $suggestionRepo = null,
        ?FilmGroupFilterService $groupFilter = null,
        ?EntityActionDispatcher $dispatcher = null,
        ?PosterImageService $posterImageService = null,
        ?ImageLocationService $imageLocationService = null,
    ): FilmService {
        return new FilmService(
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            filmRepo: $filmRepo ?? $this->createStub(FilmRepository::class),
            suggestionRepo: $suggestionRepo ?? $this->createStub(FilmSuggestionRepository::class),
            groupFilter: $groupFilter ?? $this->createStub(FilmGroupFilterService::class),
            dispatcher: $dispatcher ?? $this->createStub(EntityActionDispatcher::class),
            posterImageService: $posterImageService ?? $this->createStub(PosterImageService::class),
            imageLocationService: $imageLocationService ?? $this->createStub(ImageLocationService::class),
        );
    }
}
