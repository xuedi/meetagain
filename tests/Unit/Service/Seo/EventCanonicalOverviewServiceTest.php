<?php declare(strict_types=1);

namespace Tests\Unit\Service\Seo;

use App\Entity\Event;
use App\Entity\EventSeries;
use App\Entity\EventTranslation;
use App\Repository\EventCanonicalRootRepository;
use App\Repository\EventRepository;
use App\Repository\EventSeriesRepository;
use App\Service\Seo\EventCanonicalOverviewService;
use App\Service\Seo\EventCanonicalResolver;
use App\Service\Seo\EventSimilarityService;
use DateTime;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Stubs\EventSeriesStub;
use Tests\Unit\Stubs\EventStub;

class EventCanonicalOverviewServiceTest extends TestCase
{
    public function testLanesCarryOnlySeriesLocaleAndStops(): void
    {
        // Arrange
        $series = $this->makeSeries(7, 'Tuesday Meetup');
        $members = [
            $this->makeMember(1, $series, ['en' => 'Bring a friend'], '2026-01-06'),
            $this->makeMember(2, $series, ['en' => 'Bring a friend'], '2026-02-03'),
        ];
        $service = $this->createService($series, $members);

        // Act
        $lanes = $service->getLanes();

        // Assert
        self::assertCount(1, $lanes);
        self::assertSame(7, $lanes[0]->seriesId);
        self::assertSame('Tuesday Meetup', $lanes[0]->seriesName);
        self::assertSame('en', $lanes[0]->locale);
        self::assertSame([1, 2], array_map(static fn($stop) => $stop->eventId, $lanes[0]->stops));
        self::assertSame(
            ['locale', 'rootCount', 'seriesId', 'seriesName', 'stops'],
            $this->sortedPropertyNames($lanes[0]),
        );
    }

    public function testIdenticalOccurrencesShareOneRootAndDoNotBranch(): void
    {
        // Arrange
        $series = $this->makeSeries(7, 'Tuesday Meetup');
        $members = [
            $this->makeMember(1, $series, ['en' => 'Bring a friend'], '2026-01-06'),
            $this->makeMember(2, $series, ['en' => 'Bring a friend'], '2026-02-03'),
        ];
        $service = $this->createService($series, $members);

        // Act
        $lanes = $service->getLanes();

        // Assert
        self::assertSame(1, $lanes[0]->rootCount);
        self::assertFalse($lanes[0]->isBranched());
        self::assertSame([1, 1], array_map(static fn($stop) => $stop->rootEventId, $lanes[0]->stops));
    }

    public function testEachLocaleGetsItsOwnLane(): void
    {
        // Arrange
        $series = $this->makeSeries(7, 'Tuesday Meetup');
        $members = [$this->makeMember(1, $series, ['en' => 'Bring a friend', 'de' => 'Bring jemanden mit'], '2026-01-06')];
        $service = $this->createService($series, $members);

        // Act
        $lanes = $service->getLanes();

        // Assert
        self::assertSame(['de', 'en'], array_map(static fn($lane) => $lane->locale, $lanes));
    }

    public function testEventIdFilterDropsSeriesWithNoMatchingMember(): void
    {
        // Arrange
        $series = $this->makeSeries(7, 'Tuesday Meetup');
        $members = [$this->makeMember(1, $series, ['en' => 'Bring a friend'], '2026-01-06')];
        $service = $this->createService($series, $members);

        // Act
        $lanes = $service->getLanes(eventIds: [999]);

        // Assert
        self::assertSame([], $lanes);
    }

    /**
     * @param array<Event> $members
     */
    private function createService(EventSeries $series, array $members): EventCanonicalOverviewService
    {
        $seriesRepository = $this->createStub(EventSeriesRepository::class);
        $seriesRepository->method('findAll')->willReturn([$series]);

        $eventRepository = $this->createStub(EventRepository::class);
        $eventRepository->method('findSeriesMembers')->willReturn($members);

        $markerRepository = $this->createStub(EventCanonicalRootRepository::class);
        $markerRepository->method('findBySeries')->willReturn([]);
        $markerRepository->method('findOneByEventAndLocale')->willReturn(null);

        return new EventCanonicalOverviewService(
            seriesRepository: $seriesRepository,
            eventRepository: $eventRepository,
            resolver: new EventCanonicalResolver($eventRepository, $markerRepository),
            similarityService: new EventSimilarityService(),
        );
    }

    private function makeSeries(int $id, string $name): EventSeries
    {
        $series = new EventSeriesStub();
        $series->setId($id);
        $series->setName($name);

        return $series;
    }

    /**
     * @param array<string, string> $contentByLocale
     */
    private function makeMember(int $id, EventSeries $series, array $contentByLocale, string $day): Event
    {
        $event = new EventStub();
        $event->setId($id);
        $event->setStart(new DateTime($day . ' 19:00'));
        $event->setSeries($series);

        foreach ($contentByLocale as $locale => $description) {
            $translation = new EventTranslation();
            $translation->setLanguage($locale);
            $translation->setTitle('Weekly meetup');
            $translation->setTeaser('');
            $translation->setDescription($description);
            $event->addTranslation($translation);
        }

        return $event;
    }

    /**
     * @return array<int, string>
     */
    private function sortedPropertyNames(object $value): array
    {
        $names = array_keys(get_object_vars($value));
        sort($names);

        return $names;
    }
}
