<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\EventFilterRsvp;
use App\Entity\EventFilterSort;
use App\Entity\EventFilterTime;
use App\Entity\EventIntervals;
use App\Entity\EventTypes;
use App\Repository\EventRepository;
use App\Service\EmailService;
use App\Service\Event\EventService;
use App\Service\Config\PluginService;
use App\Service\Event\RecurringEventService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Unit\Stubs\EventStub;
use Tests\Unit\Stubs\UserStub;

class EventServiceTest extends TestCase
{
    public function testStructureListGroupsEventsByYearAndMonth(): void
    {
        // Arrange: create events across different months
        $event1 = new EventStub()
            ->setId(1)
            ->setStart(new DateTimeImmutable('2002-01-01'));
        $event2 = new EventStub()
            ->setId(2)
            ->setStart(new DateTimeImmutable('2002-01-05'));
        $event3 = new EventStub()
            ->setId(3)
            ->setStart(new DateTimeImmutable('2002-01-07'));
        $event4 = new EventStub()
            ->setId(4)
            ->setStart(new DateTimeImmutable('2002-04-02'));
        $event5 = new EventStub()
            ->setId(5)
            ->setStart(new DateTimeImmutable('2002-04-04'));

        $eventList = [$event4, $event1, $event3, $event5, $event2];

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            emailService: $this->createStub(EmailService::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringEventService::class),
            plugins: [],
        );

        // Act: invoke private structureList method via reflection
        $method = new ReflectionClass($subject)->getMethod('structureList');

        $result = $method->invoke($subject, $eventList);

        // Assert: events are grouped by year-month with correct structure
        $this->assertArrayHasKey('2002-01', $result);
        $this->assertArrayHasKey('2002-04', $result);

        $this->assertSame('2002', $result['2002-01']['year']);
        $this->assertSame('January', $result['2002-01']['month']);
        $this->assertCount(3, $result['2002-01']['events']);

