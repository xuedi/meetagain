<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\NotificationSettings;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Service\Config\ConfigService;
use App\Service\Email\EmailService;
use App\Service\Event\RsvpNotificationService;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Output\BufferedOutput;

class RsvpNotificationServiceTest extends TestCase
{
    private EventRepository&Stub $eventRepo;
    private EmailService&MockObject $emailService;
    private ConfigService&Stub $configService;
    private EntityManagerInterface&Stub $entityManager;
    private ClockInterface&Stub $clock;
    private RsvpNotificationService $service;

    protected function setUp(): void
    {
        $this->eventRepo = $this->createStub(EventRepository::class);
        $this->emailService = $this->createMock(EmailService::class);
        $this->configService = $this->createStub(ConfigService::class);
        $this->configService->method('isSendRsvpNotifications')->willReturn(true);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->clock = $this->createStub(ClockInterface::class);
        $this->clock->method('now')->willReturn(new DateTimeImmutable('2026-03-13 10:00:00'));
        $this->service = new RsvpNotificationService(
            $this->eventRepo,
            $this->emailService,
            $this->configService,
            $this->createStub(LoggerInterface::class),
            $this->entityManager,
            $this->clock,
        );
    }

    public function testNotifyFollowersForEventSendsEmail(): void
    {
        $event = $this->createStub(Event::class);
        $event->method('getId')->willReturn(1);

        $attendee = $this->createStub(User::class);
        $attendee->method('getId')->willReturn(10);
        $attendee->method('getName')->willReturn('Attendee');

        $follower = $this->createStub(User::class);
        $follower->method('getId')->willReturn(20);
        $follower->method('isNotification')->willReturn(true);
        $settings = new NotificationSettings(['followingUpdates' => true]);
        $follower->method('getNotificationSettings')->willReturn($settings);

        $event->method('getRsvp')->willReturn(new ArrayCollection([$attendee]));
        $attendee->method('getFollowers')->willReturn(new ArrayCollection([$follower]));
        $event->method('hasRsvp')->willReturn(false);

        $this->emailService
            ->expects($this->once())
            ->method('prepareAggregatedRsvpNotification')
            ->with($follower, [$attendee], $event);

        $count = $this->service->notifyFollowersForEvent($event);
        static::assertSame(1, $count);
    }

    public function testNotifyFollowersForEventSendsEmailAggregated(): void
    {
        $event = $this->createStub(Event::class);
        $event->method('getId')->willReturn(1);

        $attendee1 = $this->createStub(User::class);
        $attendee1->method('getId')->willReturn(10);
        $attendee1->method('getName')->willReturn('Attendee 1');

        $attendee2 = $this->createStub(User::class);
        $attendee2->method('getId')->willReturn(11);
        $attendee2->method('getName')->willReturn('Attendee 2');

        $follower = $this->createStub(User::class);
        $follower->method('getId')->willReturn(20);
        $follower->method('isNotification')->willReturn(true);
        $settings = new NotificationSettings(['followingUpdates' => true]);
        $follower->method('getNotificationSettings')->willReturn($settings);

        $event->method('getRsvp')->willReturn(new ArrayCollection([$attendee1, $attendee2]));
        $attendee1->method('getFollowers')->willReturn(new ArrayCollection([$follower]));
        $attendee2->method('getFollowers')->willReturn(new ArrayCollection([$follower]));
        $event->method('hasRsvp')->willReturn(false);

        $this->emailService
            ->expects($this->once())
            ->method('prepareAggregatedRsvpNotification')
            ->with($follower, [$attendee1, $attendee2], $event);

        $count = $this->service->notifyFollowersForEvent($event);
        static::assertSame(1, $count);
    }

    public function testNotifyFollowersForEventSkipsIfNotificationsDisabled(): void
    {
        $event = $this->createStub(Event::class);
        $attendee = $this->createStub(User::class);
        $follower = $this->createStub(User::class);

        $follower->method('isNotification')->willReturn(false);

        $event->method('getRsvp')->willReturn(new ArrayCollection([$attendee]));
        $attendee->method('getFollowers')->willReturn(new ArrayCollection([$follower]));

        $this->emailService->expects($this->never())->method('prepareAggregatedRsvpNotification');

        $count = $this->service->notifyFollowersForEvent($event);
        static::assertSame(0, $count);
    }

    public function testProcessUpcomingEvents(): void
    {
        $event1 = $this->createStub(Event::class);
        $event1->method('getRsvp')->willReturn(new ArrayCollection([]));
        $event2 = $this->createStub(Event::class);
        $event2->method('getRsvp')->willReturn(new ArrayCollection([]));

        $this->eventRepo->method('findUpcomingEventsNeedingRsvpNotification')->willReturn([$event1, $event2]);

        $this->emailService->expects($this->never())->method('prepareAggregatedRsvpNotification');

        $result = $this->service->processUpcomingEvents();
        static::assertSame('0 sent', $result);
    }

