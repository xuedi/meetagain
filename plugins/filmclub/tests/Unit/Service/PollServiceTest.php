<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Service;

use App\Entity\Event;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Entity\FilmPoll;
use Plugin\Filmclub\Entity\PollStatus;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Repository\FilmPollRepository;
use Plugin\Filmclub\Repository\FilmPollVoteRepository;
use Plugin\Filmclub\Service\PollService;
use Plugin\Filmclub\Service\WishlistService;
use Plugin\Filmclub\ValueObject\PollClosure;
use ReflectionProperty;
use RuntimeException;

class PollServiceTest extends TestCase
{
    public function testCreateRequiresAtLeastOneFilm(): void
    {
        // Arrange
        $event = $this->createStub(Event::class);
        $service = $this->makeService();

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $service->create($event, [], 7, 1);
    }

    public function testCreateAttachesFilmsToNewPoll(): void
    {
        // Arrange
        $event = $this->createStub(Event::class);

        $film1 = $this->createStub(Film::class);
        $film2 = $this->createStub(Film::class);

        $pollIdProp = new ReflectionProperty(FilmPoll::class, 'id');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use ($pollIdProp): void {
            if ($entity instanceof FilmPoll && $entity->getId() === null) {
                $pollIdProp->setValue($entity, 1);
            }
        });

        $service = $this->makeService(em: $em);

        // Act
        $poll = $service->create($event, [$film1, $film2], 7, 42);

        // Assert
        static::assertSame(2, $poll->getFilms()->count());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCloseWithSingleWinningFilmSetsWinningFilm(): void
    {
        // Arrange
        $film1 = $this->createStub(Film::class);
        $film1->method('getId')->willReturn(1);

        $film2 = $this->createStub(Film::class);
        $film2->method('getId')->willReturn(2);

        $poll = $this->createPartialMock(FilmPoll::class, ['getId', 'getStatus', 'getFilms']);
        $poll->method('getId')->willReturn(1);
        $poll->method('getStatus')->willReturn(PollStatus::Active);
        $poll->method('getFilms')->willReturn(new ArrayCollection([$film1, $film2]));

        $voteRepo = $this->createStub(FilmPollVoteRepository::class);
        $voteRepo->method('countVotesPerFilm')->willReturn([1 => 3, 2 => 1]);

        $em = $this->createStub(EntityManagerInterface::class);

        $service = $this->makeService(em: $em, voteRepo: $voteRepo);

        // Act
        $closure = $service->close($poll);

        // Assert
        static::assertInstanceOf(PollClosure::class, $closure);
        static::assertSame($film1, $closure->winningFilm);
        static::assertSame([], $closure->tiedFilms);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCloseWithTiedFilmsRecordsTiedFilmIds(): void
    {
        // Arrange
        $film1 = $this->createStub(Film::class);
        $film1->method('getId')->willReturn(1);

        $film2 = $this->createStub(Film::class);
        $film2->method('getId')->willReturn(2);

        $poll = $this->createPartialMock(FilmPoll::class, ['getId', 'getStatus', 'getFilms']);
        $poll->method('getId')->willReturn(2);
        $poll->method('getStatus')->willReturn(PollStatus::Active);
        $poll->method('getFilms')->willReturn(new ArrayCollection([$film1, $film2]));

        $voteRepo = $this->createStub(FilmPollVoteRepository::class);
        $voteRepo->method('countVotesPerFilm')->willReturn([1 => 2, 2 => 2]);

        $em = $this->createStub(EntityManagerInterface::class);

        $service = $this->makeService(em: $em, voteRepo: $voteRepo);

        // Act
        $closure = $service->close($poll);

        // Assert
        static::assertNull($closure->winningFilm);
        static::assertCount(2, $closure->tiedFilms);
    }

    public function testCommitOutcomeWritesSelectionAndCallsWishlistOutcome(): void
    {
        // Arrange
        $film = $this->createStub(Film::class);
        $film->method('getId')->willReturn(7);

        $poll = $this->createStub(FilmPoll::class);
        $poll->method('getEventId')->willReturn(42);
        $poll->method('getCreatedBy')->willReturn(1);

        $em = $this->createStub(EntityManagerInterface::class);

        $wishlistService = $this->createMock(WishlistService::class);
        $wishlistService->expects(static::once())
            ->method('onPollOutcome')
            ->with($film);

        $service = $this->makeService(em: $em, wishlistService: $wishlistService);

        // Act
        $service->commitOutcome($poll, $film);

        // Assert — mock expectations verified
    }

    private function makeService(
        ?EntityManagerInterface $em = null,
        ?FilmPollRepository $pollRepo = null,
        ?FilmPollVoteRepository $voteRepo = null,
        ?WishlistService $wishlistService = null,
        ?FilmGroupFilterService $groupFilter = null,
    ): PollService {
        return new PollService(
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            pollRepo: $pollRepo ?? $this->createStub(FilmPollRepository::class),
            voteRepo: $voteRepo ?? $this->createStub(FilmPollVoteRepository::class),
            wishlistService: $wishlistService ?? $this->createStub(WishlistService::class),
            groupFilter: $groupFilter ?? $this->createStub(FilmGroupFilterService::class),
        );
    }
}
