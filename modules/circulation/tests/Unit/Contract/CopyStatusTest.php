<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Unit\Contract;

use Module\Circulation\Contract\CopyStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CopyStatusTest extends TestCase
{
    #[DataProvider('provideStatuses')]
    public function testACopyCirculatesUntilItIsRetiredOrLost(CopyStatus $status, bool $held, bool $circulating): void
    {
        // Act
        $actual = [$status->isHeld(), $status->isCirculating()];

        // Assert
        static::assertSame([$held, $circulating], $actual);
    }

    /**
     * @return iterable<string, array{CopyStatus, bool, bool}>
     */
    public static function provideStatuses(): iterable
    {
        yield 'available' => [CopyStatus::Available, false, true];
        yield 'held' => [CopyStatus::Held, true, true];
        yield 'in handover' => [CopyStatus::InHandover, false, true];
        yield 'retired' => [CopyStatus::Retired, false, false];
        yield 'lost' => [CopyStatus::Lost, false, false];
    }
}