        $this->assertSame('2002', $result['2002-04']['year']);
        $this->assertSame('April', $result['2002-04']['month']);
        $this->assertCount(2, $result['2002-04']['events']);
    }

    #[DataProvider('filteredListDataProvider')]
    public function testGetFilteredListAppliesCorrectCriteria(
        EventFilterTime $time,
        EventFilterSort $sort,
        EventTypes $types,
        EventFilterRsvp $rsvp,
    ): void {
        // Arrange: mock repository to verify call and return empty array
        $repoMock = $this->createMock(EventRepository::class);
        $repoMock
            ->expects($this->once())
            ->method('findByFilters')
            ->with($time, $sort, $types, null, $rsvp)
            ->willReturn([]);

        $subject = new EventService(
            repo: $repoMock,
            em: $this->createStub(EntityManagerInterface::class),
            emailService: $this->createStub(EmailService::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringEventService::class),
            plugins: [],
        );

        // Act: get filtered list
        $result = $subject->getFilteredList($time, $sort, $types, $rsvp);

        // Assert: returns structured (empty) list
        $this->assertSame([], $result);
    }

    public static function filteredListDataProvider(): Generator
    {
        yield 'all events sorted newest first' => [
            'time' => EventFilterTime::All,
            'sort' => EventFilterSort::NewToOld,
            'types' => EventTypes::All,
            'rsvp' => EventFilterRsvp::All,
        ];
    }

    public function testCancelEventSetsCanceledFlagAndSendsNotifications(): void
    {
        // Arrange: create future event with RSVP users
        $user1 = new UserStub()->setId(1);
        $user2 = new UserStub()->setId(2);

        $event = new EventStub()
            ->setId(1)
            ->setStart(new DateTime('+1 week'));
        $event->addRsvp($user1);
        $event->addRsvp($user2);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($event);
        $emMock->expects($this->once())->method('flush');

        $emailServiceMock = $this->createMock(EmailService::class);
        $emailServiceMock->expects($this->exactly(2))->method('prepareEventCanceledNotification')->willReturn(true);
        $emailServiceMock->expects($this->once())->method('sendQueue');

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $emMock,
            emailService: $emailServiceMock,
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringEventService::class),
            plugins: [],
        );

        // Act: cancel the event
        $subject->cancelEvent($event);

        // Assert: event is marked as canceled
        $this->assertTrue($event->isCanceled());
    }

    public function testCancelEventWithNoRsvpsDoesNotSendEmails(): void
    {
        // Arrange: create future event without RSVPs
        $event = new EventStub()
            ->setId(1)
            ->setStart(new DateTime('+1 week'));

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($event);
        $emMock->expects($this->once())->method('flush');

        $emailServiceMock = $this->createMock(EmailService::class);
        $emailServiceMock->expects($this->never())->method('prepareEventCanceledNotification');
        $emailServiceMock->expects($this->once())->method('sendQueue');

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $emMock,
            emailService: $emailServiceMock,
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringEventService::class),
            plugins: [],
        );

        // Act: cancel the event
        $subject->cancelEvent($event);

        // Assert: event is marked as canceled
        $this->assertTrue($event->isCanceled());
    }

    public function testCancelPastEventDoesNotSendNotifications(): void
    {
        // Arrange: create past event with RSVP users
        $user = new UserStub()->setId(1);
        $event = new EventStub()
            ->setId(1)
            ->setStart(new DateTime('-1 week'));
        $event->addRsvp($user);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($event);
        $emMock->expects($this->once())->method('flush');

        $emailServiceMock = $this->createMock(EmailService::class);
        $emailServiceMock->expects($this->never())->method('prepareEventCanceledNotification');
        $emailServiceMock->expects($this->never())->method('sendQueue');

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $emMock,
            emailService: $emailServiceMock,
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringEventService::class),
            plugins: [],
        );

        // Act: cancel the past event
        $subject->cancelEvent($event);

        // Assert: event is marked as canceled but no notifications sent
        $this->assertTrue($event->isCanceled());
    }

    public function testUncancelEventRemovesCanceledFlag(): void
    {
        // Arrange: create canceled event
        $event = new EventStub()->setId(1);
        $event->setCanceled(true);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($event);
        $emMock->expects($this->once())->method('flush');

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $emMock,
            emailService: $this->createStub(EmailService::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringEventService::class),
            plugins: [],
        );

        // Act: uncancel the event
        $subject->uncancelEvent($event);

        // Assert: event is no longer canceled
        $this->assertFalse($event->isCanceled());
    }

    public function testUpdateRecurringEventsWithNoRecurringReturnZero(): void
    {
        // Arrange: create event without recurring rule
        $event = new EventStub()->setId(1);

        $recurringServiceMock = $this->createMock(RecurringEventService::class);
        $recurringServiceMock->expects($this->once())->method('updateRecurringEvents')->with($event)->willReturn(0);

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            emailService: $this->createStub(EmailService::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $recurringServiceMock,
            plugins: [],
        );

        // Act & Assert
        $this->assertSame(0, $subject->updateRecurringEvents($event));
    }

    public function testUpdateRecurringEventsWithParentReturnsCount(): void
    {
        // Arrange: create parent event with recurring rule
        $parent = new EventStub()
            ->setId(1)
            ->setRecurringRule(EventIntervals::Weekly);
        $parent->setStart(new DateTime('2025-01-01 10:00:00'));

        $recurringServiceMock = $this->createMock(RecurringEventService::class);
        $recurringServiceMock->expects($this->once())->method('updateRecurringEvents')->with($parent)->willReturn(1);

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            emailService: $this->createStub(EmailService::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $recurringServiceMock,
            plugins: [],
        );

        // Act & Assert
        $this->assertSame(1, $subject->updateRecurringEvents($parent));
    }

    public function testCancelEventSendsEmailsAndFlushes(): void
    {
        // Arrange: create future event with RSVP
        $user = new UserStub()
            ->setId(1)
            ->setEmail('test@example.com');
        $event = new EventStub()
            ->setId(1)
            ->setStart(new DateTime('+1 week'));
        $event->addRsvp($user);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($event);
        $emMock->expects($this->once())->method('flush');

        $emailMock = $this->createMock(EmailService::class);
        $emailMock->expects($this->once())->method('prepareEventCanceledNotification')->with($user, $event);
        $emailMock->expects($this->once())->method('sendQueue');

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $emMock,
            emailService: $emailMock,
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $this->createStub(RecurringEventService::class),
            plugins: [],
        );

        // Act & Assert
        $subject->cancelEvent($event);
        $this->assertTrue($event->isCanceled());
    }

    public function testExtentRecurringEventsCallsRecurringService(): void
    {
        // Arrange: create mock that verifies call to recurring service
        $recurringServiceMock = $this->createMock(RecurringEventService::class);
        $recurringServiceMock->expects($this->once())->method('extentRecurringEvents');

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            emailService: $this->createStub(EmailService::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $recurringServiceMock,
            plugins: [],
        );

        // Act
        $subject->extentRecurringEvents();

        // Assert: verified through mock expectations
    }

    public function testUpdateRecurringEventsWithChildUpdatesParent(): void
    {
        // Arrange: create child event with parent reference
        $child = new EventStub()
            ->setId(2)
            ->setRecurringOf(1)
            ->setStart(new DateTime('+1 week'));

        $recurringServiceMock = $this->createMock(RecurringEventService::class);
        $recurringServiceMock->expects($this->once())->method('updateRecurringEvents')->with($child)->willReturn(1);

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            emailService: $this->createStub(EmailService::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $recurringServiceMock,
            plugins: [],
        );

        // Act & Assert
        $this->assertSame(1, $subject->updateRecurringEvents($child));
    }

    public function testUpdateRecurringEventsWithDeletedParentReturnsZero(): void
    {
        // Arrange: create child event with deleted parent reference
        $child = new EventStub()
            ->setId(2)
            ->setRecurringOf(1)
            ->setStart(new DateTime('+1 week'));

        $recurringServiceMock = $this->createMock(RecurringEventService::class);
        $recurringServiceMock->expects($this->once())->method('updateRecurringEvents')->with($child)->willReturn(0);

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            emailService: $this->createStub(EmailService::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $recurringServiceMock,
            plugins: [],
        );

        // Act & Assert
        $this->assertSame(0, $subject->updateRecurringEvents($child));
    }

    public function testExtentRecurringEventsWithBiMonthly(): void
    {
        // Arrange: create mock that verifies call to recurring service
        $recurringServiceMock = $this->createMock(RecurringEventService::class);
        $recurringServiceMock->expects($this->once())->method('extentRecurringEvents');

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            emailService: $this->createStub(EmailService::class),
            pluginService: $this->createStub(PluginService::class),
            recurringEventService: $recurringServiceMock,
            plugins: [],
        );

        // Act
        $subject->extentRecurringEvents();

        // Assert: verified through mock expectations
    }
}
