<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Service;

use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Entity\FilmSuggestion;
use Plugin\Filmclub\Entity\SuggestionStatus;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Repository\FilmSuggestionRepository;
use Plugin\Filmclub\Service\SuggestionService;
use RuntimeException;

class SuggestionServiceTest extends TestCase
{
    public function testWithdrawDispatchesDeleteFilmSuggestion(): void
    {
        // Arrange
        $suggestion = $this->createStub(FilmSuggestion::class);
        $suggestion->method('getId')->willReturn(42);
        $suggestion->method('getSuggestedBy')->willReturn(1);
        $suggestion->method('getStatus')->willReturn(SuggestionStatus::Pending);

        $suggestionRepo = $this->createStub(FilmSuggestionRepository::class);
        $suggestionRepo->method('find')->willReturn($suggestion);

        $em = $this->createStub(EntityManagerInterface::class);
        $groupFilter = $this->createStub(FilmGroupFilterService::class);

        $dispatcher = $this->createMock(EntityActionDispatcher::class);
        $dispatcher->expects(static::once())
            ->method('dispatch')
            ->with(EntityAction::DeleteFilmSuggestion, 42);

        $service = $this->makeService(
            em: $em,
            suggestionRepo: $suggestionRepo,
            groupFilter: $groupFilter,
            dispatcher: $dispatcher,
        );

        // Act
        $service->withdraw(suggestionId: 42, userId: 1);

        // Assert — mock verifies dispatch called with DeleteFilmSuggestion and id 42
    }

    public function testGetPendingSuggestionsPassesBlockAllFilter(): void
    {
        // Arrange
        $groupFilter = $this->createStub(FilmGroupFilterService::class);
        $groupFilter->method('getAllowedSuggestionIds')->willReturn([]);

        $suggestionRepo = $this->createMock(FilmSuggestionRepository::class);
        $suggestionRepo->expects(static::once())
            ->method('findAllPending')
            ->with([])
            ->willReturn([]);

        $service = $this->makeService(suggestionRepo: $suggestionRepo, groupFilter: $groupFilter);

        // Act
        $result = $service->getPendingSuggestions();

        // Assert
        static::assertSame([], $result);
    }

    public function testSuggestThrowsWhenFilmNotApproved(): void
    {
        // Arrange
        $film = $this->createStub(Film::class);
        $film->method('isApproved')->willReturn(false);

        $service = $this->makeService();

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('filmclub_suggestion.flash_not_approved');

        $service->suggest($film, userId: 1);
    }

    private function makeService(
        ?EntityManagerInterface $em = null,
        ?FilmSuggestionRepository $suggestionRepo = null,
        ?FilmGroupFilterService $groupFilter = null,
        ?EntityActionDispatcher $dispatcher = null,
    ): SuggestionService {
        return new SuggestionService(
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            suggestionRepo: $suggestionRepo ?? $this->createStub(FilmSuggestionRepository::class),
            groupFilter: $groupFilter ?? $this->createStub(FilmGroupFilterService::class),
            dispatcher: $dispatcher ?? $this->createStub(EntityActionDispatcher::class),
        );
    }
}
