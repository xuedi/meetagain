<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Circulation\ContextResolver;
use App\Circulation\DefaultContextProvider;
use App\Entity\CirculationCopy;
use App\Entity\CirculationHandover;
use App\Entity\CirculationLedgerEntry;
use App\Entity\CirculationRequest;
use App\Entity\User;
use App\Enum\CirculationCopyStatus;
use App\Enum\CirculationLedgerEntryType;
use App\Enum\CirculationRequestStatus;
use App\Portability\DataCategory;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\CirculationSection;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use ReflectionProperty;

final class CirculationSectionTest extends SectionTestCase
{
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
        $member = $this->user(201, 'member@example.org');
        $steward = $this->user(209, 'steward@example.org');
        $context = $this->context();
        $context->mapRef(User::class, 'member@example.org', $member);
        $context->mapRef(User::class, 'steward@example.org', $steward);
        $context->mapItems('book', [5 => 50]);

        // Act
        $this->section()->import($exported, $context);

        // Assert
        $copy = $context->resolveRef(CirculationCopy::class, 11);
        $request = $context->resolveRef(CirculationRequest::class, 22);
        $handover = $context->resolveRef(CirculationHandover::class, 32);
        static::assertSame('book', $copy?->getContext());
        static::assertSame(50, $copy?->getItemId());
        static::assertSame($member, $copy?->getDonatedBy());
        static::assertSame($steward, $copy?->getHolder());
        static::assertSame(CirculationCopyStatus::Held, $copy?->getStatus());
        static::assertSame($copy, $request?->getOfferedCopy());
        static::assertSame(CirculationRequestStatus::Offered, $request?->getStatus());
        static::assertSame($copy, $handover?->getCopy());
        static::assertSame($request, $handover?->getRequest());

        $entries = $this->ledgerEntries();
        static::assertCount(2, $entries);
        static::assertSame($copy?->getId(), $entries[1]->getCopyId());
        static::assertSame(201, $entries[1]->getFromUserId());
        static::assertSame(209, $entries[1]->getToUserId());
        static::assertSame(['handoverId' => $handover?->getId()], $entries[1]->getPayload());

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
        static::assertSame([], $this->persisted);
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
        static::assertSame([], $this->persisted);
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
            circulationContexts: ['book'],
        );
    }

    /**
     * @return array<class-string, list<object>>
     */
    private function library(): array
    {
        $member = $this->user(1, 'member@example.org');
        $quiet = $this->user(2, 'quiet@example.org');
        $steward = $this->user(9, 'steward@example.org');

        $copy = $this->withId(new CirculationCopy('book', 'book', 5, new DateTimeImmutable('2030-01-01 10:00')), 11);
        $copy->setLabel('Blue cover');
        $copy->setDonatedBy($member);
        $copy->setHolder($quiet);
        $copy->setHeldSince(new DateTimeImmutable('2030-01-08 18:00'));
        $copy->setStatus(CirculationCopyStatus::Held);
        $otherBook = $this->withId(new CirculationCopy('book', 'book', 6, new DateTimeImmutable('2030-01-01 10:00')), 12);

        $quietRequest = $this->withId(new CirculationRequest('book', 'book', 5, $quiet, new DateTimeImmutable('2030-01-02 10:00')), 21);
        $memberRequest = $this->withId(new CirculationRequest('book', 'book', 5, $member, new DateTimeImmutable('2030-01-03 10:00')), 22);
        $memberRequest->setStatus(CirculationRequestStatus::Offered);
        $memberRequest->setOfferedCopy($copy);
        $memberRequest->setOfferedAt(new DateTimeImmutable('2030-01-09 09:00'));

        $toQuiet = $this->withId(new CirculationHandover($copy, $member, $quiet, new DateTimeImmutable('2030-01-08 10:00')), 31);
        $toSteward = $this->withId(new CirculationHandover($copy, $member, $steward, new DateTimeImmutable('2030-01-09 18:00')), 32);
        $toSteward->setRequest($memberRequest);

        return [
            CirculationCopy::class => [$copy, $otherBook],
            CirculationRequest::class => [$quietRequest, $memberRequest],
            CirculationHandover::class => [$toQuiet, $toSteward],
            CirculationLedgerEntry::class => [
                $this->entry(41, CirculationLedgerEntryType::Donated, null, 1, 1, ['label' => 'Blue cover']),
                $this->entry(42, CirculationLedgerEntryType::HandoverOpened, 1, 2, 1, ['handoverId' => 31]),
                $this->entry(43, CirculationLedgerEntryType::HandoverOpened, 1, 9, 9, ['handoverId' => 32]),
            ],
            User::class => [$member, $quiet, $steward],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function entry(int $id, CirculationLedgerEntryType $type, ?int $fromUserId, ?int $toUserId, ?int $actorUserId, array $payload): CirculationLedgerEntry
    {
        $occurredAt = new DateTimeImmutable('2030-01-08 10:00');

        return $this->withId(new CirculationLedgerEntry($type, 'book', 'book', 5, $occurredAt, 11, $fromUserId, $toUserId, $actorUserId, $payload), $id);
    }

    /**
     * @param array<class-string, list<object>> $found
     */
    private function section(array $found = []): CirculationSection
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(function (string $class) use ($found): EntityRepository {
            $repository = $this->createStub(EntityRepository::class);
            $repository->method('findBy')->willReturn($found[$class] ?? []);

            return $repository;
        });
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $em->method('flush')->willReturnCallback(function (): void {
            foreach ($this->persisted as $index => $entity) {
                $id = new ReflectionProperty($entity::class, 'id');
                if ($id->getValue($entity) === null) {
                    $id->setValue($entity, 100 + $index);
                }
            }
        });

        return new CirculationSection($em, new ContextResolver([new DefaultContextProvider()]));
    }

    /**
     * @return list<CirculationLedgerEntry>
     */
    private function ledgerEntries(): array
    {
        return array_values(array_filter($this->persisted, static fn(object $entity): bool => $entity instanceof CirculationLedgerEntry));
    }

    private function user(int $id, string $email): User
    {
        $user = $this->withId(new User(), $id);
        $user->setEmail($email);

        return $user;
    }
}
