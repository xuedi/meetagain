<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Repository\CommandExecutionLogRepository;
use App\Repository\EmailQueueRepository;
use App\Repository\EventRepository;
use App\Repository\ImageRepository;
use App\Repository\LoginAttemptRepository;
use App\Repository\MessageRepository;
use App\Repository\TranslationSuggestionRepository;
use App\Repository\UserRepository;
use App\Service\DashboardActionService;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class DashboardActionServiceTest extends TestCase
{
    private Stub&EventRepository $eventRepoStub;
    private Stub&UserRepository $userRepoStub;
    private Stub&EmailQueueRepository $mailRepoStub;
    private Stub&ImageRepository $imageRepoStub;
    private Stub&TranslationSuggestionRepository $translationRepoStub;
    private Stub&MessageRepository $messageRepoStub;
    private Stub&LoginAttemptRepository $loginAttemptRepoStub;
    private Stub&CommandExecutionLogRepository $commandLogRepoStub;
    private DashboardActionService $subject;

    protected function setUp(): void
    {
        $this->eventRepoStub = $this->createStub(EventRepository::class);
        $this->userRepoStub = $this->createStub(UserRepository::class);
        $this->mailRepoStub = $this->createStub(EmailQueueRepository::class);
        $this->imageRepoStub = $this->createStub(ImageRepository::class);
        $this->translationRepoStub = $this->createStub(TranslationSuggestionRepository::class);
        $this->messageRepoStub = $this->createStub(MessageRepository::class);
        $this->loginAttemptRepoStub = $this->createStub(LoginAttemptRepository::class);
        $this->commandLogRepoStub = $this->createStub(CommandExecutionLogRepository::class);

        $this->subject = new DashboardActionService(
            $this->eventRepoStub,
            $this->userRepoStub,
            $this->mailRepoStub,
            $this->imageRepoStub,
            $this->translationRepoStub,
            $this->messageRepoStub,
            $this->loginAttemptRepoStub,
            $this->commandLogRepoStub
        );
    }

    public function testGetNeedForApprovalReturnsUsers(): void
    {
        $users = [new User(), new User()];
        $this->userRepoStub->method('findBy')->willReturn($users);

        $result = $this->subject->getNeedForApproval();

        $this->assertCount(2, $result);
    }

    public function testGetActionItemsReturnsExpectedCounts(): void
    {
        $this->imageRepoStub->method('getReportedCount')->willReturn(3);
        $this->translationRepoStub->method('getPendingCount')->willReturn(5);
        $this->mailRepoStub->method('getStaleCount')->willReturn(2);
        $this->mailRepoStub->method('getPendingCount')->willReturn(10);

        $result = $this->subject->getActionItems();

        $this->assertSame(3, $result['reportedImages']);
        $this->assertSame(5, $result['pendingTranslations']);
        $this->assertSame(2, $result['staleEmails']);
        $this->assertSame(10, $result['pendingEmails']);
    }

    public function testGetUserStatusBreakdownDelegatesToRepo(): void
    {
        $breakdown = ['active' => 100, 'pending' => 5, 'banned' => 2];
        $this->userRepoStub->method('getStatusBreakdown')->willReturn($breakdown);

        $result = $this->subject->getUserStatusBreakdown();

        $this->assertSame($breakdown, $result);
    }

    public function testGetActiveUsersCountReturnsCount(): void
    {
        $this->userRepoStub->method('getRecentlyActiveCount')->willReturn(42);

        $result = $this->subject->getActiveUsersCount();

        $this->assertSame(42, $result);
    }

    public function testGetImageStatsDelegatesToRepo(): void
    {
        $start = new DateTimeImmutable('2025-01-01');
        $stop = new DateTimeImmutable('2025-01-31');
        $stats = ['count' => 100, 'size' => 1048576];

        $this->imageRepoStub->method('getStorageStats')->willReturn($stats);

        $result = $this->subject->getImageStats($start, $stop);

        $this->assertSame($stats, $result);
    }

    public function testGetUpcomingEventsReturnsEvents(): void
    {
        $events = [new Event(), new Event(), new Event()];
        $this->eventRepoStub->method('getUpcomingEvents')->willReturn($events);

        $result = $this->subject->getUpcomingEvents(3);

        $this->assertCount(3, $result);
    }

    public function testGetPastEventsWithoutPhotosReturnsEvents(): void
    {
        $events = [new Event()];
        $this->eventRepoStub->method('getPastEventsWithoutPhotos')->willReturn($events);

        $result = $this->subject->getPastEventsWithoutPhotos(5);

        $this->assertCount(1, $result);
    }

    public function testGetRecurringEventsCountReturnsCount(): void
    {
        $this->eventRepoStub->method('getRecurringCount')->willReturn(7);

        $result = $this->subject->getRecurringEventsCount();

        $this->assertSame(7, $result);
    }

    public function testGetPendingSuggestionsCountReturnsCount(): void
    {
        $this->translationRepoStub->method('getPendingCount')->willReturn(15);

        $result = $this->subject->getPendingSuggestionsCount();

        $this->assertSame(15, $result);
    }

    public function testGetUnverifiedCountReturnsCount(): void
    {
        $this->userRepoStub->method('getUnverifiedCount')->willReturn(3);

        $result = $this->subject->getUnverifiedCount();

        $this->assertSame(3, $result);
    }

    public function testGetMessageStatsReturnsStats(): void
    {
        $stats = ['total' => 100, 'unread' => 5];
        $this->messageRepoStub->method('getSystemStats')->willReturn($stats);

        $result = $this->subject->getMessageStats();

        $this->assertSame($stats, $result);
    }

    public function testGetEmailQueueBreakdownReturnsBreakdown(): void
    {
        $breakdown = ['welcome' => 5, 'password_reset' => 2];
        $this->mailRepoStub->method('getPendingByTemplate')->willReturn($breakdown);

        $result = $this->subject->getEmailQueueBreakdown();

        $this->assertSame($breakdown, $result);
    }

    public function testGetLoginAttemptStatsReturnsStats(): void
    {
        $stats = ['total' => 100, 'successful' => 95, 'failed' => 5];
        $this->loginAttemptRepoStub->method('getStats')->willReturn($stats);

        $result = $this->subject->getLoginAttemptStats();

        $this->assertSame($stats, $result);
    }

    public function testGetCommandExecutionStatsReturnsStats(): void
    {
        $stats = ['total' => 50, 'successful' => 48, 'failed' => 2];
        $this->commandLogRepoStub->method('getStats')->willReturn($stats);

        $result = $this->subject->getCommandExecutionStats();

        $this->assertSame($stats, $result);
    }

    public function testGetLastCommandExecutionsReturnsLogs(): void
    {
        $logs = [];
        $this->commandLogRepoStub->method('getLastExecutionsByCommand')->willReturn($logs);

        $result = $this->subject->getLastCommandExecutions();

        $this->assertSame($logs, $result);
    }

    public function testGetEmailDeliveryStatsReturnsStats(): void
    {
        $stats = ['total' => 100, 'sent' => 98, 'failed' => 2];
        $this->mailRepoStub->method('getDeliveryStats')->willReturn($stats);

        $result = $this->subject->getEmailDeliveryStats();

        $this->assertSame($stats, $result);
    }

    public function testGetEmailDeliverySuccessRateReturnsRate(): void
    {
        $this->mailRepoStub->method('getDeliverySuccessRate')->willReturn(98.5);

        $result = $this->subject->getEmailDeliverySuccessRate();

        $this->assertSame(98.5, $result);
    }
}
