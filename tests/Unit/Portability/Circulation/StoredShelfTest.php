<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Circulation;

use App\Portability\Circulation\StoredShelf;
use DateTimeImmutable;
use Module\Circulation\Contract\CopyStatus;
use Module\Circulation\Contract\HandoverStatus;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Contract\PortableCopy;
use Module\Circulation\Contract\PortableHandover;
use Module\Circulation\Contract\PortableLedgerEntry;
use Module\Circulation\Contract\PortableRequest;
use Module\Circulation\Contract\PortableShelf;
use Module\Circulation\Contract\RequestStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StoredShelfTest extends TestCase
{
    private const string DONATED = '2030-01-01 10:00:00';
    private const string REQUESTED = '2030-01-02 10:00:00';
    private const string OPENED = '2030-01-03 10:00:00';

    public function testACopyIsFoundByItsItemAndDonationTime(): void
    {
        // Arrange
        $stored = new StoredShelf($this->shelf());

        // Act
        $sameCopy = $stored->copyId($this->copy(99, self::DONATED));
        $otherCopy = $stored->copyId($this->copy(99, '2030-02-01 10:00:00'));

        // Assert
        static::assertSame(11, $sameCopy);
        static::assertNull($otherCopy);
    }

    #[DataProvider('provideRequests')]
    public function testARequestIsFoundByItsTimeOrByItsMembersOpenPlace(string $requestedAt, RequestStatus $status, bool $expected): void
    {
        // Arrange
        $stored = new StoredShelf($this->shelf());

        // Act
        $isStored = $stored->hasRequest(new PortableRequest(5, 'book', 'book', 5, 2, new DateTimeImmutable($requestedAt), $status));

        // Assert
        static::assertSame($expected, $isStored);
    }

    public static function provideRequests(): iterable
    {
        yield 'the same request' => [self::REQUESTED, RequestStatus::Fulfilled, true];
        yield 'another open place of the same member on the same item' => ['2030-03-01 10:00:00', RequestStatus::Waiting, true];
        yield 'a closed request at another time' => ['2030-03-01 10:00:00', RequestStatus::Cancelled, false];
    }

    public function testAHandoverIsFoundByItsCopyReceiverAndOpeningTime(): void
    {
        // Arrange
        $stored = new StoredShelf($this->shelf());

        // Act
        $handoverId = $stored->handoverId(11, 2, new DateTimeImmutable(self::OPENED));

        // Assert
        static::assertSame(21, $handoverId);
        static::assertNull($stored->handoverId(11, 3, new DateTimeImmutable(self::OPENED)));
    }

    public function testALedgerEntryIsFoundByItsTypeItemAndTime(): void
    {
        // Arrange
        $stored = new StoredShelf($this->shelf());

        // Act
        $isStored = $stored->hasLedgerEntry(LedgerEntryType::Donated, 'book', 5, new DateTimeImmutable(self::DONATED));

        // Assert
        static::assertTrue($isStored);
        static::assertFalse(new StoredShelf()->hasLedgerEntry(LedgerEntryType::Donated, 'book', 5, new DateTimeImmutable(self::DONATED)));
    }

    private function shelf(): PortableShelf
    {
        return new PortableShelf(
            copies: [$this->copy(11, self::DONATED)],
            requests: [new PortableRequest(31, 'book', 'book', 5, 2, new DateTimeImmutable(self::REQUESTED), RequestStatus::Waiting)],
            handovers: [new PortableHandover(21, 11, 2, new DateTimeImmutable(self::OPENED), HandoverStatus::Open)],
            ledger: [new PortableLedgerEntry(LedgerEntryType::Donated, 'book', 'book', 5, new DateTimeImmutable(self::DONATED))],
        );
    }

    private function copy(int $ref, string $donatedAt): PortableCopy
    {
        return new PortableCopy($ref, 'book', 'book', 5, new DateTimeImmutable($donatedAt), CopyStatus::Available);
    }
}
