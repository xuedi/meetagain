<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email\Delivery;

use App\Service\Email\Delivery\SyncResult;
use PHPUnit\Framework\TestCase;

class SyncResultTest extends TestCase
{
    public function testConstructorAssignsAllProperties(): void
    {
        // Act
        $result = new SyncResult(available: true, updated: 5, checked: 10);

        // Assert
        static::assertTrue($result->available);
        static::assertSame(5, $result->updated);
        static::assertSame(10, $result->checked);
    }

    public function testUnavailableFactoryReturnsZeroedResult(): void
    {
        // Act
        $result = SyncResult::unavailable();

        // Assert
        static::assertFalse($result->available);
        static::assertSame(0, $result->updated);
        static::assertSame(0, $result->checked);
    }

    public function testSuccessFactoryMarksAvailableAndCarriesCounts(): void
    {
        // Act
        $result = SyncResult::success(updated: 3, checked: 42);

        // Assert
        static::assertTrue($result->available);
        static::assertSame(3, $result->updated);
        static::assertSame(42, $result->checked);
    }
}
