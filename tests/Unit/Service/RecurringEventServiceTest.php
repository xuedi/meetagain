<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\EventTranslation;
use App\EntityActionDispatcher;
use App\Enum\CronTaskStatus;
use App\Enum\EventInterval;
use App\Enum\EventStatus;
use App\Enum\RealignmentOutcome;
use App\Repository\CmsBlockRepository;
use App\Repository\EventRepository;
use App\Repository\EventSeriesRepository;
use App\Service\Cms\CmsService;
use App\Service\Event\RecurringEventService;
use App\ValueObject\RealignmentItem;
use App\ValueObject\RealignmentPlan;
use App\ValueObject\ScheduleChange;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\Unit\Stubs\EventSeriesStub;
use Tests\Unit\Stubs\EventStub;
use Tests\Unit\Stubs\UserStub;

class RecurringEventServiceTest extends TestCase
{
    // ---- helpers ----

    private function makeSeries(int $id, ?EventInterval $rule): EventSeriesStub
    {
        $series = new EventSeriesStub();
        $series->setId($id);
        $series->setName('Test Series');
        $series->setRule($rule);

        return $series;
    }

    private function makeEvent(int $id, EventStatus $status = EventStatus::Published): EventStub
    {
        $event = new EventStub();
        $event->setId($id);
        $event->setStart(new DateTime('2025-06-01 19:00'));
        $event->setStatus($status);

        return $event;
    }

    private function createService(EventRepository $repo, EntityManagerInterface $em, ?EventSeriesRepository $seriesRepo = null): RecurringEventService
    {
        return new RecurringEventService(
            repo: $repo,
            seriesRepo: $seriesRepo ?? $this->createStub(EventSeriesRepository::class),
            em: $em,
            entityActionDispatcher: $this->createStub(EntityActionDispatcher::class),
            cmsBlockRepository: $this->createStub(CmsBlockRepository::class),
            cmsService: $this->createStub(CmsService::class),
        );
    }

    // ---- runCronTask ----

    public function testRunCronTaskReturnsOkResult(): void
    {
        // Arrange
        $seriesRepo = $this->createStub(EventSeriesRepository::class);
        $seriesRepo->method('findOpen')->willReturn([]);
        $service = $this->createService($this->createStub(EventRepository::class), $this->createStub(EntityManagerInterface::class), $seriesRepo);

        // Act
        $result = $service->runCronTask($this->createStub(OutputInterface::class));

        // Assert
        static::assertSame('recurring-events', $result->identifier);
        static::assertSame(CronTaskStatus::ok, $result->status);
        static::assertSame('0 events extended', $result->message);
    }

    // ---- updateRecurringEvents: early-return cases (data provider) ----

    #[DataProvider('updateRecurringEventsReturnsZeroProvider')]
    public function testUpdateRecurringEventsReturnsZero(EventStub $event): void
    {
        // Arrange
        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createService($repo, $em);

        // Act
        $result = $service->updateRecurringEvents($event);

        // Assert
        static::assertSame(0, $result);
    }

    public static function updateRecurringEventsReturnsZeroProvider(): iterable
    {
        $noSeries = new EventStub();
        $noSeries->setId(1);
        $noSeries->setStart(new DateTime('2025-06-01 19:00'));
        yield 'event without a series → returns 0' => [
            'event' => $noSeries,
        ];

        $memberWithoutFollowUps = new EventStub();
        $memberWithoutFollowUps->setId(2);
        $memberWithoutFollowUps->setStart(new DateTime('2025-06-01 19:00'));
        $memberWithoutFollowUps->setSeries(new EventSeriesStub()->setId(9)->setRule(EventInterval::Weekly));
        yield 'series member with zero follow-ups → returns 0' => [
            'event' => $memberWithoutFollowUps,
        ];
    }

    // ---- updateRecurringEvents: member with unlocked follow-ups → counts and persists ----

