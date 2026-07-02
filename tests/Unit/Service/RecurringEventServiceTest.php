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
use Tests\Unit\Stubs\EventStub;
use Tests\Unit\Stubs\UserStub;

class RecurringEventServiceTest extends TestCase
{
    // ---- helpers ----

    private function makeEvent(int $id, EventStatus $status = EventStatus::Published): EventStub
    {
        $event = new EventStub();
        $event->setId($id);
        $event->setStart(new DateTime('2025-06-01 19:00'));
        $event->setStatus($status);

        return $event;
    }

    private function createService(EventRepository $repo, EntityManagerInterface $em): RecurringEventService
    {
        return new RecurringEventService(
            repo: $repo,
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
        $repo = $this->createStub(EventRepository::class);
        $repo->method('findAllRecurring')->willReturn([]);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        // Act
        $result = $service->runCronTask($this->createStub(OutputInterface::class));

        // Assert
        static::assertSame('recurring-events', $result->identifier);
        static::assertSame(CronTaskStatus::ok, $result->status);
        static::assertSame('0 events extended', $result->message);
    }

    // ---- updateRecurringEvents: early-return cases (data provider) ----

    #[DataProvider('updateRecurringEventsReturnsZeroProvider')]
    public function testUpdateRecurringEventsReturnsZero(EventStub $event, ?EventStub $parentFromRepo): void
    {
        // Arrange
        $repo = $this->createStub(EventRepository::class);
        $repo->method('findOneBy')->willReturn($parentFromRepo);
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
        $noParentNoRule = new EventStub();
        $noParentNoRule->setId(1);
        $noParentNoRule->setStart(new DateTime('2025-06-01 19:00'));
        yield 'no recurring rule and no recurringOf → returns 0' => [
            'event' => $noParentNoRule,
            'parentFromRepo' => null,
        ];

        $parentZeroChildren = new EventStub();
        $parentZeroChildren->setId(2);
        $parentZeroChildren->setStart(new DateTime('2025-06-01 19:00'));
        $parentZeroChildren->setRecurringRule(EventInterval::Weekly);
        yield 'parent with zero children → returns 0' => [
            'event' => $parentZeroChildren,
            'parentFromRepo' => null,
        ];

        $child = new EventStub();
        $child->setId(10);
        $child->setStart(new DateTime('2025-06-01 19:00'));
        $child->setRecurringOf(99);
        yield 'child with parent not found in DB → returns 0' => [
            'event' => $child,
            'parentFromRepo' => null,
        ];
    }

    // ---- updateRecurringEvents: parent with unlocked children → counts and persists ----

    public function testUpdateRecurringEventsParentWithTwoUnlockedChildrenReturnsTwo(): void
    {
        // Arrange
        $parent = $this->makeEvent(1);
        $parent->setRecurringRule(EventInterval::Weekly);

        $child1 = $this->makeEvent(2);
        $child2 = $this->makeEvent(3);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$child1, $child2]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->once())->method('flush');

        $service = $this->createService($repo, $em);

        // Act
        $result = $service->updateRecurringEvents($parent);

