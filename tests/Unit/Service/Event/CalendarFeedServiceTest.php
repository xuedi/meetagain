<?php declare(strict_types=1);

namespace Tests\Unit\Service\Event;

use App\Calendar\Writer;
use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\Location;
use App\Filter\Event\EventFilterService;
use App\Repository\EventRepository;
use App\Service\Config\ConfigService;
use App\Service\Event\CalendarFeedService;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Unit\Stubs\EventStub;

class CalendarFeedServiceTest extends TestCase
{
    public function testRendersAnAccessibleEvent(): void
    {
        // Arrange
        $service = $this->service([$this->event(7)]);

        // Act
        $document = $service->renderFeed('example.org', 'en');

        // Assert
        self::assertStringContainsString('UID:event-7@example.org', $document);
        self::assertStringContainsString('SUMMARY:Salsa night', $document);
    }

    public function testSkipsEventsWithoutATranslationInTheRequestedLocale(): void
    {
        // Arrange
        $service = $this->service([$this->event(7, language: 'de')]);

        // Act
        $document = $service->renderFeed('example.org', 'en');

        // Assert
        self::assertStringNotContainsString('BEGIN:VEVENT', $document);
    }

    public function testSkipsEventsTheFilterChainExcludes(): void
    {
        // Arrange
        $service = $this->service([$this->event(7), $this->event(8)], accessibleIds: [8]);

        // Act
        $document = $service->renderFeed('example.org', 'en');

        // Assert
        self::assertStringNotContainsString('UID:event-7@example.org', $document);
        self::assertStringContainsString('UID:event-8@example.org', $document);
    }

    public function testFallsBackToATwoHourDurationWhenTheEventHasNoStop(): void
    {
        // Arrange
        $service = $this->service([$this->event(7, stop: null)]);

        // Act
        $document = $service->renderFeed('example.org', 'en');

        // Assert
        self::assertStringContainsString('DTSTART:20260901T180000Z', $document);
        self::assertStringContainsString('DTEND:20260901T200000Z', $document);
    }

    public function testCanceledEventKeepsItsEntryAndGainsAPrefix(): void
    {
        // Arrange
        $service = $this->service([$this->event(7, canceled: true)]);

        // Act
        $document = $service->renderFeed('example.org', 'en');

        // Assert
        self::assertStringContainsString('STATUS:CANCELLED', $document);
        self::assertStringContainsString('SUMMARY:events.calendar_canceled_prefix Salsa night', $document);
    }

    public function testDescriptionIsStrippedOfMarkup(): void
    {
        // Arrange
        $service = $this->service([$this->event(7, teaser: '', description: '<p>First line</p><p>Second line</p>')]);

        // Act
        $document = $service->renderFeed('example.org', 'en');

        // Assert
        $unfolded = str_replace("\r\n ", '', $document);
        self::assertStringContainsString('DESCRIPTION:First line\nSecond line', $unfolded);
        self::assertStringNotContainsString('<p>', $unfolded);
    }

    public function testLocationIsFlattenedToASingleLine(): void
    {
        // Arrange
        $service = $this->service([$this->event(7, location: $this->location())]);

        // Act
        $document = $service->renderFeed('example.org', 'en');

        // Assert
        self::assertStringContainsString('LOCATION:Studio\, Main Street 1\, 10115 Berlin', str_replace("\r\n ", '', $document));
    }

    public function testOutputIsByteIdenticalWithinTheSameHour(): void
    {
        // Arrange
        $early = $this->service([$this->event(7)], clock: new MockClock('2026-08-03 10:00:01'));
        $late = $this->service([$this->event(7)], clock: new MockClock('2026-08-03 10:59:59'));

        // Act
        $first = $early->renderFeed('example.org', 'en');
        $second = $late->renderFeed('example.org', 'en');

        // Assert
        self::assertSame($first, $second);
    }

    public function testOutputChangesInTheFollowingHour(): void
    {
        // Arrange
        $before = $this->service([$this->event(7)], clock: new MockClock('2026-08-03 10:59:59'));
        $after = $this->service([$this->event(7)], clock: new MockClock('2026-08-03 11:00:00'));

        // Act
        $first = $before->renderFeed('example.org', 'en');
        $second = $after->renderFeed('example.org', 'en');

        // Assert
        self::assertNotSame($first, $second);
    }

    public function testSingleEventRenderReturnsNullWhenTheLocaleHasNoTranslation(): void
    {
        // Arrange
        $service = $this->service([]);

        // Act
        $document = $service->renderEvent($this->event(7, language: 'de'), 'example.org', 'en');

        // Assert
        self::assertNull($document);
    }

    /**
     * @param list<Event> $events
     * @param list<int>|null $accessibleIds
     */
    private function service(array $events, ?array $accessibleIds = null, ?MockClock $clock = null): CalendarFeedService
    {
        $repository = $this->createStub(EventRepository::class);
        $repository->method('findForCalendarFeed')->willReturn($events);

        $filter = $this->createStub(EventFilterService::class);
        $filter->method('getAccessibleEventIds')->willReturnCallback(
            static fn(array $ids): array => $accessibleIds === null ? $ids : array_values(array_intersect($ids, $accessibleIds)),
        );

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn(string $route, array $parameters): string => sprintf('https://example.org/en/event/%d', $parameters['id']),
        );

        $config = $this->createStub(ConfigService::class);
        $config->method('getHost')->willReturn('https://example.org');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn(string $id): string => $id);

        return new CalendarFeedService(
            $repository,
            $filter,
            new Writer(),
            $urlGenerator,
            $config,
            $translator,
            $clock ?? new MockClock('2026-08-03 10:17:33'),
            new ArrayAdapter(),
        );
    }

    private function event(
        int $id,
        string $language = 'en',
        bool $canceled = false,
        ?DateTimeInterface $stop = null,
        string $teaser = 'Come dance with us',
        string $description = 'A long description',
        ?Location $location = null,
    ): Event {
        $translation = new EventTranslation()
            ->setLanguage($language)
            ->setTitle('Salsa night')
            ->setTeaser($teaser)
            ->setDescription($description);

        $event = new EventStub()
            ->setId($id)
            ->setStart(new DateTimeImmutable('2026-09-01 18:00:00', new DateTimeZone('UTC')))
            ->setStop($stop)
            ->setLocation($location)
            ->setCanceled($canceled);

        $event->addTranslation($translation);

        return $event;
    }

    private function location(): Location
    {
        return new Location()
            ->setName('Studio')
            ->setStreet('Main Street 1')
            ->setPostcode('10115')
            ->setCity('Berlin');
    }
}
