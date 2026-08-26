<?php declare(strict_types=1);

namespace Tests\Unit\Circulation;

use App\Circulation\LedgerReplay;
use App\Entity\CirculationLedgerEntry;
use App\Enum\CirculationCopyStatus;
use App\Enum\CirculationLedgerEntryType;
use App\Repository\CirculationLedgerEntryRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class LedgerReplayTest extends TestCase
{
    private const string CONTEXT = 'book-group-1';

    public function testDonationThenHandoverLeavesTheCopyWithTheReceiver(): void
    {
        // Arrange
        $handedOverAt = new DateTimeImmutable('2026-08-10 18:00:00');
        $replay = $this->replayOf([
            $this->entry(CirculationLedgerEntryType::Donated, new DateTimeImmutable('2026-08-01 09:00:00'), actorUserId: 3),
            $this->entry(CirculationLedgerEntryType::MarkedFinished, new DateTimeImmutable('2026-08-09 09:00:00'), actorUserId: 3),
            $this->entry(CirculationLedgerEntryType::HandoverOpened, new DateTimeImmutable('2026-08-09 10:00:00'), fromUserId: 3, toUserId: 5),
            $this->entry(CirculationLedgerEntryType::HandoverCompleted, $handedOverAt, fromUserId: 3, toUserId: 5),
        ]);

        // Act
        $states = $replay->rebuild(self::CONTEXT);

        // Assert
        self::assertSame(5, $states[9]->holderId);
        self::assertSame(CirculationCopyStatus::Held, $states[9]->status);
        self::assertSame($handedOverAt, $states[9]->heldSince);
    }

    public function testACancelledHandoverLeavesTheCopyAvailableWithTheGiver(): void
    {
        // Arrange
        $donatedAt = new DateTimeImmutable('2026-08-01 09:00:00');
        $replay = $this->replayOf([
            $this->entry(CirculationLedgerEntryType::Donated, $donatedAt, actorUserId: 3),
            $this->entry(CirculationLedgerEntryType::HandoverOpened, new DateTimeImmutable('2026-08-09 10:00:00'), fromUserId: 3, toUserId: 5),
            $this->entry(CirculationLedgerEntryType::HandoverCancelled, new DateTimeImmutable('2026-08-11 10:00:00'), fromUserId: 3, toUserId: 5),
        ]);

        // Act
        $states = $replay->rebuild(self::CONTEXT);

        // Assert
        self::assertSame(3, $states[9]->holderId);
        self::assertSame(CirculationCopyStatus::Available, $states[9]->status);
        self::assertSame($donatedAt, $states[9]->heldSince);
    }

    public function testRetirementIsTheLastWord(): void
    {
        // Arrange
        $replay = $this->replayOf([
            $this->entry(CirculationLedgerEntryType::Donated, new DateTimeImmutable('2026-08-01 09:00:00'), actorUserId: 3),
            $this->entry(CirculationLedgerEntryType::Retired, new DateTimeImmutable('2026-08-12 09:00:00')),
        ]);

        // Act
        $states = $replay->rebuild(self::CONTEXT);

        // Assert
        self::assertSame(CirculationCopyStatus::Retired, $states[9]->status);
    }

    public function testDerivedStateDetectsACorruptedHolderColumn(): void
    {
        // Arrange
        $replay = $this->replayOf([
            $this->entry(CirculationLedgerEntryType::Donated, new DateTimeImmutable('2026-08-01 09:00:00'), actorUserId: 3),
        ]);
        $state = $replay->rebuild(self::CONTEXT)[9];

        // Act
        $matches = $state->equals(99, new DateTimeImmutable('2026-08-01 09:00:00'), CirculationCopyStatus::Available);

        // Assert
        self::assertFalse($matches);
    }

    /**
     * @param list<CirculationLedgerEntry> $entries
     */
    private function replayOf(array $entries): LedgerReplay
    {
        $repository = $this->createStub(CirculationLedgerEntryRepository::class);
        $repository->method('findChronological')->willReturn($entries);

        return new LedgerReplay($repository);
    }

    private function entry(
        CirculationLedgerEntryType $type,
        DateTimeImmutable $occurredAt,
        ?int $fromUserId = null,
        ?int $toUserId = null,
        ?int $actorUserId = null,
    ): CirculationLedgerEntry {
        return new CirculationLedgerEntry($type, self::CONTEXT, 'book', 42, $occurredAt, 9, $fromUserId, $toUserId, $actorUserId);
    }
}
