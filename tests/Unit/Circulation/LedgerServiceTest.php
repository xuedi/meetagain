<?php declare(strict_types=1);

namespace Tests\Unit\Circulation;

use App\Circulation\LedgerService;
use App\Entity\CirculationLedgerEntry;
use App\Enum\CirculationLedgerEntryType;
use App\Repository\CirculationLedgerEntryRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class LedgerServiceTest extends TestCase
{
    public function testAppendWritesExactlyOneRow(): void
    {
        // Arrange
        $occurredAt = new DateTimeImmutable('2026-08-01 10:00:00');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::callback(
            static fn(CirculationLedgerEntry $entry): bool => $entry->getEntryType() === CirculationLedgerEntryType::Donated
                && $entry->getContext() === 'book-group-1'
                && $entry->getItemId() === 42
                && $entry->getOccurredAt() === $occurredAt,
        ));
        $em->expects(self::once())->method('flush');
        $service = new LedgerService($em, $this->createStub(CirculationLedgerEntryRepository::class));

        // Act
        $entry = $service->append(CirculationLedgerEntryType::Donated, 'book-group-1', 'book', 42, $occurredAt, 7, null, 3, 3, ['label' => 'blue']);

        // Assert
        self::assertSame(7, $entry->getCopyId());
        self::assertSame(['label' => 'blue'], $entry->getPayload());
        self::assertGreaterThanOrEqual($occurredAt, $entry->getRecordedAt());
    }

    public function testTheEntryHasNoMutators(): void
    {
        // Arrange
        $reflection = new ReflectionClass(CirculationLedgerEntry::class);

        // Act
        $setters = array_filter(
            $reflection->getMethods(),
            static fn($method): bool => str_starts_with($method->getName(), 'set'),
        );

        // Assert
        self::assertSame([], $setters);
    }
}
