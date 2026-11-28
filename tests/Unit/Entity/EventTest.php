<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\Event;
use App\Entity\EventTranslation;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    private const string NOW = '2026-11-05 18:00:00';

    public function testEventRunningUntilLaterIsNotOver(): void
    {
        // Arrange
        $event = new Event()
            ->setStart(new DateTimeImmutable('2026-11-05 17:00:00'))
            ->setStop(new DateTimeImmutable('2026-11-05 20:00:00'));

        // Act & Assert
        static::assertFalse($event->isOverAt(new DateTimeImmutable(self::NOW)));
    }

    public function testEventWhoseStopHasPassedIsOver(): void
    {
        // Arrange
        $event = new Event()
            ->setStart(new DateTimeImmutable('2026-11-05 15:00:00'))
            ->setStop(new DateTimeImmutable('2026-11-05 17:59:59'));

        // Act & Assert
        static::assertTrue($event->isOverAt(new DateTimeImmutable(self::NOW)));
    }

    public function testEventWithoutStopIsOverOnceItsStartHasPassed(): void
    {
        // Arrange
        $started = new Event()->setStart(new DateTimeImmutable('2026-11-05 17:30:00'));
        $upcoming = new Event()->setStart(new DateTimeImmutable('2026-11-05 18:30:00'));

        // Act & Assert
        static::assertTrue($started->isOverAt(new DateTimeImmutable(self::NOW)));
        static::assertFalse($upcoming->isOverAt(new DateTimeImmutable(self::NOW)));
    }

    #[DataProvider('metaDescriptionProvider')]
    public function testTheMetaDescriptionPrefersTeaserThenDescriptionThenTitle(?string $teaser, string $description, string $expected): void
    {
        // Arrange
        $event = new Event()->addTranslation(
            new EventTranslation()
                ->setLanguage('en')
                ->setTitle('Go night')
                ->setDescription($description)
                ->setTeaser($teaser),
        );

        // Act
        $result = $event->getMetaDescription('en');

        // Assert
        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{?string, string, string}>
     */
    public static function metaDescriptionProvider(): iterable
    {
        yield 'the teaser wins' => ['Bring a board', '<p>We play Go.</p>', 'Bring a board'];
        yield 'the description stands in without a teaser' => [null, '<p>We play Go.</p>', 'We play Go.'];
        yield 'tags leave a space between words' => [null, '<h2>Go</h2><p>night &amp; day</p>', 'Go night & day'];
        yield 'the title stands in without either' => [null, '', 'Go night'];
    }
}
