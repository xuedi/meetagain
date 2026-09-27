<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Unit\Internal;

use DateTimeImmutable;
use Module\Circulation\Contract\CopyStatus;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Internal\Entity\LedgerEntry;
use Module\Circulation\Internal\LedgerReplay;
use Module\Circulation\Internal\Repository\LedgerEntryRepository;
use PHPUnit\Framework\TestCase;

class LedgerReplayTest extends TestCase
{
    private const string CONTEXT = 'book-group-1';

    public function testDonationThenHandoverLeavesTheCopyWithTheReceiver(): void
    {
        // Arrange
        $handedOverAt = new DateTimeImmutable('2026-08-10 18:00:00');
        $replay = $this->replayOf([
            $this->entry(LedgerEntryType::Donated, new DateTimeImmutable('2026-08-01 09:00:00'), actorUserId: 3),
            $this->entry(LedgerEntryType::MarkedFinished, new DateTimeImmutable('2026-08-09 09:00:00'), actorUserId: 3),
            $this->entry(LedgerEntryType::HandoverOpened, new DateTimeImmutable('2026-08-09 10:00:00'), fromUserId: 3, toUserId: 5),
            $this->entry(LedgerEntryType::HandoverCompleted, $handedOverAt, fromUserId: 3, toUserId: 5),
        ]);

        // Act
        $states = $replay->rebuild(self::CONTEXT);

        // Assert
        self::assertSame(5, $states[9]->holderId);
        self::assertSame(CopyStatus::Held, $states[9]->status);
        self::assertSame($handedOverAt, $states[9]->heldSince);
    }

    public function testACancelledHandoverLeavesTheCopyAvailableWithTheGiver(): void
    {
        // Arrange
        $donatedAt = new DateTimeImmutable('2026-08-01 09:00:00');
        $replay = $this->replayOf([
            $this->entry(LedgerEntryType::Donated, $donatedAt, actorUserId: 3),
            $this->entry(LedgerEntryType::HandoverOpened, new DateTimeImmutable('2026-08-09 10:00:00'), fromUserId: 3, toUserId: 5),
            $this->entry(LedgerEntryType::HandoverCancelled, new DateTimeImmutable('2026-08-11 10:00:00'), fromUserId: 3, toUserId: 5),
        ]);

        // Act
        $states = $replay->rebuild(self::CONTEXT);

        // Assert
        self::assertSame(3, $states[9]->holderId);
        self::assertSame(CopyStatus::Available, $states[9]->status);
        self::assertSame($donatedAt, $states[9]->heldSince);
    }

    public function testRetirementIsTheLastWord(): void
    {
        // Arrange
        $replay = $this->replayOf([
            $this->entry(LedgerEntryType::Donated, new DateTimeImmutable('2026-08-01 09:00:00'), actorUserId: 3),
            $this->entry(LedgerEntryType::Retired, new DateTimeImmutable('2026-08-12 09:00:00')),
        ]);

        // Act
        $states = $replay->rebuild(self::CONTEXT);

        // Assert
        self::assertSame(CopyStatus::Retired, $states[9]->status);
    }

    public function testDerivedStateDetectsACorruptedHolderColumn(): void
    {
        // Arrange
        $replay = $this->replayOf([
            $this->entry(LedgerEntryType::Donated, new DateTimeImmutable('2026-08-01 09:00:00'), actorUserId: 3),
        ]);
        $state = $replay->rebuild(self::CONTEXT)[9];

        // Act
        $matches = $state->equals(99, new DateTimeImmutable('2026-08-01 09:00:00'), CopyStatus::Available);

        // Assert
        self::assertFalse($matches);
    }

    /**
     * @param list<LedgerEntry> $entries
     */
    private function replayOf(array $entries): LedgerReplay
    {
        $repository = $this->createStub(LedgerEntryRepository::class);
        $repository->method('findChronological')->willReturn($entries);

        return new LedgerReplay($repository);
    }

    private function entry(
        LedgerEntryType $type,
        DateTimeImmutable $occurredAt,
        ?int $fromUserId = null,
        ?int $toUserId = null,
        ?int $actorUserId = null,
    ): LedgerEntry {
        return new LedgerEntry($type, self::CONTEXT, 'book', 42, $occurredAt, 9, $fromUserId, $toUserId, $actorUserId);
    }
}
