<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Service;

use App\Item\Tag\TagService;
use App\Repository\ItemTagAssignmentRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Entity\TrainerCard;
use Plugin\Glossary\Enum\CardState;
use Plugin\Glossary\Enum\Direction;
use Plugin\Glossary\Repository\TrainerCardRepository;
use Plugin\Glossary\Repository\TrainerDayRepository;
use Plugin\Glossary\Service\ProgressService;
use Plugin\Glossary\Service\TrainerService;
use ReflectionProperty;

class ProgressServiceTest extends TestCase
{
    private const int USER = 7;

    public function testAWordCountsAsLearnedOnlyWhenEveryAnsweredDirectionIsMature(): void
    {
        // Arrange
        $service = $this->service([
            $this->answered($this->card(1, CardState::Review, 30)),
            $this->answered($this->card(1, CardState::Review, 21, Direction::DefinitionToTerm)),
            $this->answered($this->card(2, CardState::Review, 30)),
            $this->answered($this->card(2, CardState::Review, 6, Direction::DefinitionToTerm)),
            $this->answered($this->card(3, CardState::Review, 40)),
            $this->card(3, CardState::New, 0, Direction::DefinitionToTerm),
            $this->answered($this->card(4, CardState::New, 0)),
        ]);

        // Act
        $summary = $service->summary(self::USER, new DateTimeImmutable('2026-09-12 10:00'));

        // Assert
        self::assertSame(4, $summary['started']);
        self::assertSame(2, $summary['learned']);
        self::assertSame(5, $summary['total']);
    }

    public function testUpcomingCountsOverdueWordsTodayAndSkipsSuspendedCards(): void
    {
        // Arrange
        $overdue = $this->card(1, CardState::Review, 6)->setDueAt(new DateTimeImmutable('2026-09-10 09:00'));
        $sameWordOtherDirection = $this->card(1, CardState::Review, 6, Direction::DefinitionToTerm)->setDueAt(new DateTimeImmutable('2026-09-12 08:00'));
        $inTwoDays = $this->card(2, CardState::Review, 6)->setDueAt(new DateTimeImmutable('2026-09-14 09:00'));
        $suspended = $this->card(3, CardState::Review, 6)->setDueAt(new DateTimeImmutable('2026-09-12 09:00'));
        $suspended->setSuspended(true);
        $nextMonth = $this->card(4, CardState::Review, 30)->setDueAt(new DateTimeImmutable('2026-10-12 09:00'));
        $service = $this->service([$overdue, $sameWordOtherDirection, $inTwoDays, $suspended, $nextMonth]);

        // Act
        $upcoming = $service->details(self::USER, 'en', new DateTimeImmutable('2026-09-12 10:00'))['upcoming'];

        // Assert
        self::assertSame(
            [
                '2026-09-12' => 1,
                '2026-09-13' => 0,
                '2026-09-14' => 1,
                '2026-09-15' => 0,
                '2026-09-16' => 0,
                '2026-09-17' => 0,
                '2026-09-18' => 0,
            ],
            $upcoming,
        );
    }

    public function testTheMostMissedWordsComeFirstAndWordsNeverMissedStayOut(): void
    {
        // Arrange
        $service = $this->service([
            $this->answered($this->card(1, CardState::Review, 6), true, true, true, false),
            $this->answered($this->card(2, CardState::Relearning, 1), false, false),
            $this->answered($this->card(3, CardState::Review, 6), true, true),
        ]);

        // Act
        $hardest = $service->details(self::USER, 'en', new DateTimeImmutable('2026-09-12 10:00'))['hardest'];

        // Assert
        self::assertSame([[2, 2, 2], [1, 4, 1]], array_map(static fn(array $row): array => [$row['entry']->getId(), $row['seen'], $row['missed']], $hardest));
    }

    public function testTheStreakStillCountsWhenTodayHasNoAnswerYet(): void
    {
        // Arrange
        $service = $this->service([], activeDays: ['2026-09-11', '2026-09-10', '2026-09-08']);

        // Act
        $streak = $service->streak(self::USER, new DateTimeImmutable('2026-09-12 10:00'));

        // Assert
        self::assertSame(2, $streak);
    }

    /** @return iterable<string, array{int, int, ?string}> */
    public static function bandCases(): iterable
    {
        yield 'two learners stay silent even when both lapsed' => [2, 2, null];
        yield 'one lapse among ten learners reads as easy' => [10, 1, 'glossary_trainer.band_easy'];
        yield 'two lapses among five learners reads as hard' => [5, 2, 'glossary_trainer.band_hard'];
    }

    #[DataProvider('bandCases')]
    public function testTheDifficultyBandNeedsThreeLearners(int $learners, int $lapsed, ?string $expected): void
    {
        // Arrange
        $service = $this->service([], lapses: ['learners' => $learners, 'lapsed' => $lapsed]);

        // Act
        $band = $service->difficultyBand(1);

        // Assert
        self::assertSame($expected, $band);
    }

    /**
     * @param list<TrainerCard> $cards
     * @param list<string> $activeDays
     * @param array{learners: int, lapsed: int} $lapses
     */
    private function service(array $cards, array $activeDays = [], array $lapses = ['learners' => 0, 'lapsed' => 0]): ProgressService
    {
        $trainer = $this->createStub(TrainerService::class);
        $trainer->method('visibleIds')->willReturn([1, 2, 3, 4, 5]);
        $trainer->method('scopeIds')->willReturn([]);
        $trainer->method('entries')->willReturnCallback(fn(array $ids): array => array_map($this->entry(...), $ids));

        $cardRepo = $this->createStub(TrainerCardRepository::class);
        $cardRepo->method('findBy')->willReturn($cards);
        $cardRepo->method('lapseCounts')->willReturn($lapses);

        $dayRepo = $this->createStub(TrainerDayRepository::class);
        $dayRepo->method('activeDays')->willReturn($activeDays);
        $dayRepo->method('findBy')->willReturn([]);

        return new ProgressService($trainer, $cardRepo, $dayRepo, $this->createStub(TagService::class), $this->createStub(ItemTagAssignmentRepository::class));
    }

    private function card(int $glossaryId, CardState $state, int $intervalDays, Direction $direction = Direction::TermToDefinition): TrainerCard
    {
        $card = new TrainerCard(self::USER, $this->entry($glossaryId), $direction, new DateTimeImmutable('2026-08-01'));
        $card->setState($state)->setIntervalDays($intervalDays);

        return $card;
    }

    private function answered(TrainerCard $card, bool ...$results): TrainerCard
    {
        foreach ($results === [] ? [true] : $results as $correct) {
            $card->recordAnswer($correct, new DateTimeImmutable('2026-09-01'));
        }

        return $card;
    }

    private function entry(int $id): Glossary
    {
        $entry = new Glossary()->setPhrase('word ' . $id);
        new ReflectionProperty(Glossary::class, 'id')->setValue($entry, $id);

        return $entry;
    }
}
