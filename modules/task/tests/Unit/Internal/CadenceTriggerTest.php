<?php declare(strict_types=1);

namespace Module\Task\Tests\Unit\Internal;

use DateTimeImmutable;
use Module\Task\Contract\Cadence;
use Module\Task\Internal\CadenceTrigger;
use PHPUnit\Framework\TestCase;

final class CadenceTriggerTest extends TestCase
{
    public function testEveryProcessThatAsksGetsTheSameJitteredTime(): void
    {
        // Arrange
        $cadence = new Cadence('5 * * * *', jitterSeconds: 600);
        $slot = new DateTimeImmutable('2026-10-18 04:05:00');

        // Act
        $beforeTheSlot = new CadenceTrigger('cleanup', $cadence, 'Europe/Berlin')->getNextRunDate($slot->modify('-1 second'));
        $afterTheSlot = new CadenceTrigger('cleanup', $cadence, 'Europe/Berlin')->getNextRunDate($slot->modify('+1 minute'));

        // Assert
        static::assertEquals($beforeTheSlot, $afterTheSlot);
        static::assertGreaterThanOrEqual($slot, $beforeTheSlot);
        static::assertLessThanOrEqual($slot->modify('+600 seconds'), $beforeTheSlot);
    }

    public function testATaskWithoutJitterRunsOnItsSlotAndNotTwice(): void
    {
        // Arrange
        $trigger = new CadenceTrigger('hourly', new Cadence('5 * * * *'), 'Europe/Berlin');

        // Act
        $first = $trigger->getNextRunDate(new DateTimeImmutable('2026-10-18 04:04:59'));
        $second = $trigger->getNextRunDate(new DateTimeImmutable('2026-10-18 04:05:00'));

        // Assert
        static::assertEquals(new DateTimeImmutable('2026-10-18 04:05:00'), $first);
        static::assertEquals(new DateTimeImmutable('2026-10-18 05:05:00'), $second);
    }
}