    public function testNotifyFollowersForEventEmptyAttendees(): void
    {
        $event = $this->createStub(Event::class);
        $event->method('getRsvp')->willReturn(new ArrayCollection([]));

        $this->emailService->expects($this->never())->method('prepareAggregatedRsvpNotification');

        $count = $this->service->notifyFollowersForEvent($event);
        static::assertSame(0, $count);
    }

    public function testNotifyFollowersForEventSkipsIfRecipientHasRsvp(): void
    {
        $event = $this->createStub(Event::class);
        $event->method('getId')->willReturn(1);

        $attendee = $this->createStub(User::class);
        $attendee->method('getId')->willReturn(10);

        $follower = $this->createStub(User::class);
        $follower->method('getId')->willReturn(20);
        $follower->method('isNotification')->willReturn(true);
        $settings = new NotificationSettings(['followingUpdates' => true]);
        $follower->method('getNotificationSettings')->willReturn($settings);

        $event->method('getRsvp')->willReturn(new ArrayCollection([$attendee]));
        $attendee->method('getFollowers')->willReturn(new ArrayCollection([$follower]));
        $event->method('hasRsvp')->willReturn(true);

        $this->emailService->expects($this->never())->method('prepareAggregatedRsvpNotification');

        $count = $this->service->notifyFollowersForEvent($event);
        static::assertSame(0, $count);
    }

    public function testNotifyFollowersForEventSkipsWhenGlobalSettingDisabled(): void
    {
        $configService = $this->createStub(ConfigService::class);
        $configService->method('isSendRsvpNotifications')->willReturn(false);

        $service = new RsvpNotificationService(
            $this->eventRepo,
            $this->emailService,
            $configService,
            $this->createStub(LoggerInterface::class),
            $this->entityManager,
            $this->clock,
        );

        $event = $this->createStub(Event::class);
        $attendee = $this->createStub(User::class);
        $follower = $this->createStub(User::class);
        $follower->method('isNotification')->willReturn(true);
        $settings = new NotificationSettings(['followingUpdates' => true]);
        $follower->method('getNotificationSettings')->willReturn($settings);

        $event->method('getRsvp')->willReturn(new ArrayCollection([$attendee]));
        $attendee->method('getFollowers')->willReturn(new ArrayCollection([$follower]));
        $event->method('hasRsvp')->willReturn(false);

        $this->emailService->expects($this->never())->method('prepareAggregatedRsvpNotification');

        $count = $service->notifyFollowersForEvent($event);
        static::assertSame(0, $count);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testProcessUpcomingEventsSkipsWhenGlobalSettingDisabled(): void
    {
        $configService = $this->createStub(ConfigService::class);
        $configService->method('isSendRsvpNotifications')->willReturn(false);

        $eventRepo = $this->createMock(EventRepository::class);
        $service = new RsvpNotificationService(
            $eventRepo,
            $this->emailService,
            $configService,
            $this->createStub(LoggerInterface::class),
            $this->entityManager,
            $this->clock,
        );

        $eventRepo->expects($this->never())->method('findUpcomingEventsNeedingRsvpNotification');

        $result = $service->processUpcomingEvents();
        static::assertSame('disabled', $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunCronTaskSkipsBeforeAllowedHours(): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-03-13 06:59:00'));

        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo->expects($this->never())->method('findUpcomingEventsNeedingRsvpNotification');

        $service = new RsvpNotificationService(
            $eventRepo,
            $this->createStub(EmailService::class),
            $this->configService,
            $this->createStub(LoggerInterface::class),
            $this->entityManager,
            $clock,
        );

        $output = new BufferedOutput();
        $service->runCronTask($output);

        static::assertStringContainsString('outside allowed hours', $output->fetch());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunCronTaskSkipsAfterAllowedHours(): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-03-13 22:00:00'));

        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo->expects($this->never())->method('findUpcomingEventsNeedingRsvpNotification');

        $service = new RsvpNotificationService(
            $eventRepo,
            $this->createStub(EmailService::class),
            $this->configService,
            $this->createStub(LoggerInterface::class),
            $this->entityManager,
            $clock,
        );

        $output = new BufferedOutput();
        $service->runCronTask($output);

        static::assertStringContainsString('outside allowed hours', $output->fetch());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testProcessUpcomingEventsMarksEventAfterSending(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getRsvp')->willReturn(new ArrayCollection([]));
        $event->expects($this->once())->method('setRsvpNotificationSentAt');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $this->eventRepo->method('findUpcomingEventsNeedingRsvpNotification')->willReturn([$event]);

        $service = new RsvpNotificationService(
            $this->eventRepo,
            $this->createStub(EmailService::class),
            $this->configService,
            $this->createStub(LoggerInterface::class),
            $entityManager,
            $this->clock,
        );

        $service->processUpcomingEvents();
    }
}
