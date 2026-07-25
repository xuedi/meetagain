<?php declare(strict_types=1);

namespace Tests\Unit\Service\Seo;

use App\Entity\Event;
use App\Entity\EventCanonicalRoot;
use App\Entity\EventSeries;
use App\Entity\EventTranslation;
use App\Enum\EventCanonicalRootType;
use App\Repository\EventCanonicalRootRepository;
use App\Repository\EventRepository;
use App\Repository\EventSeriesRepository;
use App\Service\Config\ConfigService;
use App\Service\Seo\EventCanonicalRebuildService;
use App\Service\Seo\EventCanonicalResolver;
use App\Service\Seo\EventSimilarityService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Stubs\EventSeriesStub;
use Tests\Unit\Stubs\EventStub;

class EventCanonicalRebuildServiceTest extends TestCase
{
    /** @var array<EventCanonicalRoot> */
    private array $persisted = [];

    /** @var array<EventCanonicalRoot> */
    private array $removed = [];

    private function makeSeries(int $id = 1): EventSeries
    {
        $series = new EventSeriesStub();
        $series->setId($id);

        return $series;
    }

    /**
     * @param array<string, string> $contentByLocale locale => description
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
     * @param array<Event> $members
     * @param array<EventCanonicalRoot> $existingMarkers
     */
    private function createService(array $members, array $existingMarkers = [], int $threshold = 20): EventCanonicalRebuildService
    {
        $this->persisted = [];
        $this->removed = [];

        $eventRepository = $this->createStub(EventRepository::class);
        $eventRepository->method('findSeriesMembers')->willReturn($members);
        $eventRepository->method('findFollowUpEvents')->willReturn($members);

        $markerRepository = $this->createStub(EventCanonicalRootRepository::class);
        $markerRepository->method('findBySeries')->willReturn($existingMarkers);
        $markerRepository->method('findOneByEventAndLocale')->willReturnCallback(
            static function (int $eventId, string $locale) use ($existingMarkers): ?EventCanonicalRoot {
                return array_find(
                    $existingMarkers,
                    static fn(EventCanonicalRoot $m) => $m->getEvent()?->getId() === $eventId && $m->getLocale() === $locale,
                );
            },
        );

        $configService = $this->createStub(ConfigService::class);
        $configService->method('getEventCanonicalThreshold')->willReturn($threshold);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof EventCanonicalRoot) {
                $this->persisted[] = $entity;
            }
        });
        $entityManager->method('remove')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof EventCanonicalRoot) {
                $this->removed[] = $entity;
            }
        });

        return new EventCanonicalRebuildService(
            eventRepository: $eventRepository,
            seriesRepository: $this->createStub(EventSeriesRepository::class),
            markerRepository: $markerRepository,
            similarityService: new EventSimilarityService(),
            resolver: new EventCanonicalResolver($eventRepository, $markerRepository),
            configService: $configService,
            entityManager: $entityManager,
        );
    }

    /**
     * @return array<string, array<int, EventCanonicalRootType>> locale => eventId => type
     */
    private function writtenMarkers(): array
    {
        $written = [];
        foreach ($this->persisted as $marker) {
            $written[$marker->getLocale()][(int) $marker->getEvent()?->getId()] = $marker->getType();
        }

        return $written;
    }

    public function testNeverBranchingSeriesProducesNoMarkers(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $members = [
            $this->makeMember(1, $series, ['en' => 'We play board games every week.'], '2026-01-01'),
            $this->makeMember(2, $series, ['en' => 'We play board games every week.'], '2026-01-08'),
            $this->makeMember(3, $series, ['en' => 'We play board games every week.'], '2026-01-15'),
        ];
        $service = $this->createService($members);

        // Act
        $summaries = $service->rebuildSeries($series);

        // Assert
        static::assertSame([], $this->writtenMarkers());
        static::assertSame(3, $summaries[0]->membersScanned);
        static::assertSame(0, $summaries[0]->rootsWritten);
    }

    public function testPermanentContentChangeProducesOneRootAtTheChange(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $old = 'We play board games every week in the back room.';
        $new = 'Karaoke night with a rented machine and a full song catalogue.';
        $members = [
            $this->makeMember(1, $series, ['en' => $old], '2026-01-01'),
            $this->makeMember(2, $series, ['en' => $old], '2026-01-08'),
            $this->makeMember(3, $series, ['en' => $new], '2026-01-15'),
            $this->makeMember(4, $series, ['en' => $new], '2026-01-22'),
            $this->makeMember(5, $series, ['en' => $new], '2026-01-29'),
        ];
        $service = $this->createService($members);

        // Act
        $summaries = $service->rebuildSeries($series);

        // Assert
        static::assertSame([3 => EventCanonicalRootType::Root], $this->writtenMarkers()['en']);
        static::assertSame(1, $summaries[0]->rootsWritten);
        static::assertSame(0, $summaries[0]->detachedWritten);
    }

    public function testSingleOddOccurrenceProducesOneDetachedMarker(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $usual = 'We play board games every week in the back room.';
        $oneOff = 'Cancelled this week, we go bowling in the city centre instead.';
        $members = [
            $this->makeMember(1, $series, ['en' => $usual], '2026-01-01'),
            $this->makeMember(2, $series, ['en' => $oneOff], '2026-01-08'),
            $this->makeMember(3, $series, ['en' => $usual], '2026-01-15'),
            $this->makeMember(4, $series, ['en' => $usual], '2026-01-22'),
        ];
        $service = $this->createService($members);

        // Act
        $service->rebuildSeries($series);

        // Assert
        static::assertSame([2 => EventCanonicalRootType::Detached], $this->writtenMarkers()['en']);
    }

    public function testDivergedLastMemberIsDetached(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $usual = 'We play board games every week in the back room.';
        $members = [
            $this->makeMember(1, $series, ['en' => $usual], '2026-01-01'),
            $this->makeMember(2, $series, ['en' => $usual], '2026-01-08'),
            $this->makeMember(3, $series, ['en' => 'Final session, we hand back the keys and clear the shelves.'], '2026-01-15'),
        ];
        $service = $this->createService($members);

        // Act
        $service->rebuildSeries($series);

        // Assert
        static::assertSame([3 => EventCanonicalRootType::Detached], $this->writtenMarkers()['en']);
    }

    public function testTwoSuccessiveBranchesProduceTwoRoots(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $first = 'We play board games every week in the back room.';
        $second = 'Karaoke night with a rented machine and a song catalogue.';
        $third = 'Filmabend mit Projektor, Leinwand und selbstgemachtem Popcorn.';
        $members = [
            $this->makeMember(1, $series, ['en' => $first], '2026-01-01'),
            $this->makeMember(2, $series, ['en' => $first], '2026-01-08'),
            $this->makeMember(3, $series, ['en' => $second], '2026-01-15'),
            $this->makeMember(4, $series, ['en' => $second], '2026-01-22'),
            $this->makeMember(5, $series, ['en' => $third], '2026-01-29'),
            $this->makeMember(6, $series, ['en' => $third], '2026-02-05'),
        ];
        $service = $this->createService($members);

        // Act
        $service->rebuildSeries($series);

        // Assert
        static::assertSame([
            3 => EventCanonicalRootType::Root,
            5 => EventCanonicalRootType::Root,
        ], $this->writtenMarkers()['en']);
    }

    public function testSeriesBranchingInOneLocaleOnlyProducesMarkersThere(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $english = 'We play board games every week in the back room.';
        $german = 'Wir spielen jede Woche Brettspiele im Hinterzimmer.';
        $members = [
            $this->makeMember(1, $series, ['en' => $english, 'de' => $german], '2026-01-01'),
            $this->makeMember(2, $series, ['en' => $english, 'de' => $german], '2026-01-08'),
            $this->makeMember(3, $series, ['en' => $english, 'de' => 'Karaokeabend mit gemieteter Anlage und Liederbuch.'], '2026-01-15'),
            $this->makeMember(4, $series, ['en' => $english, 'de' => 'Karaokeabend mit gemieteter Anlage und Liederbuch.'], '2026-01-22'),
        ];
        $service = $this->createService($members);

        // Act
        $service->rebuildSeries($series);

        // Assert
        $written = $this->writtenMarkers();
        static::assertArrayNotHasKey('en', $written);
        static::assertSame([3 => EventCanonicalRootType::Root], $written['de']);
    }

    public function testRerunningTheRebuildProducesIdenticalMarkers(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $usual = 'We play board games every week in the back room.';
        $members = [
            $this->makeMember(1, $series, ['en' => $usual], '2026-01-01'),
            $this->makeMember(2, $series, ['en' => 'Bowling in the city centre this once, no board games.'], '2026-01-08'),
            $this->makeMember(3, $series, ['en' => $usual], '2026-01-15'),
        ];

        // Act
        $service = $this->createService($members);
        $service->rebuildSeries($series);
        $firstRun = $this->writtenMarkers();

        $service = $this->createService($members, $this->persisted);
        $service->rebuildSeries($series);
        $secondRun = $this->writtenMarkers();

        // Assert
        static::assertSame($firstRun, $secondRun);
    }

    public function testEditThatChangesNothingWritesNoMarker(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $usual = 'We play board games every week in the back room.';
        $members = [
            $this->makeMember(1, $series, ['en' => $usual], '2026-01-01'),
            $this->makeMember(2, $series, ['en' => $usual], '2026-01-08'),
        ];
        $service = $this->createService($members);

        // Act
        $service->refreshAfterEdit($members[1], false);

        // Assert
        static::assertSame([], $this->persisted);
    }

    public function testHeavyEditWithAllFollowingProducesARootAndWithoutItADetached(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $members = [
            $this->makeMember(1, $series, ['en' => 'We play board games every week in the back room.'], '2026-01-01'),
            $this->makeMember(2, $series, ['en' => 'Karaoke night with a rented machine and a full song catalogue.'], '2026-01-08'),
        ];

        // Act
        $service = $this->createService($members);
        $service->refreshAfterEdit($members[1], true);
        $withAllFollowing = $this->persisted[0]->getType();

        $service = $this->createService($members);
        $service->refreshAfterEdit($members[1], false);
        $withoutAllFollowing = $this->persisted[0]->getType();

        // Assert
        static::assertSame(EventCanonicalRootType::Root, $withAllFollowing);
        static::assertSame(EventCanonicalRootType::Detached, $withoutAllFollowing);
    }

    public function testEditBackUnderThresholdRemovesTheExistingMarker(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $usual = 'We play board games every week in the back room.';
        $members = [
            $this->makeMember(1, $series, ['en' => $usual], '2026-01-01'),
            $this->makeMember(2, $series, ['en' => $usual], '2026-01-08'),
        ];
        $marker = (new EventCanonicalRoot())
            ->setEvent($members[1])
            ->setLocale('en')
            ->setType(EventCanonicalRootType::Detached);
        $service = $this->createService($members, [$marker]);

        // Act
        $service->refreshAfterEdit($members[1], false);

        // Assert
        static::assertSame([$marker], $this->removed);
        static::assertSame([], $this->persisted);
    }

    public function testEditInOneLocaleLeavesTheOtherLocaleUnmarked(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $english = 'We play board games every week in the back room.';
        $german = 'Wir spielen jede Woche Brettspiele im Hinterzimmer.';
        $members = [
            $this->makeMember(1, $series, ['en' => $english, 'de' => $german], '2026-01-01'),
            $this->makeMember(2, $series, ['en' => $english, 'de' => 'Karaokeabend mit gemieteter Anlage und vollem Liederbuch.'], '2026-01-08'),
        ];
        $service = $this->createService($members);

        // Act
        $service->refreshAfterEdit($members[1], true);

        // Assert
        static::assertCount(1, $this->persisted);
        static::assertSame('de', $this->persisted[0]->getLocale());
    }

    public function testFirstMemberOfTheSeriesNeverGetsAMarker(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $members = [
            $this->makeMember(1, $series, ['en' => 'Completely rewritten opening session of the series.'], '2026-01-01'),
            $this->makeMember(2, $series, ['en' => 'We play board games every week in the back room.'], '2026-01-08'),
        ];
        $service = $this->createService($members);

        // Act
        $service->refreshAfterEdit($members[0], true);

        // Assert
        static::assertSame([], $this->persisted);
    }

    public function testMarkersThatNoLongerApplyAreCountedAsRemoved(): void
    {
        // Arrange
        $series = $this->makeSeries();
        $usual = 'We play board games every week in the back room.';
        $members = [
            $this->makeMember(1, $series, ['en' => $usual], '2026-01-01'),
            $this->makeMember(2, $series, ['en' => $usual], '2026-01-08'),
        ];
        $stale = (new EventCanonicalRoot())
            ->setEvent($members[1])
            ->setLocale('en')
            ->setType(EventCanonicalRootType::Detached);
        $service = $this->createService($members, [$stale]);

        // Act
        $summaries = $service->rebuildSeries($series);

        // Assert
        static::assertSame([], $this->writtenMarkers());
        static::assertSame(1, $summaries[0]->markersRemoved);
    }
}
