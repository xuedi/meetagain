<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit\Contract;

use DateTimeImmutable;
use Module\Email\Contract\ScheduledMailItem;
use PHPUnit\Framework\TestCase;

final class ScheduledMailItemTest extends TestCase
{
    public function testTheKeyTellsTheSameTypeAtTwoTimesApart(): void
    {
        // Arrange
        $morning = new ScheduledMailItem('reminder', 'Reminder', new DateTimeImmutable('2031-01-01 08:00'), 3);
        $evening = new ScheduledMailItem('reminder', 'Reminder', new DateTimeImmutable('2031-01-01 20:00'), 3);

        // Act
        $keys = [$morning->getKey(), $evening->getKey()];

        // Assert
        static::assertStringStartsWith('reminder_', $keys[0]);
        static::assertNotSame($keys[0], $keys[1]);
    }
}
