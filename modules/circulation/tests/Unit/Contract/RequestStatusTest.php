<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Unit\Contract;

use Module\Circulation\Contract\RequestStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestStatusTest extends TestCase
{
    #[DataProvider('provideStatuses')]
    public function testARequestIsOpenWhileWaitingOrOffered(RequestStatus $status, bool $open): void
    {
        // Act
        $actual = $status->isOpen();

        // Assert
        static::assertSame($open, $actual);
    }

    /**
     * @return iterable<string, array{RequestStatus, bool}>
     */
    public static function provideStatuses(): iterable
    {
        yield 'waiting' => [RequestStatus::Waiting, true];
        yield 'offered' => [RequestStatus::Offered, true];
        yield 'fulfilled' => [RequestStatus::Fulfilled, false];
        yield 'cancelled' => [RequestStatus::Cancelled, false];
        yield 'expired' => [RequestStatus::Expired, false];
    }
}
