<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Unit;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Internal\Entity\LedgerEntry;
use Module\Circulation\Internal\LedgerService;
use Module\Circulation\Internal\Repository\LedgerEntryRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class LedgerServiceTest extends TestCase
{
    public function testAppendWritesExactlyOneRow(): void
    {
        // Arrange
        $occurredAt = new DateTimeImmutable('2026-08-01 10:00:00');
        $em = $this->createMock(EntityManagerInterface::class);
        $em
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(
                static fn(LedgerEntry $entry): bool => (
                    $entry->getEntryType() === LedgerEntryType::Donated
                    && $entry->getContext() === 'book-group-1'
                    && $entry->getItemId() === 42
                    && $entry->getOccurredAt() === $occurredAt
                ),
            ));
        $em->expects(self::once())->method('flush');
        $service = new LedgerService($em, $this->createStub(LedgerEntryRepository::class));

        // Act
        $entry = $service->append(LedgerEntryType::Donated, 'book-group-1', 'book', 42, $occurredAt, 7, null, 3, 3, ['label' => 'blue']);

        // Assert
        self::assertSame(7, $entry->getCopyId());
        self::assertSame(['label' => 'blue'], $entry->getPayload());
        self::assertGreaterThanOrEqual($occurredAt, $entry->getRecordedAt());
    }

    public function testTheEntryHasNoMutators(): void
    {
        // Arrange
        $reflection = new ReflectionClass(LedgerEntry::class);

        // Act
        $setters = array_filter($reflection->getMethods(), static fn($method): bool => str_starts_with($method->getName(), 'set'));

        // Assert
        self::assertSame([], $setters);
    }
}
