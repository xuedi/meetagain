<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\User;
use App\Portability\DataCategory;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\CirculationSection;
use DateTimeImmutable;
use Module\Circulation\Contract\CirculationInterface;
use Module\Circulation\Contract\CopyStatus;
use Module\Circulation\Contract\HandoverStatus;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Contract\PortableCopy;
use Module\Circulation\Contract\PortableHandover;
use Module\Circulation\Contract\PortableLedgerEntry;
use Module\Circulation\Contract\PortableRequest;
use Module\Circulation\Contract\PortableShelf;
use Module\Circulation\Contract\RequestStatus;

final class CirculationSectionTest extends SectionTestCase
{
    private ?PortableShelf $restored = null;

    public function testAMemberWhoKeepsTheirLendingPrivateLeavesNoRowsAndTheirCopyGoesToTheSteward(): void
    {
        // Arrange
        $section = $this->section($this->library());

        // Act
        $rows = $section->export($this->scope(), $this->images());

        // Assert
        static::assertSame([11], array_column($rows['copies'], 'ref'));
        static::assertSame('member@example.org', $rows['copies'][0]['donated_by_email']);
        static::assertSame('steward@example.org', $rows['copies'][0]['holder_email']);
        static::assertSame([22], array_column($rows['requests'], 'ref'));
        static::assertSame([32], array_column($rows['handovers'], 'ref'));
        static::assertSame([['label' => 'Blue cover'], ['handoverId' => 32]], array_column($rows['ledger'], 'payload'));
        static::assertSame([32], $section->exportedHandoverIds($this->scope()));
    }

    public function testTheCirculationSurvivesTheRoundTrip(): void
    {
        // Arrange
        $exported = $this->section($this->library())->export($this->scope(), $this->images());
        $context = $this->context();
        $context->mapRef(User::class, 'member@example.org', $this->user(201, 'member@example.org'));
        $context->mapRef(User::class, 'steward@example.org', $this->user(209, 'steward@example.org'));
        $context->mapItems('book', [5 => 50]);

        // Act
        $this->section()->import($exported, $context);

        // Assert
        $shelf = $this->restored;
        static::assertNotNull($shelf);
        static::assertCount(1, $shelf->copies);
        $copy = $shelf->copies[0];
        static::assertSame(['book', 50, 201, 209, CopyStatus::Held], [
            $copy->context,
            $copy->itemId,
            $copy->donatedByUserId,
            $copy->holderUserId,
            $copy->status,
        ]);
        static::assertSame([11, RequestStatus::Offered, 201], [$shelf->requests[0]->offeredCopyRef, $shelf->requests[0]->status, $shelf->requests[0]->userId]);
        static::assertSame([11, 22, 209], [$shelf->handovers[0]->copyRef, $shelf->handovers[0]->requestRef, $shelf->handovers[0]->toUserId]);

        static::assertCount(2, $shelf->ledger);
        $opened = $shelf->ledger[1];
        static::assertSame([11, 201, 209, 32, []], [$opened->copyRef, $opened->fromUserId, $opened->toUserId, $opened->handoverRef, $opened->payload]);
        static::assertSame(132, $context->resolveId(CirculationInterface::COMMENT_TARGET, 32));

        $summary = $context->toSummary();
        static::assertSame(1, $summary->get(CirculationSection::KIND_COPIES, Outcome::Created));
        static::assertSame(1, $summary->get(CirculationSection::KIND_REQUESTS, Outcome::Created));
        static::assertSame(1, $summary->get(CirculationSection::KIND_HANDOVERS, Outcome::Created));
        static::assertSame(2, $summary->get(CirculationSection::KIND_LEDGER, Outcome::Created));
    }

    public function testRowsOfAnItemTypeThisInstanceLacksAreSkipped(): void
    {
        // Arrange
        $context = $this->context();
        $rows = [
            'copies' => [['ref' => 1, 'item_type' => 'film', 'item_ref' => 3]],
            'ledger' => [['entry_type' => 'donated', 'item_type' => 'film', 'item_ref' => 3]],
        ];

        // Act
        $this->section()->import($rows, $context);

        // Assert
        static::assertEquals(new PortableShelf(), $this->restored);
        static::assertSame(1, $context->toSummary()->get(CirculationSection::KIND_COPIES, Outcome::Skipped));
        static::assertSame(1, $context->toSummary()->get(CirculationSection::KIND_LEDGER, Outcome::Skipped));
    }

