<?php declare(strict_types=1);

namespace Tests\Unit\Service\Event;

use App\Entity\Event;
use App\Entity\EventTranslation;
use App\EntityActionDispatcher;
use App\Enum\EventInterval;
use App\Enum\EventStatus;
use App\Repository\CmsBlockRepository;
use App\Repository\EventRepository;
use App\Repository\EventSeriesRepository;
use App\Service\Cms\CmsService;
use App\Service\Event\OccurrenceCalculator;
use App\Service\Event\RecurrenceResolver;
use App\Service\Event\RecurringEventService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Clock\MockClock;
use Tests\Unit\Stubs\EventSeriesStub;
use Tests\Unit\Stubs\EventStub;
use Tests\Unit\Stubs\UserStub;

class RecurringEventExtensionTest extends TestCase
{
    private const string NOW = '2026-06-15 12:00:00'; // a Monday, so weekday maths in the fixtures is readable

    private function makeSeries(int $id, ?EventInterval $rule, ?string $ruleSpec = null): EventSeriesStub
    {
        $series = new EventSeriesStub();
        $series->setId($id);
        $series->setName('Test Series');
        $series->setRule($rule);
        $series->setRuleSpec($ruleSpec);

        return $series;
    }

    private function makeFutureEvent(int $id, string $start, ?string $stop = null, EventStatus $status = EventStatus::Published): EventStub
    {
        $event = new EventStub();
        $event->setId($id);
        $event->setStart(new DateTime($start));
        $event->setStop($stop !== null ? new DateTime($stop) : null);
        $event->setStatus($status);

        return $event;
    }

    private function createService(EventRepository $repo, EntityManagerInterface $em, EventSeriesRepository $seriesRepo, string $now): RecurringEventService
    {
        return new RecurringEventService(
            repo: $repo,
            seriesRepo: $seriesRepo,
            em: $em,
            entityActionDispatcher: $this->createStub(EntityActionDispatcher::class),
            cmsBlockRepository: $this->createStub(CmsBlockRepository::class),
            cmsService: $this->createStub(CmsService::class),
            recurrenceResolver: new RecurrenceResolver(),
            calculator: new OccurrenceCalculator(),
            clock: new MockClock(new DateTimeImmutable($now)),
        );
    }

    private function makeTranslation(string $title): EventTranslation
    {
        $translation = new EventTranslation();
        $translation->setLanguage('en');
        $translation->setTitle($title);
        $translation->setTeaser($title . ' teaser');
        $translation->setDescription($title . ' description');

        return $translation;
    }

