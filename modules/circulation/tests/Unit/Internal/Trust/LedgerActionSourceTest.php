<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Unit\Internal\Trust;

use DateTimeImmutable;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Internal\Entity\LedgerEntry;
use Module\Circulation\Internal\Repository\LedgerEntryRepository;
use Module\Circulation\Internal\Trust\ContextIndex;
use Module\Circulation\Internal\Trust\LedgerActionSource;
use Module\Trust\Contract\ActionDescriptor;
use Module\Trust\Contract\TrustAction;
use PHPUnit\Framework\TestCase;

class LedgerActionSourceTest extends TestCase
{
    private const string CONTEXT = 'book-group-1';

    public function testACompletedHandoverRewardsBothSides(): void
    {
        // Arrange
        $source = $this->source([$this->entry(LedgerEntryType::HandoverCompleted, fromUserId: 3, toUserId: 5)]);

        // Act
        $actions = iterator_to_array($source->replay(self::CONTEXT), false);

        // Assert
        self::assertCount(2, $actions);
        self::assertSame([5, 3], array_map(static fn(TrustAction $action): int => $action->userId, $actions));
        self::assertSame(
            [LedgerActionSource::HANDOVER_ACTION, LedgerActionSource::HANDOVER_ACTION],
            array_map(static fn(TrustAction $action): string => $action->action, $actions),
        );
    }

    public function testADonationOriginHandoverRewardsOnlyTheReceiver(): void
    {
        // Arrange
        $source = $this->source([$this->entry(LedgerEntryType::HandoverCompleted, fromUserId: null, toUserId: 5)]);

        // Act
        $actions = iterator_to_array($source->replay(self::CONTEXT), false);

        // Assert
        self::assertCount(1, $actions);
        self::assertSame(5, $actions[0]->userId);
    }

    public function testADonationRewardsItsDonor(): void
    {
        // Arrange
        $source = $this->source([$this->entry(LedgerEntryType::Donated, actorUserId: 3)]);

        // Act
        $actions = iterator_to_array($source->replay(self::CONTEXT), false);

        // Assert
        self::assertCount(1, $actions);
        self::assertSame(LedgerActionSource::DONATION_ACTION, $actions[0]->action);
    }

    public function testEveryReplayedKeyIsAlsoDeclared(): void
    {
        // Arrange
        $source = $this->source([
            $this->entry(LedgerEntryType::Donated, actorUserId: 3),
            $this->entry(LedgerEntryType::HandoverCompleted, fromUserId: 3, toUserId: 5),
            $this->entry(LedgerEntryType::MarkedFinished, actorUserId: 5),
        ]);

        // Act
        $declared = array_map(
            static fn(ActionDescriptor $descriptor): string => $descriptor->key,
            iterator_to_array($source->describeActions(self::CONTEXT), false),
        );
        $replayed = array_unique(array_map(
            static fn(TrustAction $action): string => $action->action,
            iterator_to_array($source->replay(self::CONTEXT), false),
        ));

        // Assert
        self::assertSame([], array_diff($replayed, $declared));
    }

    public function testReplayIsStableAcrossTwoRuns(): void
    {
        // Arrange
        $source = $this->source([
            $this->entry(LedgerEntryType::Donated, actorUserId: 3),
            $this->entry(LedgerEntryType::HandoverCompleted, fromUserId: 3, toUserId: 5),
        ]);
        $shape = static fn(TrustAction $action): string => (
            $action->userId . '|' . $action->action . '|' . $action->occurredAt->format(DATE_ATOM) . '|' . $action->quantity
        );

        // Act
        $first = array_map($shape, iterator_to_array($source->replay(self::CONTEXT), false));
        $second = array_map($shape, iterator_to_array($source->replay(self::CONTEXT), false));

        // Assert
        self::assertSame($first, $second);
    }

    public function testAnUnclaimedContextContributesNothing(): void
    {
        // Arrange
        $source = $this->source([$this->entry(LedgerEntryType::Donated, actorUserId: 3)], claimed: false);

        // Act + Assert
        self::assertSame([], iterator_to_array($source->replay(self::CONTEXT), false));
        self::assertSame([], iterator_to_array($source->describeActions(self::CONTEXT), false));
        self::assertNull($source->getRevision(self::CONTEXT));
    }

    public function testTheRevisionFollowsTheHighestLedgerRow(): void
    {
        // Arrange
        $entries = $this->createStub(LedgerEntryRepository::class);
        $entries->method('getMaxId')->willReturn(17);
        $source = new LedgerActionSource($this->index(true), $entries);

        // Act + Assert
        self::assertSame('circulation-17', $source->getRevision(self::CONTEXT));
    }

    /**
     * @param list<LedgerEntry> $entries
     */
    private function source(array $entries, bool $claimed = true): LedgerActionSource
    {
        $repository = $this->createStub(LedgerEntryRepository::class);
        $repository->method('findChronological')->willReturn($entries);

        return new LedgerActionSource($this->index($claimed), $repository);
    }

    private function index(bool $claimed): ContextIndex
    {
        $index = $this->createStub(ContextIndex::class);
        $index->method('itemTypeFor')->willReturn($claimed ? 'book' : null);

        return $index;
    }

    private function entry(LedgerEntryType $type, ?int $fromUserId = null, ?int $toUserId = null, ?int $actorUserId = null): LedgerEntry
    {
        return new LedgerEntry($type, self::CONTEXT, 'book', 42, new DateTimeImmutable('2026-08-01 09:00:00'), 9, $fromUserId, $toUserId, $actorUserId);
    }
}
