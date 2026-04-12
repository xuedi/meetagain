<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\Location;
use App\Entity\NotificationSettings;
use App\Entity\User;
use App\Filter\Event\UserEventDigestFilterInterface;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\AppStateService;
use App\Service\Config\ConfigService;
use App\Service\Email\EmailService;
use App\Service\Event\UpcomingEventsDigestService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Output\BufferedOutput;

class UpcomingEventsDigestServiceTest extends TestCase
{
    private EventRepository&Stub $eventRepo;
    private UserRepository&Stub $userRepo;
    private EmailService&MockObject $emailService;
    private ConfigService&Stub $configService;
    private ClockInterface&Stub $clock;
    private AppStateService&Stub $appStateService;
    private UpcomingEventsDigestService $service;

    protected function setUp(): void
    {
        $this->eventRepo = $this->createStub(EventRepository::class);
        $this->userRepo = $this->createStub(UserRepository::class);
        $this->emailService = $this->createMock(EmailService::class);
        $this->configService = $this->createStub(ConfigService::class);
        $this->configService->method('getHost')->willReturn('https://localhost');
        $this->configService->method('isUpcomingDigestEnabled')->willReturn(true);
        $this->clock = $this->createStub(ClockInterface::class);
        // Sunday 2026-04-12 12:00 - default clock for most tests
        $this->clock->method('now')->willReturn(new DateTimeImmutable('2026-04-12 12:00:00'));
        $this->appStateService = $this->createStub(AppStateService::class);
        // Default: not already sent this week
        $this->appStateService->method('get')->willReturn(null);

        $this->service = $this->buildService([]);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunCronTaskSkipsWhenDisabled(): void
    {
        // Arrange
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getHost')->willReturn('https://localhost');
        $configService->method('isUpcomingDigestEnabled')->willReturn(false);

        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo->expects($this->never())->method('findUpcomingEventsNotRsvpdByUser');

        $service = $this->buildService([], eventRepo: $eventRepo, configService: $configService);

        // Act
        $output = new BufferedOutput();
        $result = $service->runCronTask($output);

        // Assert
        static::assertStringContainsString('disabled', $result->message);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunCronTaskSkipsOnNonSunday(): void
    {
        // Arrange - Monday
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-04-13 12:00:00'));

        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo->expects($this->never())->method('findUpcomingEventsNotRsvpdByUser');

        $service = $this->buildService([], clock: $clock, eventRepo: $eventRepo);

        // Act
        $output = new BufferedOutput();
        $result = $service->runCronTask($output);

        // Assert
        static::assertStringContainsString('not Sunday at noon', $result->message);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunCronTaskSkipsOnSundayWrongHour(): void
    {
        // Arrange - Sunday at 11:00
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-04-12 11:00:00'));

        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo->expects($this->never())->method('findUpcomingEventsNotRsvpdByUser');

        $service = $this->buildService([], clock: $clock, eventRepo: $eventRepo);

        // Act
        $output = new BufferedOutput();
        $result = $service->runCronTask($output);

        // Assert
        static::assertStringContainsString('not Sunday at noon', $result->message);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunCronTaskRunsOnSundayNoon(): void
    {
        // Arrange - Sunday at 12:00 (default clock), no subscribers
        $this->userRepo->method('findAnnouncementSubscribers')->willReturn([]);

        // Act
        $output = new BufferedOutput();
        $result = $this->service->runCronTask($output);

        // Assert
        static::assertStringContainsString('digests sent', $result->message);
        static::assertStringContainsString('Upcoming events digest:', $output->fetch());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testProcessDigestNoSubscribers(): void
    {
        // Arrange
        $this->userRepo->method('findAnnouncementSubscribers')->willReturn([]);

        // Act
        $result = $this->service->processDigest();

        // Assert
        static::assertSame('0 digests sent', $result);
    }

    public function testProcessDigestSkipsWhenAlreadySentThisWeek(): void
    {
        // Arrange - clock is Sunday 2026-04-12, upcoming week is 2026-04-13 -> week "202616"
        $appStateService = $this->createStub(AppStateService::class);
        $appStateService->method('get')->willReturn('16');

        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo->expects($this->never())->method('findUpcomingEventsNotRsvpdByUser');

        $this->emailService->expects($this->never())->method('prepareUpcomingEvents');

        // Act
        $service = $this->buildService([], eventRepo: $eventRepo, appStateService: $appStateService);
        $result = $service->processDigest();

        // Assert
        static::assertStringContainsString('already sent this week', $result);
    }

    public function testProcessDigestSkipsUserWithUpcomingEventsDisabled(): void
    {
        // Arrange
        $settings = new NotificationSettings(['upcomingEvents' => false]);
        $user = $this->createStub(User::class);
        $user->method('getNotificationSettings')->willReturn($settings);

        $this->userRepo->method('findAnnouncementSubscribers')->willReturn([$user]);
        $this->emailService->expects($this->never())->method('prepareUpcomingEvents');

        // Act
        $result = $this->service->processDigest();

        // Assert
        static::assertSame('0 digests sent', $result);
    }

    public function testProcessDigestSkipsUserWithNoEvents(): void
    {
        // Arrange
        $settings = new NotificationSettings(['upcomingEvents' => true]);
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getNotificationSettings')->willReturn($settings);

        $this->userRepo->method('findAnnouncementSubscribers')->willReturn([$user]);
        $this->eventRepo->method('findUpcomingEventsNotRsvpdByUser')->willReturn([]);
        $this->emailService->expects($this->never())->method('prepareUpcomingEvents');

        // Act
        $result = $this->service->processDigest();

        // Assert
        static::assertSame('0 digests sent', $result);
    }

    public function testProcessDigestSendsEmailAndMarksWeek(): void
    {
        // Arrange
        $settings = new NotificationSettings(['upcomingEvents' => true]);
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getNotificationSettings')->willReturn($settings);
        $user->method('getLocale')->willReturn('en');

        $location = $this->createStub(Location::class);
        $location->method('getName')->willReturn('NightBar 64');

        $event = $this->createStub(Event::class);
        $event->method('getId')->willReturn(42);
        $event->method('getTitle')->willReturn('Go tournament');
        $event->method('getLocation')->willReturn($location);
        $event->method('getStart')->willReturn(new DateTimeImmutable('2026-04-19 19:00:00'));

        $this->userRepo->method('findAnnouncementSubscribers')->willReturn([$user]);
        $this->eventRepo->method('findUpcomingEventsNotRsvpdByUser')->willReturn([$event]);

        $appStateService = $this->createMock(AppStateService::class);
        $appStateService->method('get')->willReturn(null);
        // Expect the upcoming week (Monday 2026-04-13 -> oW = "202616") to be stored
        $appStateService->expects($this->once())
            ->method('set')
            ->with('upcoming_events_digest_last_week', '16');

        // Act + Assert
        $this->emailService
            ->expects($this->once())
            ->method('prepareUpcomingEvents')
            ->with($user, $this->stringContains('Go tournament'));

        $service = $this->buildService([], appStateService: $appStateService);
        $result = $service->processDigest();
        static::assertSame('1 digests sent', $result);
    }

    public function testProcessDigestAppliesFilter(): void
    {
        // Arrange
        $settings = new NotificationSettings(['upcomingEvents' => true]);
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getNotificationSettings')->willReturn($settings);

        $event = $this->createStub(Event::class);

        $this->userRepo->method('findAnnouncementSubscribers')->willReturn([$user]);
        $this->eventRepo->method('findUpcomingEventsNotRsvpdByUser')->willReturn([$event]);

        $filter = $this->createStub(UserEventDigestFilterInterface::class);
        $filter->method('filterForUser')->willReturn([]);

        $this->emailService->expects($this->never())->method('prepareUpcomingEvents');

        // Act
        $service = $this->buildService([$filter]);
        $result = $service->processDigest();

        // Assert
        static::assertSame('0 digests sent', $result);
    }

    /**
     * @param array<UserEventDigestFilterInterface> $filters
     */
    private function buildService(
        array $filters,
        ?ClockInterface $clock = null,
        ?EventRepository $eventRepo = null,
        ?AppStateService $appStateService = null,
        ?ConfigService $configService = null,
    ): UpcomingEventsDigestService {
        return new UpcomingEventsDigestService(
            $eventRepo ?? $this->eventRepo,
            $this->userRepo,
            $this->emailService,
            $configService ?? $this->configService,
            $this->createStub(LoggerInterface::class),
            $clock ?? $this->clock,
            $appStateService ?? $this->appStateService,
            new \ArrayObject($filters),
        );
    }
}
