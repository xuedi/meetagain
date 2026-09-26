<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Unit;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Module\Circulation\Contract\CopyStatus;
use Module\Circulation\Contract\HandoverStatus;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Contract\PortableShelf;
use Module\Circulation\Contract\RequestStatus;
use Module\Circulation\Internal\ContextResolver;
use Module\Circulation\Internal\Entity\Copy;
use Module\Circulation\Internal\Entity\Handover;
use Module\Circulation\Internal\Entity\LedgerEntry;
use Module\Circulation\Internal\Entity\Request;
use Module\Circulation\Internal\Repository\LedgerEntryRepository;
use Module\Circulation\Internal\ShelfArchive;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ShelfArchiveTest extends TestCase
{
    /** @var list<object> */
    private array $persisted = [];

    public function testAnExportedShelfRestoresIntoFreshRowsThatPointAtEachOther(): void
    {
        // Arrange
        $shelf = $this->archive($this->library())->export(['book-group-1']);

        // Act
        $handoverIds = $this->archive()->restore($shelf);

        // Assert
        $copy = $this->onlyOf(Copy::class);
        $request = $this->onlyOf(Request::class);
        $handover = $this->onlyOf(Handover::class);
        static::assertSame(['book-group-1', 5, 'Blue cover', 1, 2, CopyStatus::InHandover], [
            $copy->getContext(),
            $copy->getItemId(),
            $copy->getLabel(),
            $copy->getDonatedBy()?->getId(),
            $copy->getHolder()?->getId(),
            $copy->getStatus(),
        ]);
        static::assertSame($copy, $request->getOfferedCopy());
        static::assertSame(RequestStatus::Offered, $request->getStatus());
        static::assertSame($copy, $handover->getCopy());
        static::assertSame($request, $handover->getRequest());
        static::assertSame([31 => $handover->getId()], $handoverIds);

        $ledger = $this->allOf(LedgerEntry::class);
        static::assertSame(
            [['label' => 'Blue cover'], ['handoverId' => $handover->getId(), 'expired' => true]],
            array_map(static fn(LedgerEntry $entry): array => $entry->getPayload(), $ledger),
        );
        static::assertSame([$copy->getId(), $copy->getId()], array_map(static fn(LedgerEntry $entry): ?int => $entry->getCopyId(), $ledger));
    }

    public function testAHandoverWhoseCopyIsNotOnTheShelfIsSkipped(): void
    {
        // Arrange
        $shelf = $this->archive($this->library())->export(['book-group-1']);
        $withoutCopies = new PortableShelf([], $shelf->requests, $shelf->handovers, $shelf->ledger);

        // Act
        $handoverIds = $this->archive()->restore($withoutCopies);

        // Assert
        static::assertSame([], $handoverIds);
        static::assertSame([], $this->allOf(Handover::class));
        static::assertSame([null, null], array_map(static fn(LedgerEntry $entry): ?int => $entry->getCopyId(), $this->allOf(LedgerEntry::class)));
    }

    /**
     * @return array<class-string, list<object>>
     */
    private function library(): array
    {
        $donor = $this->user(1);
        $reader = $this->user(2);
        $at = new DateTimeImmutable('2030-01-08 10:00');

        $copy = $this->withId(new Copy('book-group-1', 'book', 5, $at), 11);
        $copy->setLabel('Blue cover');
        $copy->setDonatedBy($donor);
        $copy->setHolder($reader);
        $copy->setStatus(CopyStatus::InHandover);

        $request = $this->withId(new Request('book-group-1', 'book', 5, $reader, $at), 21);
        $request->setStatus(RequestStatus::Offered);
        $request->setOfferedCopy($copy);

        $handover = $this->withId(new Handover($copy, $reader, $donor, $at), 31);
        $handover->setRequest($request);
        $handover->setStatus(HandoverStatus::Open);

        return [
            Copy::class => [$copy],
            Request::class => [$request],
            Handover::class => [$handover],
            LedgerEntry::class => [
                new LedgerEntry(LedgerEntryType::Donated, 'book-group-1', 'book', 5, $at, 11, null, 1, 1, ['label' => 'Blue cover']),
                new LedgerEntry(LedgerEntryType::HandoverCancelled, 'book-group-1', 'book', 5, $at, 11, 2, 1, 1, [
                    'handoverId' => 31,
                    'expired' => true,
                ]),
            ],
        ];
    }

    /**
     * @param array<class-string, list<object>> $found
     */
    private function archive(array $found = []): ShelfArchive
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(function (string $class) use ($found): EntityRepository {
            $repository = $this->createStub(EntityRepository::class);
            $repository->method('findBy')->willReturn($found[$class] ?? []);

            return $repository;
        });
        $em->method('getReference')->willReturnCallback(fn(string $class, int $id): User => $this->user($id));
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

        return new ShelfArchive($em, new ContextResolver([]), $this->createStub(LedgerEntryRepository::class));
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return list<T>
     */
    private function allOf(string $class): array
    {
        return array_values(array_filter($this->persisted, static fn(object $entity): bool => $entity instanceof $class));
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function onlyOf(string $class): object
    {
        $matches = $this->allOf($class);
        static::assertCount(1, $matches);

        return $matches[0];
    }

    private function user(int $id): User
    {
        return $this->withId(new User(), $id);
    }

    /**
     * @template T of object
     * @param T $entity
     * @return T
     */
    private function withId(object $entity, int $id): object
    {
        new ReflectionProperty($entity::class, 'id')->setValue($entity, $id);

        return $entity;
    }
}
