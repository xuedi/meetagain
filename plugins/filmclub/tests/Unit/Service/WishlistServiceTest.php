<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Service;

use App\Repository\EventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Entity\FilmWishlistEntry;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Repository\FilmRepository;
use Plugin\Filmclub\Repository\FilmWishlistEntryRepository;
use Plugin\Filmclub\Service\WishlistService;

class WishlistServiceTest extends TestCase
{
    public function testAddReturnsExistingEntryWhenAlreadyWishlisted(): void
    {
        // Arrange
        $existingEntry = $this->createStub(FilmWishlistEntry::class);

        $wishlistRepo = $this->createStub(FilmWishlistEntryRepository::class);
        $wishlistRepo->method('findByUserAndFilm')->willReturn($existingEntry);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(static::never())->method('persist');

        $film = $this->createStub(Film::class);
        $film->method('getId')->willReturn(1);

        $service = $this->makeService(em: $em, wishlistRepo: $wishlistRepo);

        // Act
        $result = $service->add($film, userId: 7);

        // Assert
        static::assertSame($existingEntry, $result);
    }

    public function testListForUserPassesFilteredIds(): void
    {
        // Arrange
        $groupFilter = $this->createStub(FilmGroupFilterService::class);
        $groupFilter->method('getAllowedWishlistEntryIds')->willReturn([5, 6]);

        $wishlistRepo = $this->createMock(FilmWishlistEntryRepository::class);
        $wishlistRepo->expects(static::once())
            ->method('findByUser')
            ->with(42, [5, 6])
            ->willReturn([]);

        $service = $this->makeService(wishlistRepo: $wishlistRepo, groupFilter: $groupFilter);

        // Act
        $service->listForUser(42);

        // Assert — mock verifies findByUser called with correct userId and allowedIds
    }

    public function testOnPollOutcomeIncrementsAllGroupEntriesExceptWinner(): void
    {
        // Arrange
        $winner = $this->createStub(Film::class);
        $winner->method('getId')->willReturn(5);

        $groupFilter = $this->createStub(FilmGroupFilterService::class);
        $groupFilter->method('getAllowedWishlistEntryIds')->willReturn([1, 2, 3]);

        $wishlistRepo = $this->createMock(FilmWishlistEntryRepository::class);
        $wishlistRepo->expects(static::once())
            ->method('incrementAllExceptWinner')
            ->with(5, [1, 2, 3]);
        $wishlistRepo->expects(static::once())
            ->method('deleteByFilmInGroup')
            ->with(5, [1, 2, 3]);

        $service = $this->makeService(wishlistRepo: $wishlistRepo, groupFilter: $groupFilter);

        // Act
        $service->onPollOutcome($winner);

        // Assert — mock expectations verified
    }

    public function testOnPollOutcomeDeletesEntriesForWinningFilmAcrossGroup(): void
    {
        // Arrange
        $winner = $this->createStub(Film::class);
        $winner->method('getId')->willReturn(10);

        $groupFilter = $this->createStub(FilmGroupFilterService::class);
        $groupFilter->method('getAllowedWishlistEntryIds')->willReturn(null);

        $wishlistRepo = $this->createMock(FilmWishlistEntryRepository::class);
        $wishlistRepo->expects(static::once())
            ->method('deleteByFilmInGroup')
            ->with(10, null);
        $wishlistRepo->method('incrementAllExceptWinner');

        $service = $this->makeService(wishlistRepo: $wishlistRepo, groupFilter: $groupFilter);

        // Act
        $service->onPollOutcome($winner);

        // Assert — mock expectations verified
    }

    public function testCountPastEventsInGroupSinceReturnsZeroWhenAllowedIdsEmpty(): void
    {
        // Arrange
        $groupFilter = $this->createStub(FilmGroupFilterService::class);
        $groupFilter->method('getAllowedEventIds')->willReturn([]);

        $service = $this->makeService(groupFilter: $groupFilter);

        // Act
        $result = $service->countPastEventsInGroupSince(new DateTimeImmutable());

        // Assert
        static::assertSame(0, $result);
    }

    private function makeService(
        ?EntityManagerInterface $em = null,
        ?FilmWishlistEntryRepository $wishlistRepo = null,
        ?FilmRepository $filmRepo = null,
        ?FilmGroupFilterService $groupFilter = null,
        ?EventRepository $eventRepo = null,
    ): WishlistService {
        return new WishlistService(
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            wishlistRepo: $wishlistRepo ?? $this->createStub(FilmWishlistEntryRepository::class),
            filmRepo: $filmRepo ?? $this->createStub(FilmRepository::class),
            groupFilter: $groupFilter ?? $this->createStub(FilmGroupFilterService::class),
            eventRepo: $eventRepo ?? $this->createStub(EventRepository::class),
        );
    }
}