    public function testUpdateRecurringEventsMemberWithTwoUnlockedFollowUpsReturnsTwo(): void
    {
        // Arrange
        $anchor = $this->makeEvent(1);
        $anchor->setSeries($this->makeSeries(9, EventInterval::Weekly));

        $child1 = $this->makeEvent(2);
        $child2 = $this->makeEvent(3);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$child1, $child2]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->once())->method('flush');

        $service = $this->createService($repo, $em);

        // Act
        $result = $service->updateRecurringEvents($anchor);

        // Assert
        static::assertSame(2, $result);
    }

    public function testUpdateRecurringEventsLockedFollowUpIsSkipped(): void
    {
        // Arrange
        $anchor = $this->makeEvent(1);
        $anchor->setSeries($this->makeSeries(9, EventInterval::Monthly));

        $lockedChild = $this->makeEvent(2, EventStatus::Locked);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$lockedChild]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = $this->createService($repo, $em);

        // Act
        $result = $service->updateRecurringEvents($anchor);

        // Assert
        static::assertSame(0, $result);
    }

    public function testUpdateRecurringEventsMixedFollowUpsSkipsLockedReturnsTwoUpdated(): void
    {
        // Arrange
        $anchor = $this->makeEvent(1);
        $anchor->setSeries($this->makeSeries(9, EventInterval::Weekly));

        $lockedChild = $this->makeEvent(2, EventStatus::Locked);
        $unlocked1 = $this->makeEvent(3);
        $unlocked2 = $this->makeEvent(4);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$lockedChild, $unlocked1, $unlocked2]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->once())->method('flush');

        $service = $this->createService($repo, $em);

        // Act
        $result = $service->updateRecurringEvents($anchor);

        // Assert
        static::assertSame(2, $result);
    }

    // ---- updateRecurringEvents: lookup keyed on the series ----

    public function testUpdateRecurringEventsUsesSeriesIdForFollowUpLookup(): void
    {
        // Arrange
        $member = $this->makeEvent(10);
        $member->setSeries($this->makeSeries(42, EventInterval::Monthly));

        $followUp1 = $this->makeEvent(11);
        $followUp2 = $this->makeEvent(12);

        $repo = $this->createMock(EventRepository::class);
        $repo->expects($this->once())->method('findFollowUpEvents')->with(42, $member->getStart())->willReturn([$followUp1, $followUp2]);

        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createService($repo, $em);

        // Act
        $result = $service->updateRecurringEvents($member);

        // Assert
        static::assertSame(2, $result);
    }

    public function testUpdateRecurringEventsExcludesTheAnchorItself(): void
    {
        // Arrange: the series-keyed query can return the anchor among the follow-ups
        $anchor = $this->makeEvent(1);
        $anchor->setSeries($this->makeSeries(9, EventInterval::Weekly));

        $child = $this->makeEvent(2);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$anchor, $child]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($child);
        $em->expects($this->once())->method('flush');

        $service = $this->createService($repo, $em);

        // Act
        $result = $service->updateRecurringEvents($anchor);

        // Assert
        static::assertSame(1, $result);
    }

    // ---- updateRecurringEvents: translation propagation ----

    public function testUpdateRecurringEventsPropagatesTranslationsToFollowUps(): void
    {
        // Arrange: anchor event with one translation
        $anchor = $this->makeEvent(1);
        $anchor->setSeries($this->makeSeries(9, EventInterval::Weekly));

        $translation = new EventTranslation();
        $translation->setLanguage('en');
        $translation->setTitle('Anchor Title');
        $translation->setTeaser('Anchor Teaser');
        $translation->setDescription('Anchor Description');
        $anchor->addTranslation($translation);

        // Arrange: follow-up with no existing translation for 'en'
        $child = $this->makeEvent(2);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$child]);

        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createService($repo, $em);

        // Act
        $service->updateRecurringEvents($anchor);

        // Assert: follow-up now has the 'en' translation with propagated fields
        $childTranslation = $child->findTranslation('en');
        static::assertNotNull($childTranslation);
        static::assertSame('Anchor Title', $childTranslation->getTitle());
        static::assertSame('Anchor Teaser', $childTranslation->getTeaser());
        static::assertSame('Anchor Description', $childTranslation->getDescription());
    }

    // ---- planRealignment ----

    private function makeFutureEvent(int $id, string $start, ?string $stop = null, EventStatus $status = EventStatus::Published): EventStub
    {
        $event = new EventStub();
        $event->setId($id);
        $event->setStart(new DateTime($start));
        $event->setStop($stop !== null ? new DateTime($stop) : null);
        $event->setStatus($status);

        return $event;
    }

    private function makeWeeklyChange(): ScheduleChange
    {
        return new ScheduleChange(
            oldStart: new DateTimeImmutable('2030-01-02 19:00'),
            oldStop: new DateTimeImmutable('2030-01-02 22:00'),
            oldRule: EventInterval::Weekly,
            newStart: new DateTimeImmutable('2030-01-12 20:00'),
            newStop: new DateTimeImmutable('2030-01-12 23:30'),
            newRule: EventInterval::Weekly,
        );
    }

    public function testPlanRealignmentWeeklyShiftMapsChildrenOntoNewPattern(): void
    {
        // Arrange
        $anchor = $this->makeFutureEvent(1, '2030-01-02 19:00', '2030-01-02 22:00');
        $anchor->setSeries($this->makeSeries(9, EventInterval::Weekly));

        $child1 = $this->makeFutureEvent(2, '2030-01-09 19:00', '2030-01-09 22:00');
        $child2 = $this->makeFutureEvent(3, '2030-01-16 19:00', '2030-01-16 22:00');

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$child1, $child2]);
        $repo->method('getRsvpCounts')->willReturn([2 => 3, 3 => 0]);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        // Act
        $plan = $service->planRealignment($anchor, $this->makeWeeklyChange());

        // Assert
        static::assertSame(9, $plan->seriesId);
        static::assertSame(EventInterval::Weekly, $plan->rule);
        static::assertCount(2, $plan->items);
        static::assertSame('2030-01-19 20:00', $plan->items[0]->newStart->format('Y-m-d H:i'));
        static::assertSame('2030-01-19 23:30', $plan->items[0]->newStop->format('Y-m-d H:i'));
        static::assertSame(3, $plan->items[0]->rsvpCount);
        static::assertSame(RealignmentOutcome::Moved, $plan->items[0]->outcome);
        static::assertSame('2030-01-26 20:00', $plan->items[1]->newStart->format('Y-m-d H:i'));
        static::assertSame(RealignmentOutcome::Moved, $plan->items[1]->outcome);
        static::assertSame(2, $plan->movedCount());
    }

    public function testPlanRealignmentRuleChangeToMonthlyMapsAllChildrenBeyondOldSpacing(): void
    {
        // Arrange
        $anchor = $this->makeFutureEvent(1, '2030-01-02 19:00');
        $anchor->setSeries($this->makeSeries(9, EventInterval::Weekly));

        $child1 = $this->makeFutureEvent(2, '2030-01-09 19:00');
        $child2 = $this->makeFutureEvent(3, '2030-01-16 19:00');

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$child1, $child2]);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        $change = new ScheduleChange(
            oldStart: new DateTimeImmutable('2030-01-02 19:00'),
            oldStop: null,
            oldRule: EventInterval::Weekly,
            newStart: new DateTimeImmutable('2030-01-12 20:00'),
            newStop: null,
            newRule: EventInterval::Monthly,
        );

        // Act
        $plan = $service->planRealignment($anchor, $change);

        // Assert: nothing dropped, monthly occurrences continue past the old weekly spacing
        static::assertCount(2, $plan->items);
        static::assertSame('2030-02-12 20:00', $plan->items[0]->newStart->format('Y-m-d H:i'));
        static::assertNull($plan->items[0]->newStop);
        static::assertSame('2030-03-12 20:00', $plan->items[1]->newStart->format('Y-m-d H:i'));
    }

    public function testPlanRealignmentLockedChildKeepsDateAndDoesNotConsumeOccurrenceSlot(): void
    {
        // Arrange
        $anchor = $this->makeFutureEvent(1, '2030-01-02 19:00', '2030-01-02 22:00');
        $anchor->setSeries($this->makeSeries(9, EventInterval::Weekly));

        $lockedChild = $this->makeFutureEvent(2, '2030-01-09 19:00', '2030-01-09 22:00', EventStatus::Locked);
        $realignable = $this->makeFutureEvent(3, '2030-01-16 19:00', '2030-01-16 22:00');

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$lockedChild, $realignable]);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        // Act
        $plan = $service->planRealignment($anchor, $this->makeWeeklyChange());

        // Assert: the realignable child takes the occurrence the locked one would have taken
        static::assertSame(RealignmentOutcome::SkippedLocked, $plan->items[0]->outcome);
        static::assertNull($plan->items[0]->newStart);
        static::assertSame('2030-01-09 19:00', $plan->items[0]->currentStart->format('Y-m-d H:i'));
        static::assertSame(RealignmentOutcome::Moved, $plan->items[1]->outcome);
        static::assertSame('2030-01-19 20:00', $plan->items[1]->newStart->format('Y-m-d H:i'));
    }

    public function testPlanRealignmentCanceledChildIsSkipped(): void
    {
        // Arrange
        $anchor = $this->makeFutureEvent(1, '2030-01-02 19:00');
        $anchor->setSeries($this->makeSeries(9, EventInterval::Weekly));

        $canceledChild = $this->makeFutureEvent(2, '2030-01-09 19:00');
        $canceledChild->setCanceled(true);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$canceledChild]);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        // Act
        $plan = $service->planRealignment($anchor, $this->makeWeeklyChange());

        // Assert
        static::assertSame(RealignmentOutcome::SkippedCanceled, $plan->items[0]->outcome);
        static::assertNull($plan->items[0]->newStart);
        static::assertSame(0, $plan->movedCount());
    }

    public function testPlanRealignmentChildAlreadyOnPatternIsDateUnchanged(): void
    {
        // Arrange: anchor keeps its start, one child sits on the pattern, one is off by a day
        $anchor = $this->makeFutureEvent(1, '2030-01-05 19:00');
        $anchor->setSeries($this->makeSeries(9, EventInterval::Weekly));

        $onPattern = $this->makeFutureEvent(2, '2030-01-12 19:00');
        $offPattern = $this->makeFutureEvent(3, '2030-01-18 19:00');

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$onPattern, $offPattern]);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        $change = new ScheduleChange(
            oldStart: new DateTimeImmutable('2030-01-05 19:00'),
            oldStop: null,
            oldRule: EventInterval::Weekly,
            newStart: new DateTimeImmutable('2030-01-05 19:00'),
            newStop: null,
            newRule: EventInterval::Weekly,
        );

        // Act
        $plan = $service->planRealignment($anchor, $change);

        // Assert
        static::assertSame(RealignmentOutcome::DateUnchanged, $plan->items[0]->outcome);
        static::assertSame(RealignmentOutcome::Moved, $plan->items[1]->outcome);
        static::assertSame('2030-01-19 19:00', $plan->items[1]->newStart->format('Y-m-d H:i'));
    }

    public function testPlanRealignmentCollectsChildrenFromOldStart(): void
    {
        // Arrange: anchor moves later; a child between old and new start must stay included
        $anchor = $this->makeFutureEvent(1, '2030-01-02 19:00');
        $anchor->setSeries($this->makeSeries(9, EventInterval::Weekly));

        $betweenChild = $this->makeFutureEvent(2, '2030-01-09 19:00');

        $repo = $this->createMock(EventRepository::class);
        $repo
            ->expects($this->once())
            ->method('findFollowUpEvents')
            ->with(9, self::callback(static fn(DateTimeImmutable $bound): bool => $bound->format('Y-m-d H:i') === '2030-01-02 19:00'))
            ->willReturn([$betweenChild]);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        // Act
        $plan = $service->planRealignment($anchor, $this->makeWeeklyChange());

        // Assert
        static::assertCount(1, $plan->items);
        static::assertSame(2, $plan->items[0]->eventId);
    }

    public function testPlanRealignmentExcludesPastChildren(): void
    {
        // Arrange
        $anchor = $this->makeFutureEvent(1, '2020-01-02 19:00');
        $anchor->setSeries($this->makeSeries(9, EventInterval::Weekly));

        $pastChild = $this->makeFutureEvent(2, '2020-01-09 19:00');
        $futureChild = $this->makeFutureEvent(3, '2030-01-16 19:00');

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$pastChild, $futureChild]);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        // Act
        $plan = $service->planRealignment($anchor, $this->makeWeeklyChange());

        // Assert
        static::assertCount(1, $plan->items);
        static::assertSame(3, $plan->items[0]->eventId);
    }

    public function testPlanRealignmentWithoutSeriesReturnsEmptyPlan(): void
    {
        // Arrange
        $anchor = $this->makeFutureEvent(1, '2030-01-02 19:00');

        $service = $this->createService($this->createStub(EventRepository::class), $this->createStub(EntityManagerInterface::class));

        // Act
        $plan = $service->planRealignment($anchor, $this->makeWeeklyChange());

        // Assert
        static::assertTrue($plan->isEmpty());
        static::assertNull($plan->seriesId);
        static::assertNull($plan->rule);
    }

    public function testPlanRealignmentOnClosedSeriesReturnsEmptyPlan(): void
    {
        // Arrange: anchor belongs to a closed series (rule = null) and no rule change is submitted
        $anchor = $this->makeFutureEvent(2, '2030-01-09 19:00');
        $anchor->setSeries($this->makeSeries(9, null));

        $service = $this->createService($this->createStub(EventRepository::class), $this->createStub(EntityManagerInterface::class));

        $change = new ScheduleChange(
            oldStart: new DateTimeImmutable('2030-01-09 19:00'),
            oldStop: null,
            oldRule: null,
            newStart: new DateTimeImmutable('2030-01-10 19:00'),
            newStop: null,
            newRule: null,
        );

        // Act
        $plan = $service->planRealignment($anchor, $change);

        // Assert
        static::assertTrue($plan->isEmpty());
        static::assertNull($plan->seriesId);
        static::assertNull($plan->rule);
    }

    public function testPlanRealignmentResolvesRuleFromSeriesWhenChangeCarriesNone(): void
    {
        // Arrange: the change carries no rule at all - the series rule applies
        $anchor = $this->makeFutureEvent(1, '2030-01-02 19:00');
        $anchor->setSeries($this->makeSeries(9, EventInterval::Weekly));

        $child = $this->makeFutureEvent(2, '2030-01-09 19:00');

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$child]);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        $change = new ScheduleChange(
            oldStart: new DateTimeImmutable('2030-01-02 19:00'),
            oldStop: null,
            oldRule: null,
            newStart: new DateTimeImmutable('2030-01-03 19:00'),
            newStop: null,
            newRule: null,
        );

        // Act
        $plan = $service->planRealignment($anchor, $change);

        // Assert
        static::assertSame(EventInterval::Weekly, $plan->rule);
        static::assertSame(9, $plan->seriesId);
        static::assertCount(1, $plan->items);
    }

    public function testPlanRealignmentRuleChangeToNonRecurringReturnsEmptyPlan(): void
    {
        // Arrange: closing the series - members keep their dates, nothing realigns
        $anchor = $this->makeFutureEvent(1, '2030-01-02 19:00');
        $anchor->setSeries($this->makeSeries(9, EventInterval::Weekly));

        $child = $this->makeFutureEvent(2, '2030-01-09 19:00');

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$child]);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        $change = new ScheduleChange(
            oldStart: new DateTimeImmutable('2030-01-02 19:00'),
            oldStop: null,
            oldRule: EventInterval::Weekly,
            newStart: new DateTimeImmutable('2030-01-02 19:00'),
            newStop: null,
            newRule: null,
        );

        // Act
        $plan = $service->planRealignment($anchor, $change);

        // Assert
        static::assertTrue($plan->isEmpty());
        static::assertNull($plan->rule);
    }

    // ---- executeRealignment ----

    public function testExecuteRealignmentMovesChildRemovesRsvpsAndResetsSentFlags(): void
    {
        // Arrange
        $attendee1 = new UserStub()->setId(10);
        $attendee2 = new UserStub()->setId(11);

        $movedChild = $this->makeFutureEvent(2, '2030-01-09 19:00', '2030-01-09 22:00');
        $movedChild->addRsvp($attendee1);
        $movedChild->addRsvp($attendee2);
        $movedChild->setRsvpNotificationSentAt(new DateTimeImmutable('2030-01-01 08:00'));
        $movedChild->setEventReminderSentAt(new DateTimeImmutable('2030-01-01 09:00'));

        $movedItem = new RealignmentItem(
            eventId: 2,
            currentStart: new DateTimeImmutable('2030-01-09 19:00'),
            currentStop: new DateTimeImmutable('2030-01-09 22:00'),
            newStart: new DateTimeImmutable('2030-01-19 20:00'),
            newStop: new DateTimeImmutable('2030-01-19 23:30'),
            rsvpCount: 2,
            outcome: RealignmentOutcome::Moved,
        );
        $unchangedItem = new RealignmentItem(
            eventId: 3,
            currentStart: new DateTimeImmutable('2030-01-16 19:00'),
            currentStop: null,
            newStart: new DateTimeImmutable('2030-01-16 19:00'),
            newStop: null,
            rsvpCount: 1,
            outcome: RealignmentOutcome::DateUnchanged,
        );
        $plan = new RealignmentPlan(9, 1, EventInterval::Weekly, [$movedItem, $unchangedItem]);

        $repo = $this->createMock(EventRepository::class);
        $repo->expects($this->once())->method('find')->with(2)->willReturn($movedChild);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($movedChild);
        $em->expects($this->once())->method('flush');

        $service = $this->createService($repo, $em);

        // Act
        $result = $service->executeRealignment($plan);

        // Assert
        static::assertSame(1, $result->movedCount);
        static::assertSame('2030-01-19 20:00', $movedChild->getStart()->format('Y-m-d H:i'));
        static::assertSame('2030-01-19 23:30', $movedChild->getStop()->format('Y-m-d H:i'));
        static::assertCount(0, $movedChild->getRsvp());
        static::assertNull($movedChild->getRsvpNotificationSentAt());
        static::assertNull($movedChild->getEventReminderSentAt());
        static::assertSame($attendee1, $result->removedAttendees[10]['user']);
        static::assertSame('2030-01-09 19:00', $result->removedAttendees[10]['dates'][0]->format('Y-m-d H:i'));
        static::assertSame($attendee2, $result->removedAttendees[11]['user']);
    }

    // ---- extension template resolution ----

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
    private function createExtensionService(EventSeriesStub $series, ?EventStub $template): array
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

        return [$this->createService($repo, $em, $seriesRepo), static function () use (&$created): array {
            return $created;
        }];
    }

    public function testExtendRecurringEventsSourcesScheduleAndContentFromNewestMember(): void
    {
        // Arrange: the newest non-locked member carries the current time, content, and creator
        $series = $this->makeSeries(9, EventInterval::Weekly);

        $creator = new UserStub()->setId(77);
        $template = $this->makeFutureEvent(5, 'tomorrow 20:30', 'tomorrow 23:00');
        $template->setUser($creator);
        $template->addTranslation($this->makeTranslation('New Title'));

        [$service, $createdEvents] = $this->createExtensionService($series, $template);

        // Act
        $count = $service->extentRecurringEvents();

        // Assert
        $created = $createdEvents();
        static::assertGreaterThan(0, $count);
        static::assertCount($count, $created);
        $expectedFirst = new DateTime('tomorrow 20:30')->modify('+7 days');
        static::assertSame($expectedFirst->format('Y-m-d H:i'), $created[0]->getStart()->format('Y-m-d H:i'));
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
        // Arrange: a fresh series where the manually created first event is the only member
        $series = $this->makeSeries(9, EventInterval::Weekly);

        $template = $this->makeFutureEvent(1, 'yesterday 19:00', 'yesterday 22:00');
        $template->addTranslation($this->makeTranslation('Old Title'));

        [$service, $createdEvents] = $this->createExtensionService($series, $template);

        // Act
        $count = $service->extentRecurringEvents();

        // Assert
        $created = $createdEvents();
        static::assertGreaterThan(0, $count);
        $expectedFirst = new DateTime('yesterday 19:00')->modify('+7 days');
        static::assertSame($expectedFirst->format('Y-m-d H:i'), $created[0]->getStart()->format('Y-m-d H:i'));
        foreach ($created as $event) {
            static::assertSame('19:00', $event->getStart()->format('H:i'));
            static::assertSame('Old Title', $event->getTitle('en'));
        }
    }

    public function testExtendRecurringEventsSkipsSeriesWithoutUsableTemplate(): void
    {
        // Arrange: every member is locked - findNewestSeriesMember finds nothing
        $series = $this->makeSeries(9, EventInterval::Weekly);

        [$service, $createdEvents] = $this->createExtensionService($series, null);

        // Act
        $count = $service->extentRecurringEvents();

        // Assert
        static::assertSame(0, $count);
        static::assertCount(0, $createdEvents());
    }

    public function testUpdateRecurringEventsUpdatesExistingFollowUpTranslation(): void
    {
        // Arrange: anchor event with 'de' translation
        $anchor = $this->makeEvent(1);
        $anchor->setSeries($this->makeSeries(9, EventInterval::Monthly));

        $anchorTranslation = new EventTranslation();
        $anchorTranslation->setLanguage('de');
        $anchorTranslation->setTitle('Neuer Titel');
        $anchorTranslation->setTeaser('Neuer Teaser');
        $anchorTranslation->setDescription('Neue Beschreibung');
        $anchor->addTranslation($anchorTranslation);

        // Arrange: follow-up already has a 'de' translation with old data
        $child = $this->makeEvent(2);
        $existingTranslation = new EventTranslation();
        $existingTranslation->setLanguage('de');
        $existingTranslation->setTitle('Alter Titel');
        $existingTranslation->setTeaser('Alter Teaser');
        $existingTranslation->setDescription('Alte Beschreibung');
        $child->addTranslation($existingTranslation);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$child]);

        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createService($repo, $em);

        // Act
        $service->updateRecurringEvents($anchor);

        // Assert: existing follow-up translation is updated in-place
        $childTranslation = $child->findTranslation('de');
        static::assertNotNull($childTranslation);
        static::assertSame('Neuer Titel', $childTranslation->getTitle());
        static::assertSame('Neuer Teaser', $childTranslation->getTeaser());
        static::assertSame('Neue Beschreibung', $childTranslation->getDescription());
    }
}
