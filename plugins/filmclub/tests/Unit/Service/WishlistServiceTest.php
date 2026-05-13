<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Entity\FilmPoll;
use Plugin\Filmclub\Entity\FilmSuggestion;
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

    public function testIncrementForLosersSkipsWinner(): void
    {
        // Arrange
        $winnerFilm = $this->createStub(Film::class);
        $winnerFilm->method('getId')->willReturn(1);

        $loserFilm = $this->createStub(Film::class);
        $loserFilm->method('getId')->willReturn(2);

        $winningSuggestion = $this->createStub(FilmSuggestion::class);
        $winningSuggestion->method('getFilm')->willReturn($winnerFilm);

        $loserSuggestion = $this->createStub(FilmSuggestion::class);
        $loserSuggestion->method('getFilm')->willReturn($loserFilm);

        $poll = $this->createStub(FilmPoll::class);
        $poll->method('getSuggestions')->willReturn(new ArrayCollection([$winningSuggestion, $loserSuggestion]));

        $calledWith = [];
        $wishlistRepo = $this->createStub(FilmWishlistEntryRepository::class);
        $wishlistRepo->method('findByFilmForIncrement')->willReturnCallback(
            static function (int $filmId) use (&$calledWith): array {
                $calledWith[] = $filmId;

                return [];
            },
        );

        $service = $this->makeService(wishlistRepo: $wishlistRepo);

        // Act
        $service->incrementForLosers($poll, $winnerFilm);

        // Assert
        static::assertNotContains(1, $calledWith, 'findByFilmForIncrement must not be called for the winner (film id 1)');
        static::assertContains(2, $calledWith, 'findByFilmForIncrement must be called for the loser (film id 2)');
    }

    private function makeService(
        ?EntityManagerInterface $em = null,
        ?FilmWishlistEntryRepository $wishlistRepo = null,
        ?FilmRepository $filmRepo = null,
        ?FilmGroupFilterService $groupFilter = null,
    ): WishlistService {
        return new WishlistService(
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            wishlistRepo: $wishlistRepo ?? $this->createStub(FilmWishlistEntryRepository::class),
            filmRepo: $filmRepo ?? $this->createStub(FilmRepository::class),
            groupFilter: $groupFilter ?? $this->createStub(FilmGroupFilterService::class),
        );
    }
}
