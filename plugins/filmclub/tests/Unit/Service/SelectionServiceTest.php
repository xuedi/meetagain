<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Service;

use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Repository\FilmSelectionRepository;
use Plugin\Filmclub\Service\SelectionService;
use Plugin\Filmclub\Service\WishlistService;
use RuntimeException;

class SelectionServiceTest extends TestCase
{
    public function testChooseDirectlyWritesSelectionAndAppliesWishlistOutcome(): void
    {
        // Arrange
        $event = $this->createStub(Event::class);
        $event->method('getId')->willReturn(5);

        $film = $this->createStub(Film::class);
        $film->method('getId')->willReturn(3);

        $selectionRepo = $this->createStub(FilmSelectionRepository::class);
        $selectionRepo->method('findByEvent')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);

        $wishlistService = $this->createMock(WishlistService::class);
        $wishlistService->expects(static::once())
            ->method('onPollOutcome')
            ->with($film);

        $service = $this->makeService(
            em: $em,
            selectionRepo: $selectionRepo,
            wishlistService: $wishlistService,
        );

        // Act
        $selection = $service->chooseDirectly($event, $film, userId: 1);

        // Assert
        static::assertSame(3, $selection->getFilm()->getId());
        static::assertSame(5, $selection->getEventId());
    }

    public function testChooseDirectlyThrowsWhenAlreadySelected(): void
    {
        // Arrange
        $event = $this->createStub(Event::class);
        $event->method('getId')->willReturn(5);

        $film = $this->createStub(Film::class);

        $existingSelection = $this->createStub(\Plugin\Filmclub\Entity\FilmSelection::class);

        $selectionRepo = $this->createStub(FilmSelectionRepository::class);
        $selectionRepo->method('findByEvent')->willReturn($existingSelection);

        $service = $this->makeService(selectionRepo: $selectionRepo);

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $service->chooseDirectly($event, $film, userId: 1);
    }

    private function makeService(
        ?EntityManagerInterface $em = null,
        ?FilmSelectionRepository $selectionRepo = null,
        ?FilmGroupFilterService $groupFilter = null,
        ?WishlistService $wishlistService = null,
    ): SelectionService {
        return new SelectionService(
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            selectionRepo: $selectionRepo ?? $this->createStub(FilmSelectionRepository::class),
            groupFilter: $groupFilter ?? $this->createStub(FilmGroupFilterService::class),
            wishlistService: $wishlistService ?? $this->createStub(WishlistService::class),
        );
    }
}
