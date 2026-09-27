<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Unit\Contract;

use Module\Circulation\Contract\HandoverStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HandoverStatusTest extends TestCase
{
    #[DataProvider('provideStatuses')]
    public function testOnlyAnOpenHandoverIsOpen(HandoverStatus $status, bool $open): void
    {
        // Act
        $actual = $status->isOpen();

        // Assert
        static::assertSame($open, $actual);
    }

    /**
     * @return iterable<string, array{HandoverStatus, bool}>
     */
    public static function provideStatuses(): iterable
    {
        yield 'open' => [HandoverStatus::Open, true];
        yield 'completed' => [HandoverStatus::Completed, false];
        yield 'cancelled' => [HandoverStatus::Cancelled, false];
        yield 'expired' => [HandoverStatus::Expired, false];
    }
}