        // Assert
        static::assertSame(2, $result);
    }

    public function testUpdateRecurringEventsLockedChildIsSkipped(): void
    {
        // Arrange
        $parent = $this->makeEvent(1);
        $parent->setRecurringRule(EventInterval::Monthly);

        $lockedChild = $this->makeEvent(2, EventStatus::Locked);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$lockedChild]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = $this->createService($repo, $em);

        // Act
        $result = $service->updateRecurringEvents($parent);

        // Assert
        static::assertSame(0, $result);
    }

    public function testUpdateRecurringEventsMixedChildrenSkipsLockedReturnsTwoUpdated(): void
    {
        // Arrange
        $parent = $this->makeEvent(1);
        $parent->setRecurringRule(EventInterval::Weekly);

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
        $result = $service->updateRecurringEvents($parent);

        // Assert
        static::assertSame(2, $result);
    }

    // ---- updateRecurringEvents: event is a child, parent found ----

    public function testUpdateRecurringEventsChildEventUsesParentForFollowUpLookup(): void
    {
        // Arrange
        $parentEvent = $this->makeEvent(1);
        $parentEvent->setRecurringRule(EventInterval::Monthly);

        $childEvent = $this->makeEvent(10);
        $childEvent->setRecurringOf(1);

        $followUp1 = $this->makeEvent(11);
        $followUp2 = $this->makeEvent(12);

        $repo = $this->createMock(EventRepository::class);
        $repo->expects($this->once())->method('findOneBy')->with(['id' => 1])->willReturn($parentEvent);
        $repo->expects($this->once())->method('findFollowUpEvents')->with(1, $childEvent->getStart())->willReturn([$followUp1, $followUp2]);

        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createService($repo, $em);

        // Act
        $result = $service->updateRecurringEvents($childEvent);

        // Assert
        static::assertSame(2, $result);
    }

    // ---- updateRecurringEvents: translation propagation ----

    public function testUpdateRecurringEventsPropagatesTranslationsToChildren(): void
    {
        // Arrange: parent event with one translation
        $parent = $this->makeEvent(1);
        $parent->setRecurringRule(EventInterval::Weekly);

        $translation = new EventTranslation();
        $translation->setLanguage('en');
        $translation->setTitle('Parent Title');
        $translation->setTeaser('Parent Teaser');
        $translation->setDescription('Parent Description');
        $parent->addTranslation($translation);

        // Arrange: child with no existing translation for 'en'
        $child = $this->makeEvent(2);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$child]);

        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createService($repo, $em);

        // Act
        $service->updateRecurringEvents($parent);

        // Assert: child now has the 'en' translation with propagated fields
        $childTranslation = $child->findTranslation('en');
        static::assertNotNull($childTranslation);
        static::assertSame('Parent Title', $childTranslation->getTitle());
        static::assertSame('Parent Teaser', $childTranslation->getTeaser());
        static::assertSame('Parent Description', $childTranslation->getDescription());
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
        $parent = $this->makeFutureEvent(1, '2030-01-02 19:00', '2030-01-02 22:00');
        $parent->setRecurringRule(EventInterval::Weekly);

        $child1 = $this->makeFutureEvent(2, '2030-01-09 19:00', '2030-01-09 22:00');
        $child2 = $this->makeFutureEvent(3, '2030-01-16 19:00', '2030-01-16 22:00');

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$child1, $child2]);
        $repo->method('getRsvpCounts')->willReturn([2 => 3, 3 => 0]);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        // Act
        $plan = $service->planRealignment($parent, $this->makeWeeklyChange());

        // Assert
        static::assertSame(1, $plan->parentEventId);
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
        $parent = $this->makeFutureEvent(1, '2030-01-02 19:00');
        $parent->setRecurringRule(EventInterval::Weekly);

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
        $plan = $service->planRealignment($parent, $change);

        // Assert: nothing dropped, monthly occurrences continue past the old weekly spacing
        static::assertCount(2, $plan->items);
        static::assertSame('2030-02-12 20:00', $plan->items[0]->newStart->format('Y-m-d H:i'));
        static::assertNull($plan->items[0]->newStop);
        static::assertSame('2030-03-12 20:00', $plan->items[1]->newStart->format('Y-m-d H:i'));
    }

    public function testPlanRealignmentLockedChildKeepsDateAndDoesNotConsumeOccurrenceSlot(): void
    {
        // Arrange
        $parent = $this->makeFutureEvent(1, '2030-01-02 19:00', '2030-01-02 22:00');
        $parent->setRecurringRule(EventInterval::Weekly);

        $lockedChild = $this->makeFutureEvent(2, '2030-01-09 19:00', '2030-01-09 22:00', EventStatus::Locked);
        $realignable = $this->makeFutureEvent(3, '2030-01-16 19:00', '2030-01-16 22:00');

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$lockedChild, $realignable]);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        // Act
        $plan = $service->planRealignment($parent, $this->makeWeeklyChange());

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
        $parent = $this->makeFutureEvent(1, '2030-01-02 19:00');
        $parent->setRecurringRule(EventInterval::Weekly);

        $canceledChild = $this->makeFutureEvent(2, '2030-01-09 19:00');
        $canceledChild->setCanceled(true);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$canceledChild]);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        // Act
        $plan = $service->planRealignment($parent, $this->makeWeeklyChange());

        // Assert
        static::assertSame(RealignmentOutcome::SkippedCanceled, $plan->items[0]->outcome);
        static::assertNull($plan->items[0]->newStart);
        static::assertSame(0, $plan->movedCount());
    }

    public function testPlanRealignmentChildAlreadyOnPatternIsDateUnchanged(): void
    {
        // Arrange: anchor keeps its start, one child sits on the pattern, one is off by a day
        $parent = $this->makeFutureEvent(1, '2030-01-05 19:00');
        $parent->setRecurringRule(EventInterval::Weekly);

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
        $plan = $service->planRealignment($parent, $change);

        // Assert
        static::assertSame(RealignmentOutcome::DateUnchanged, $plan->items[0]->outcome);
        static::assertSame(RealignmentOutcome::Moved, $plan->items[1]->outcome);
        static::assertSame('2030-01-19 19:00', $plan->items[1]->newStart->format('Y-m-d H:i'));
    }

    public function testPlanRealignmentCollectsChildrenFromOldStart(): void
    {
        // Arrange: anchor moves later; a child between old and new start must stay included
        $parent = $this->makeFutureEvent(1, '2030-01-02 19:00');
        $parent->setRecurringRule(EventInterval::Weekly);

        $betweenChild = $this->makeFutureEvent(2, '2030-01-09 19:00');

        $repo = $this->createMock(EventRepository::class);
        $repo
            ->expects($this->once())
            ->method('findFollowUpEvents')
            ->with(1, self::callback(static fn(DateTimeImmutable $bound): bool => $bound->format('Y-m-d H:i') === '2030-01-02 19:00'))
            ->willReturn([$betweenChild]);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        // Act
        $plan = $service->planRealignment($parent, $this->makeWeeklyChange());

        // Assert
        static::assertCount(1, $plan->items);
        static::assertSame(2, $plan->items[0]->eventId);
    }

    public function testPlanRealignmentExcludesPastChildren(): void
    {
        // Arrange
        $parent = $this->makeFutureEvent(1, '2020-01-02 19:00');
        $parent->setRecurringRule(EventInterval::Weekly);

        $pastChild = $this->makeFutureEvent(2, '2020-01-09 19:00');
        $futureChild = $this->makeFutureEvent(3, '2030-01-16 19:00');

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findFollowUpEvents')->willReturn([$pastChild, $futureChild]);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        // Act
        $plan = $service->planRealignment($parent, $this->makeWeeklyChange());

        // Assert
        static::assertCount(1, $plan->items);
        static::assertSame(3, $plan->items[0]->eventId);
    }

    public function testPlanRealignmentWithoutRuleReturnsEmptyPlan(): void
    {
        // Arrange: child anchor whose parent no longer carries a rule
        $parent = $this->makeFutureEvent(1, '2030-01-02 19:00');
        $child = $this->makeFutureEvent(2, '2030-01-09 19:00');
        $child->setRecurringOf(1);

        $repo = $this->createStub(EventRepository::class);
        $repo->method('findOneBy')->willReturn($parent);
        $service = $this->createService($repo, $this->createStub(EntityManagerInterface::class));

        $change = new ScheduleChange(
            oldStart: new DateTimeImmutable('2030-01-09 19:00'),
            oldStop: null,
            oldRule: null,
            newStart: new DateTimeImmutable('2030-01-10 19:00'),
            newStop: null,
            newRule: null,
        );

        // Act
        $plan = $service->planRealignment($child, $change);

        // Assert
        static::assertTrue($plan->isEmpty());
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
        $plan = new RealignmentPlan(1, 1, EventInterval::Weekly, [$movedItem, $unchangedItem]);

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
    private function createExtensionService(EventStub $parent, ?EventStub $template): array
    {
        $repo = $this->createStub(EventRepository::class);
        $repo->method('findAllRecurring')->willReturn([$parent]);
        $repo->method('find')->willReturn($parent);
        $repo->method('findNewestAutoChild')->willReturn($template);

        $created = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$created): void {
            if ($entity instanceof Event && $entity->getId() === null) {
                new ReflectionProperty(Event::class, 'id')->setValue($entity, 1000 + count($created));
                $created[] = $entity;
            }
        });

        return [$this->createService($repo, $em), static function () use (&$created): array {
            return $created;
        }];
    }

    public function testExtendRecurringEventsSourcesScheduleAndContentFromNewestChild(): void
    {
        // Arrange: parent is stale, the newest auto child carries the current time and content
        $parent = $this->makeFutureEvent(1, 'yesterday 19:00', 'yesterday 22:00');
        $parent->setRecurringRule(EventInterval::Weekly);
        $parent->addTranslation($this->makeTranslation('Old Title'));

        $template = $this->makeFutureEvent(5, 'tomorrow 20:30', 'tomorrow 23:00');
        $template->addTranslation($this->makeTranslation('New Title'));

        [$service, $createdEvents] = $this->createExtensionService($parent, $template);

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
            static::assertSame(1, $event->getRecurringOf());
            static::assertNull($event->getRecurringRule());
        }
    }

    public function testExtendRecurringEventsFallsBackToParentWhenNoAutoChildExists(): void
    {
        // Arrange
        $parent = $this->makeFutureEvent(1, 'yesterday 19:00', 'yesterday 22:00');
        $parent->setRecurringRule(EventInterval::Weekly);
        $parent->addTranslation($this->makeTranslation('Old Title'));

        [$service, $createdEvents] = $this->createExtensionService($parent, null);

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

    public function testUpdateRecurringEventsUpdatesExistingChildTranslation(): void
    {
        // Arrange: parent event with 'de' translation
        $parent = $this->makeEvent(1);
        $parent->setRecurringRule(EventInterval::Monthly);

        $parentTranslation = new EventTranslation();
        $parentTranslation->setLanguage('de');
        $parentTranslation->setTitle('Neuer Titel');
        $parentTranslation->setTeaser('Neuer Teaser');
        $parentTranslation->setDescription('Neue Beschreibung');
        $parent->addTranslation($parentTranslation);

        // Arrange: child already has a 'de' translation with old data
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
        $service->updateRecurringEvents($parent);

        // Assert: existing child translation is updated in-place
        $childTranslation = $child->findTranslation('de');
        static::assertNotNull($childTranslation);
        static::assertSame('Neuer Titel', $childTranslation->getTitle());
        static::assertSame('Neuer Teaser', $childTranslation->getTeaser());
        static::assertSame('Neue Beschreibung', $childTranslation->getDescription());
    }
}