    /**
     * @return array{RecurringEventService, callable(): array<Event>}
     */
    private function createExtensionService(EventSeriesStub $series, ?EventStub $template, string $now = self::NOW): array
    {
        $seriesRepo = $this->createStub(EventSeriesRepository::class);
        $seriesRepo->method('findOpen')->willReturn([$series]);
        $seriesRepo->method('find')->willReturn($series);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findNewestSeriesMember')->willReturn($template);

        $created = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$created): void {
            if ($entity instanceof Event && $entity->getId() === null) {
                new ReflectionProperty(Event::class, 'id')->setValue($entity, 1000 + count($created));
                $created[] = $entity;
            }
        });

        return [
            $this->createService($repo, $em, $seriesRepo, $now),
            static function () use (&$created): array {
                return $created;
            },
        ];
    }

    public function testExtendRecurringEventsSourcesScheduleAndContentFromNewestMember(): void
    {
        // Arrange
        $series = $this->makeSeries(9, EventInterval::Weekly);

        $creator = new UserStub()->setId(77);
        $template = $this->makeFutureEvent(5, '2026-06-16 20:30', '2026-06-16 23:00');
        $template->setUser($creator);
        $template->addTranslation($this->makeTranslation('New Title'));

        [$service, $createdEvents] = $this->createExtensionService($series, $template);

        // Act
        $count = $service->extentRecurringEvents();

        // Assert
        $created = $createdEvents();
        static::assertCount($count, $created);
        static::assertSame('2026-06-23 20:30', $created[0]->getStart()->format('Y-m-d H:i'));
        static::assertSame('2026-09-15 20:30', $created[$count - 1]->getStart()->format('Y-m-d H:i'));
        foreach ($created as $event) {
            static::assertSame('20:30', $event->getStart()->format('H:i'));
            static::assertSame('23:00', $event->getStop()->format('H:i'));
            static::assertSame('New Title', $event->getTitle('en'));
            static::assertSame($series, $event->getSeries());
            static::assertSame($creator, $event->getUser());
            static::assertFalse($event->isInitial());
        }
    }

    public function testExtendRecurringEventsUsesTheOnlyMemberAsTemplate(): void
    {
        // Arrange
        $series = $this->makeSeries(9, EventInterval::Weekly);

        $template = $this->makeFutureEvent(1, '2026-06-14 19:00', '2026-06-14 22:00');
        $template->addTranslation($this->makeTranslation('Old Title'));

        [$service, $createdEvents] = $this->createExtensionService($series, $template);

        // Act
        $count = $service->extentRecurringEvents();

        // Assert
        $created = $createdEvents();
        static::assertGreaterThan(0, $count);
        static::assertSame('2026-06-21 19:00', $created[0]->getStart()->format('Y-m-d H:i'));
        foreach ($created as $event) {
            static::assertSame('19:00', $event->getStart()->format('H:i'));
            static::assertSame('Old Title', $event->getTitle('en'));
        }
    }

    public function testExtendCreatesAnOccurrenceFallingLaterToday(): void
    {
        // Arrange
        $series = $this->makeSeries(9, EventInterval::Weekly);
        $template = $this->makeFutureEvent(1, '2026-06-09 19:00');
        [$service, $createdEvents] = $this->createExtensionService($series, $template, '2026-06-16 08:00:00');

        // Act
        $service->extentRecurringEvents();

        // Assert
        static::assertSame('2026-06-16 19:00', $createdEvents()[0]->getStart()->format('Y-m-d H:i'));
    }

    public function testExtendSkipsAnOccurrenceThatAlreadyPassedToday(): void
    {
        // Arrange
        $series = $this->makeSeries(9, EventInterval::Weekly);
        $template = $this->makeFutureEvent(1, '2026-06-09 19:00');
        [$service, $createdEvents] = $this->createExtensionService($series, $template, '2026-06-16 20:00:00');

        // Act
        $service->extentRecurringEvents();

        // Assert
        static::assertSame('2026-06-23 19:00', $createdEvents()[0]->getStart()->format('Y-m-d H:i'));
    }

    public function testExtendReachesTheNextLeapDayOfAYearlySeries(): void
    {
        // Arrange
        $series = $this->makeSeries(9, EventInterval::Yearly);
        $template = $this->makeFutureEvent(1, '2028-02-29 19:00');
        [$service, $createdEvents] = $this->createExtensionService($series, $template, '2028-03-01 12:00:00');

        // Act
        $count = $service->extentRecurringEvents();

        // Assert
        static::assertSame(1, $count);
        static::assertSame('2032-02-29 19:00', $createdEvents()[0]->getStart()->format('Y-m-d H:i'));
    }

    private function generateCustomSeries(string $spec, string $anchor = '2026-06-16 19:00'): string
    {
        $series = $this->makeSeries(9, EventInterval::Custom, $spec);
        $template = $this->makeFutureEvent(1, $anchor);
        [$service, $createdEvents] = $this->createExtensionService($series, $template);

        $service->extentRecurringEvents();

        return implode(',', array_map(static fn(Event $event): string => $event->getStart()->format('Y-m-d H:i'), $createdEvents()));
    }

    public function testExtendGeneratesTheFirstWeekdayOfEachMonth(): void
    {
        // Act
        $starts = $this->generateCustomSeries('FREQ=MONTHLY;BYDAY=1SU');

        // Assert
        static::assertSame(
            '2026-07-05 19:00,2026-08-02 19:00,2026-09-06 19:00,2026-10-04 19:00,2026-11-01 19:00,2026-12-06 19:00',
            $starts,
        );
    }

    public function testExtendGeneratesTheLastWeekdayOfEachMonth(): void
    {
        // Act
        $starts = $this->generateCustomSeries('FREQ=MONTHLY;BYDAY=-1FR');

        // Assert
        static::assertSame(
            '2026-06-26 19:00,2026-07-31 19:00,2026-08-28 19:00,2026-09-25 19:00,2026-10-30 19:00,2026-11-27 19:00',
            $starts,
        );
    }

    public function testExtendSkipsMonthsWithoutADayThirtyOne(): void
    {
        // Act
        $starts = $this->generateCustomSeries('FREQ=MONTHLY;BYMONTHDAY=31');

        // Assert
        static::assertSame('2026-07-31 19:00,2026-08-31 19:00,2026-10-31 19:00', $starts);
    }

    public function testExtendGeneratesTheLastDayOfEachMonth(): void
    {
        // Act
        $starts = $this->generateCustomSeries('FREQ=MONTHLY;BYMONTHDAY=-1');

        // Assert
        static::assertSame(
            '2026-06-30 19:00,2026-07-31 19:00,2026-08-31 19:00,2026-09-30 19:00,2026-10-31 19:00,2026-11-30 19:00',
            $starts,
        );
    }

    public function testExtendKeepsQuarterlySpacing(): void
    {
        // Act
        $starts = $this->generateCustomSeries('FREQ=MONTHLY;INTERVAL=3;BYDAY=3MO');

        // Assert
        static::assertSame('2026-09-21 19:00,2026-12-21 19:00,2027-03-15 19:00', $starts);
    }

    public function testExtendGeneratesSeveralWeekdaysPerWeek(): void
    {
        // Act
        $starts = $this->generateCustomSeries('FREQ=WEEKLY;BYDAY=MO,WE,FR', '2026-06-16 19:00');

        // Assert
        static::assertStringStartsWith('2026-06-17 19:00,2026-06-19 19:00,2026-06-22 19:00,2026-06-24 19:00', $starts);
    }

    public function testExtendGeneratesSeveralOrdinalsPerMonth(): void
    {
        // Act
        $starts = $this->generateCustomSeries('FREQ=MONTHLY;BYDAY=1FR,3FR');

        // Assert
        static::assertSame(
            '2026-06-19 19:00,2026-07-03 19:00,2026-07-17 19:00,2026-08-07 19:00,2026-08-21 19:00,'
            . '2026-09-04 19:00,2026-09-18 19:00,2026-10-02 19:00,2026-10-16 19:00,2026-11-06 19:00,'
            . '2026-11-20 19:00,2026-12-04 19:00',
            $starts,
        );
    }

    public function testExtendKeepsTheFirstOccurrenceWhenTheAnchorIsOffPattern(): void
    {
        // Act
        // dropping the head of the set by position would silently eat August's real date
        $starts = $this->generateCustomSeries('FREQ=MONTHLY;BYDAY=1SU', '2026-07-08 19:00');

        // Assert
        static::assertStringStartsWith('2026-08-02 19:00', $starts);
    }

    public function testExtendSkipsASeriesWhoseCustomSpecCannotBeParsed(): void
    {
        // Arrange
        $series = $this->makeSeries(9, EventInterval::Custom, 'FREQ=NONSENSE');
        $template = $this->makeFutureEvent(1, '2026-06-16 19:00');
        [$service, $createdEvents] = $this->createExtensionService($series, $template);

        // Act
        $count = $service->extentRecurringEvents();

        // Assert
        static::assertSame(0, $count);
        static::assertCount(0, $createdEvents());
    }

    public function testExtendSkipsASeriesWhoseCustomSpecCanNeverFire(): void
    {
        // Arrange
        $series = $this->makeSeries(9, EventInterval::Custom, 'FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=30');
        $template = $this->makeFutureEvent(1, '2026-06-16 19:00');
        [$service, $createdEvents] = $this->createExtensionService($series, $template);

        // Act
        $count = $service->extentRecurringEvents();

        // Assert
        static::assertSame(0, $count);
        static::assertCount(0, $createdEvents());
    }

    public function testExtendRecurringEventsSkipsSeriesWithoutUsableTemplate(): void
    {
        // Arrange
        $series = $this->makeSeries(9, EventInterval::Weekly);

        [$service, $createdEvents] = $this->createExtensionService($series, null);

        // Act
        $count = $service->extentRecurringEvents();

        // Assert
        static::assertSame(0, $count);
        static::assertCount(0, $createdEvents());
    }
}
