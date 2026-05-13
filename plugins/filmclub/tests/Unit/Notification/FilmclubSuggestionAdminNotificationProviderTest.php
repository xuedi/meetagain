<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Notification;

use App\Service\Notification\Admin\AdminNotificationItem;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Entity\FilmSuggestion;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Notification\FilmclubSuggestionAdminNotificationProvider;
use Plugin\Filmclub\Repository\FilmSuggestionRepository;
use Plugin\Filmclub\Service\SuggestionService;
use Symfony\Contracts\Translation\TranslatorInterface;

class FilmclubSuggestionAdminNotificationProviderTest extends TestCase
{
    public function testGetSectionReturnsTranslatedString(): void
    {
        // Arrange
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Filmclub suggestions');

        $provider = $this->makeProvider(translator: $translator);

        // Act
        $result = $provider->getSection();

        // Assert
        static::assertSame('Filmclub suggestions', $result);
    }

    public function testGetPendingItemsReturnsEmptyWhenNoSuggestions(): void
    {
        // Arrange
        $suggestionService = $this->createStub(SuggestionService::class);
        $suggestionService->method('getPendingSuggestions')->willReturn([]);

        $provider = $this->makeProvider(suggestionService: $suggestionService);

        // Act
        $result = $provider->getPendingItems();

        // Assert
        static::assertSame([], $result);
    }

    public function testGetPendingItemsReturnsOneItemPerSuggestion(): void
    {
        // Arrange
        $film = $this->createStub(Film::class);
        $film->method('getTitle')->willReturn('Dune');
        $film->method('getId')->willReturn(42);

        $suggestion = $this->createStub(FilmSuggestion::class);
        $suggestion->method('getFilm')->willReturn($film);

        $suggestionService = $this->createStub(SuggestionService::class);
        $suggestionService->method('getPendingSuggestions')->willReturn([$suggestion]);

        $provider = $this->makeProvider(suggestionService: $suggestionService);

        // Act
        $result = $provider->getPendingItems();

        // Assert
        static::assertCount(1, $result);
        static::assertInstanceOf(AdminNotificationItem::class, $result[0]);
        static::assertSame('Dune', $result[0]->label);
    }

    public function testGetLatestPendingAtDelegatesToRepository(): void
    {
        // Arrange
        $groupFilter = $this->createStub(FilmGroupFilterService::class);
        $groupFilter->method('getAllowedSuggestionIds')->willReturn(null);

        $expected = new DateTimeImmutable('2026-01-01');

        $suggestionRepository = $this->createStub(FilmSuggestionRepository::class);
        $suggestionRepository->method('getLatestPendingAt')->willReturn($expected);

        $provider = $this->makeProvider(
            suggestionRepository: $suggestionRepository,
            groupFilter: $groupFilter,
        );

        // Act
        $result = $provider->getLatestPendingAt();

        // Assert
        static::assertSame($expected, $result);
    }

    private function makeProvider(
        ?SuggestionService $suggestionService = null,
        ?FilmSuggestionRepository $suggestionRepository = null,
        ?FilmGroupFilterService $groupFilter = null,
        ?TranslatorInterface $translator = null,
    ): FilmclubSuggestionAdminNotificationProvider {
        return new FilmclubSuggestionAdminNotificationProvider(
            suggestionService: $suggestionService ?? $this->createStub(SuggestionService::class),
            suggestionRepository: $suggestionRepository ?? $this->createStub(FilmSuggestionRepository::class),
            groupFilter: $groupFilter ?? $this->createStub(FilmGroupFilterService::class),
            translator: $translator ?? $this->createStub(TranslatorInterface::class),
        );
    }
}