    public function testAHandoverWhoseCopyDidNotArriveIsDropped(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(User::class, 'member@example.org', $this->user(201, 'member@example.org'));

        // Act
        $this->section()->import(['handovers' => [['ref' => 1, 'copy_ref' => 99, 'to_email' => 'member@example.org']]], $context);

        // Assert
        static::assertSame([], $this->restored?->handovers);
        static::assertSame(1, $context->toSummary()->get(CirculationSection::KIND_HANDOVERS, Outcome::Dropped));
    }

    public function testAScopeWithoutContextsExportsNothing(): void
    {
        // Act
        $rows = $this->section($this->library())->export(new Scope(itemIds: ['book' => [5]]), $this->images());

        // Assert
        static::assertSame([], $rows);
    }

    private function scope(): Scope
    {
        return new Scope(
            users: [1 => 'user', 2 => 'user', 9 => 'admin'],
            itemIds: ['book' => [5]],
            grants: [1 => [DataCategory::Collections], 2 => [DataCategory::Interactions], 9 => [DataCategory::Collections]],
            stewardEmail: 'steward@example.org',
            circulationContexts: ['book' => 'book'],
        );
    }

    private function library(): PortableShelf
    {
        $donated = new DateTimeImmutable('2030-01-01 10:00');
        $occurredAt = new DateTimeImmutable('2030-01-08 10:00');

        return new PortableShelf(
            copies: [
                new PortableCopy(11, 'book', 'book', 5, $donated, CopyStatus::Held, 'Blue cover', 1, 2, new DateTimeImmutable('2030-01-08 18:00')),
                new PortableCopy(12, 'book', 'book', 6, $donated, CopyStatus::Available),
            ],
            requests: [
                new PortableRequest(21, 'book', 'book', 5, 2, new DateTimeImmutable('2030-01-02 10:00'), RequestStatus::Waiting),
                new PortableRequest(
                    22,
                    'book',
                    'book',
                    5,
                    1,
                    new DateTimeImmutable('2030-01-03 10:00'),
                    RequestStatus::Offered,
                    11,
                    new DateTimeImmutable('2030-01-09 09:00'),
                ),
            ],
            handovers: [
                new PortableHandover(31, 11, 2, new DateTimeImmutable('2030-01-08 10:00'), HandoverStatus::Open, 1),
                new PortableHandover(32, 11, 9, new DateTimeImmutable('2030-01-09 18:00'), HandoverStatus::Open, 1, 22),
            ],
            ledger: [
                new PortableLedgerEntry(LedgerEntryType::Donated, 'book', 'book', 5, $occurredAt, 11, null, 1, 1, payload: ['label' => 'Blue cover']),
                new PortableLedgerEntry(LedgerEntryType::HandoverOpened, 'book', 'book', 5, $occurredAt, 11, 1, 2, 1, 31),
                new PortableLedgerEntry(LedgerEntryType::HandoverOpened, 'book', 'book', 5, $occurredAt, 11, 1, 9, 9, 32),
            ],
        );
    }

    private function section(PortableShelf $shelf = new PortableShelf()): CirculationSection
    {
        $circulation = $this->createStub(CirculationInterface::class);
        $circulation->method('contextFor')->willReturnArgument(0);
        $circulation->method('export')->willReturn($shelf);
        $circulation
            ->method('restore')
            ->willReturnCallback(function (PortableShelf $restored): array {
                $this->restored = $restored;

                return array_combine(
                    array_map(static fn(PortableHandover $handover): int => $handover->ref, $restored->handovers),
                    array_map(static fn(PortableHandover $handover): int => $handover->ref + 100, $restored->handovers),
                );
            });

        $users = [$this->user(1, 'member@example.org'), $this->user(2, 'quiet@example.org'), $this->user(9, 'steward@example.org')];

        return new CirculationSection($this->entityManager($users), $circulation);
    }

    private function user(int $id, string $email): User
    {
        $user = $this->withId(new User(), $id);
        $user->setEmail($email);

        return $user;
    }
}
