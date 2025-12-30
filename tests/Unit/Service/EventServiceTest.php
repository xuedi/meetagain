<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\EventFilterRsvp;
use App\Entity\EventFilterSort;
use App\Entity\EventFilterTime;
use App\Entity\EventIntervals;
use App\Entity\EventTypes;
use App\Repository\EventRepository;
use App\Service\EmailService;
use App\Service\EventService;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\LazyCriteriaCollection;
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
        $event1 = (new EventStub())->setId(1)->setStart(new DateTimeImmutable('2002-01-01'));
        $event2 = (new EventStub())->setId(2)->setStart(new DateTimeImmutable('2002-01-05'));
        $event3 = (new EventStub())->setId(3)->setStart(new DateTimeImmutable('2002-01-07'));
        $event4 = (new EventStub())->setId(4)->setStart(new DateTimeImmutable('2002-04-02'));
        $event5 = (new EventStub())->setId(5)->setStart(new DateTimeImmutable('2002-04-04'));

        $eventList = [$event4, $event1, $event3, $event5, $event2];

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            emailService: $this->createStub(EmailService::class),
        );

        // Act: invoke private structureList method via reflection
        $method = (new ReflectionClass($subject))->getMethod('structureList');

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

    #[DataProvider('lastRecurringEventDataProvider')]
    public function testGetLastRecurringEventDateReturnsCorrectDate(
        ?string $dbResultDate,
        string $eventStartDate,
        string $expectedDate,
    ): void {
        // Arrange: mock repository to return last recurring event or null
        $returnValue = $dbResultDate !== null
            ? (new EventStub())->setStart(new DateTimeImmutable($dbResultDate))
            : null;

        $repoStub = $this->createStub(EventRepository::class);
        $repoStub->method('findOneBy')->willReturn($returnValue);

        $event = (new EventStub())->setStart(new DateTimeImmutable($eventStartDate));

        $subject = new EventService(
            repo: $repoStub,
            em: $this->createStub(EntityManagerInterface::class),
            emailService: $this->createStub(EmailService::class),
        );

        // Act: invoke private getLastRecurringEventDate method via reflection
        $method = (new ReflectionClass($subject))->getMethod('getLastRecurringEventDate');

        $result = $method->invoke($subject, $event);

        // Assert: returns correct date string
        $this->assertSame($expectedDate, $result);
    }

    public static function lastRecurringEventDataProvider(): Generator
    {
        yield 'returns last recurring event date when exists' => [
            'dbResultDate' => '2017-04-26',
            'eventStartDate' => '2002-01-01',
            'expectedDate' => '2017-04-26',
        ];
        yield 'returns event start date when no recurring events exist' => [
            'dbResultDate' => null,
            'eventStartDate' => '2002-01-01',
            'expectedDate' => '2002-01-01',
        ];
    }

    public function testUpdateDatePreservesTimeButChangesDate(): void
    {
        // Arrange: create target datetime with specific time and occurrence with different date
        $targetDateTime = new DateTime('2010-10-10 10:10:10');
        $occurrenceDateTime = new DateTime('2002-02-02 20:20:20');

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            emailService: $this->createStub(EmailService::class),
        );

        // Act: invoke private updateDate method via reflection
        $method = (new ReflectionClass($subject))->getMethod('updateDate');

        $result = $method->invoke($subject, $targetDateTime, $occurrenceDateTime);

        // Assert: date is from occurrence, time is from target
        $this->assertSame('2002-02-02 10:10:10', $result->format('Y-m-d H:i:s'));
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
        // Arrange: create event with RSVP users
        $user1 = (new UserStub())->setId(1);
        $user2 = (new UserStub())->setId(2);

        $event = (new EventStub())->setId(1);
        $event->addRsvp($user1);
        $event->addRsvp($user2);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($event);
        $emMock->expects($this->once())->method('flush');

        $emailServiceMock = $this->createMock(EmailService::class);
        $emailServiceMock->expects($this->exactly(2))
            ->method('prepareEventCanceledNotification')
            ->willReturn(true);
        $emailServiceMock->expects($this->once())->method('sendQueue');

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $emMock,
            emailService: $emailServiceMock,
        );

        // Act: cancel the event
        $subject->cancelEvent($event);

        // Assert: event is marked as canceled
        $this->assertTrue($event->isCanceled());
    }

    public function testCancelEventWithNoRsvpsDoesNotSendEmails(): void
    {
        // Arrange: create event without RSVPs
        $event = (new EventStub())->setId(1);

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
        );

        // Act: cancel the event
        $subject->cancelEvent($event);

        // Assert: event is marked as canceled
        $this->assertTrue($event->isCanceled());
    }

    public function testUncancelEventRemovesCanceledFlag(): void
    {
        // Arrange: create canceled event
        $event = (new EventStub())->setId(1);
        $event->setCanceled(true);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($event);
        $emMock->expects($this->once())->method('flush');

        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $emMock,
            emailService: $this->createStub(EmailService::class),
        );

        // Act: uncancel the event
        $subject->uncancelEvent($event);

        // Assert: event is no longer canceled
        $this->assertFalse($event->isCanceled());
    }

    public function testUpdateRecurringEventsWithNoRecurringReturnZero(): void
    {
        $event = (new EventStub())->setId(1);
        $subject = new EventService(
            repo: $this->createStub(EventRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            emailService: $this->createStub(EmailService::class),
        );

        $this->assertSame(0, $subject->updateRecurringEvents($event));
    }

    public function testUpdateRecurringEventsWithParentReturnsCount(): void
    {
        $parent = (new EventStub())->setId(1)->setRecurringRule(EventIntervals::Weekly);
        $parent->setStart(new DateTime('2025-01-01 10:00:00'));
        $child = (new EventStub())->setId(2)->setRecurringOf(1)->setStart(new DateTime('+1 week'));
        
        $repoMock = $this->createStub(EventRepository::class);
        $repoMock->method('findFollowUpEvents')->willReturn([$child]);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())->method('persist');
        $emMock->expects($this->once())->method('flush');

        $subject = new EventService(
            repo: $repoMock,
            em: $emMock,
            emailService: $this->createStub(EmailService::class),
        );

        $this->assertSame(1, $subject->updateRecurringEvents($parent));
    }

    public function testCancelEventSendsEmailsAndFlushes(): void
    {
        $user = (new UserStub())->setId(1)->setEmail('test@example.com');
        $event = (new EventStub())->setId(1);
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
        );

        $subject->cancelEvent($event);

        $this->assertTrue($event->isCanceled());
    }

    public function testExtentRecurringEventsCallsFillForEachEvent(): void
    {
        $event = (new EventStub())->setId(1)->setRecurringRule(EventIntervals::Weekly);
        $event->setStart(new DateTime('2025-01-01 10:00:00'));
        $event->setStop(new DateTime('2025-01-01 12:00:00'));
        $event->setPublished(true);

        $repoMock = $this->createStub(EventRepository::class);
        $repoMock->method('findAllRecurring')->willReturn([$event]);
        $repoMock->method('findOneBy')->willReturn(null);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())->method('persist');
        $emMock->expects($this->atLeastOnce())->method('flush');

        $subject = new EventService(
            repo: $repoMock,
            em: $emMock,
            emailService: $this->createStub(EmailService::class),
        );

        $subject->extentRecurringEvents();
    }
    public function testUpdateRecurringEventsWithChildUpdatesParent(): void
    {
        $parent = (new EventStub())->setId(1)->setRecurringRule(EventIntervals::Weekly);
        $parent->setStart(new DateTime('2025-01-01 10:00:00'));
        
        $child = (new EventStub())->setId(2)->setRecurringOf(1)->setStart(new DateTime('+1 week'));
        
        $repoMock = $this->createStub(EventRepository::class);
        $repoMock->method('findOneBy')->with(['id' => 1])->willReturn($parent);
        $repoMock->method('findFollowUpEvents')->willReturn([$child]);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())->method('persist');
        $emMock->expects($this->once())->method('flush');

        $subject = new EventService(
            repo: $repoMock,
            em: $emMock,
            emailService: $this->createStub(EmailService::class),
        );

        $this->assertSame(1, $subject->updateRecurringEvents($child));
    }

    public function testUpdateRecurringEventsWithDeletedParentReturnsZero(): void
    {
        $child = (new EventStub())->setId(2)->setRecurringOf(1)->setStart(new DateTime('+1 week'));
        
        $repoMock = $this->createStub(EventRepository::class);
        $repoMock->method('findOneBy')->with(['id' => 1])->willReturn(null);

        $subject = new EventService(
            repo: $repoMock,
            em: $this->createStub(EntityManagerInterface::class),
            emailService: $this->createStub(EmailService::class),
        );

        $this->assertSame(0, $subject->updateRecurringEvents($child));
    }

    public function testExtentRecurringEventsWithBiMonthly(): void
    {
        $event = (new EventStub())->setId(1)->setRecurringRule(EventIntervals::BiMonthly);
        $event->setStart(new DateTime('2025-01-01 10:00:00'));
        $event->setStop(new DateTime('2025-01-01 12:00:00'));
        $event->setPublished(true);
        
        $repoMock = $this->createStub(EventRepository::class);
        $repoMock->method('findAllRecurring')->willReturn([$event]);
        $repoMock->method('findOneBy')->willReturn(null);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->atLeastOnce())->method('persist');
        $emMock->expects($this->atLeastOnce())->method('flush');

        $subject = new EventService(
            repo: $repoMock,
            em: $emMock,
            emailService: $this->createStub(EmailService::class),
        );

        $subject->extentRecurringEvents();
    }
}
