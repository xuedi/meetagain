<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\Event;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    private const string NOW = '2026-11-05 18:00:00';

    public function testEventRunningUntilLaterIsNotOver(): void
    {
        // Arrange
        $event = new Event()
            ->setStart(new DateTime('2026-11-05 17:00:00'))
            ->setStop(new DateTime('2026-11-05 20:00:00'));

        // Act & Assert
        static::assertFalse($event->isOverAt(new DateTimeImmutable(self::NOW)));
    }

    public function testEventWhoseStopHasPassedIsOver(): void
    {
        // Arrange
        $event = new Event()
            ->setStart(new DateTime('2026-11-05 15:00:00'))
            ->setStop(new DateTime('2026-11-05 17:59:59'));

        // Act & Assert
        static::assertTrue($event->isOverAt(new DateTimeImmutable(self::NOW)));
    }

    public function testEventWithoutStopIsOverOnceItsStartHasPassed(): void
    {
        // Arrange
        $started = new Event()->setStart(new DateTime('2026-11-05 17:30:00'));
        $upcoming = new Event()->setStart(new DateTime('2026-11-05 18:30:00'));

        // Act & Assert
        static::assertTrue($started->isOverAt(new DateTimeImmutable(self::NOW)));
        static::assertFalse($upcoming->isOverAt(new DateTimeImmutable(self::NOW)));
    }
}
