<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\NotificationSettings;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Service\Config\ConfigService;
use App\Service\Email\EmailService;
use App\Service\Event\EventReminderService;
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

class EventReminderServiceTest extends TestCase
{
    private EventRepository&Stub $eventRepo;
    private EmailService&MockObject $emailService;
    private EntityManagerInterface&Stub $entityManager;
    private ClockInterface&Stub $clock;
    private ConfigService&Stub $config;
    private EventReminderService $service;

    protected function setUp(): void
    {
        $this->eventRepo = $this->createStub(EventRepository::class);
        $this->emailService = $this->createMock(EmailService::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->clock = $this->createStub(ClockInterface::class);
        $this->clock->method('now')->willReturn(new DateTimeImmutable('2026-04-12 10:00:00'));
        $this->config = $this->createStub(ConfigService::class);
        $this->config->method('isEventRemindersEnabled')->willReturn(true);

        $this->service = $this->buildService();
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunCronTaskSkipsWhenDisabled(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('isEventRemindersEnabled')->willReturn(false);

        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo->expects($this->never())->method('findEventsNeedingReminder');

        $service = $this->buildService(eventRepo: $eventRepo, config: $config);

        // Act
        $output = new BufferedOutput();
        $result = $service->runCronTask($output);

        // Assert
        static::assertStringContainsString('disabled', $result->message);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunCronTaskSkipsBeforeAllowedHours(): void
    {
        // Arrange
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-04-12 06:59:00'));

        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo->expects($this->never())->method('findEventsNeedingReminder');

        $service = $this->buildService(eventRepo: $eventRepo, clock: $clock);

        // Act
        $output = new BufferedOutput();
        $service->runCronTask($output);

        // Assert
        static::assertStringContainsString('outside allowed hours', $output->fetch());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunCronTaskSkipsAfterAllowedHours(): void
    {
        // Arrange
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-04-12 22:00:00'));

        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo->expects($this->never())->method('findEventsNeedingReminder');

        $service = $this->buildService(eventRepo: $eventRepo, clock: $clock);

        // Act
        $output = new BufferedOutput();
        $service->runCronTask($output);

        // Assert
        static::assertStringContainsString('outside allowed hours', $output->fetch());
    }

    public function testProcessRemindersNoEvents(): void
    {
        // Arrange
        $this->eventRepo->method('findEventsNeedingReminder')->willReturn([]);
        $this->emailService->expects($this->never())->method('prepareEventReminder');

        // Act
        $result = $this->service->processReminders();

        // Assert
        static::assertSame('0 reminders sent', $result);
    }

    public function testProcessRemindersSendsToRsvpdUsers(): void
    {
        // Arrange
        $settings = new NotificationSettings(['eventReminder' => true]);

        $user1 = $this->createStub(User::class);
        $user1->method('isNotification')->willReturn(true);
        $user1->method('getNotificationSettings')->willReturn($settings);

        $user2 = $this->createStub(User::class);
        $user2->method('isNotification')->willReturn(true);
        $user2->method('getNotificationSettings')->willReturn($settings);

        $event = $this->createStub(Event::class);
        $event->method('getRsvp')->willReturn(new ArrayCollection([$user1, $user2]));

        $this->eventRepo->method('findEventsNeedingReminder')->willReturn([$event]);

        // Act + Assert
        $this->emailService
            ->expects($this->exactly(2))
            ->method('prepareEventReminder');

        $result = $this->service->processReminders();
        static::assertSame('2 reminders sent', $result);
    }

    public function testProcessRemindersSkipsUserWithNotificationsDisabled(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $user->method('isNotification')->willReturn(false);

        $event = $this->createStub(Event::class);
        $event->method('getRsvp')->willReturn(new ArrayCollection([$user]));

        $this->eventRepo->method('findEventsNeedingReminder')->willReturn([$event]);
        $this->emailService->expects($this->never())->method('prepareEventReminder');

        // Act
        $result = $this->service->processReminders();

        // Assert
        static::assertSame('0 reminders sent', $result);
    }

    public function testProcessRemindersSkipsUserWithEventReminderDisabled(): void
    {
        // Arrange
        $settings = new NotificationSettings(['eventReminder' => false]);

        $user = $this->createStub(User::class);
        $user->method('isNotification')->willReturn(true);
        $user->method('getNotificationSettings')->willReturn($settings);

        $event = $this->createStub(Event::class);
        $event->method('getRsvp')->willReturn(new ArrayCollection([$user]));

        $this->eventRepo->method('findEventsNeedingReminder')->willReturn([$event]);
        $this->emailService->expects($this->never())->method('prepareEventReminder');

        // Act
        $result = $this->service->processReminders();

        // Assert
        static::assertSame('0 reminders sent', $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testProcessRemindersStampsEvent(): void
    {
        // Arrange
        $event = $this->createMock(Event::class);
        $event->method('getRsvp')->willReturn(new ArrayCollection([]));
        $event->expects($this->once())->method('setEventReminderSentAt');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $this->eventRepo->method('findEventsNeedingReminder')->willReturn([$event]);

        $service = $this->buildService(entityManager: $entityManager);

        // Act
        $service->processReminders();
    }

    private function buildService(
        ?EventRepository $eventRepo = null,
        ?ClockInterface $clock = null,
        ?EntityManagerInterface $entityManager = null,
        ?ConfigService $config = null,
    ): EventReminderService {
        return new EventReminderService(
            $eventRepo ?? $this->eventRepo,
            $this->emailService,
            $this->createStub(LoggerInterface::class),
            $entityManager ?? $this->entityManager,
            $clock ?? $this->clock,
            $config ?? $this->config,
        );
    }
}
