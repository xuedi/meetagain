<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Repository\CommandExecutionLogRepository;
use App\Repository\EventRepository;
use App\Repository\ImageRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Service\Admin\DashboardActionService;
use DateTimeImmutable;
use Module\Email\Contract\QueueStats;
use Module\Email\Contract\SendlogInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class DashboardActionServiceTest extends TestCase
{
    private Stub&EventRepository $eventRepoStub;
    private Stub&UserRepository $userRepoStub;
    private Stub&SendlogInterface $sendlogStub;
    private Stub&ImageRepository $imageRepoStub;
    private Stub&MessageRepository $messageRepoStub;
    private Stub&CommandExecutionLogRepository $commandLogRepoStub;
    private DashboardActionService $subject;

    protected function setUp(): void
    {
        $this->eventRepoStub = $this->createStub(EventRepository::class);
        $this->userRepoStub = $this->createStub(UserRepository::class);
        $this->sendlogStub = $this->createStub(SendlogInterface::class);
        $this->imageRepoStub = $this->createStub(ImageRepository::class);
        $this->messageRepoStub = $this->createStub(MessageRepository::class);
        $this->commandLogRepoStub = $this->createStub(CommandExecutionLogRepository::class);

        $this->subject = new DashboardActionService(
            $this->eventRepoStub,
            $this->userRepoStub,
            $this->sendlogStub,
            $this->imageRepoStub,
            $this->messageRepoStub,
            $this->commandLogRepoStub,
        );
    }

    public function testGetNeedForApprovalReturnsUsers(): void
    {
        $users = [new User(), new User()];
        $this->userRepoStub->method('findBy')->willReturn($users);

        $result = $this->subject->getNeedForApproval();

        static::assertCount(2, $result);
    }

    public function testGetActionItemsReturnsExpectedCounts(): void
    {
        $this->imageRepoStub->method('getReportedCount')->willReturn(3);
        $this->sendlogStub->method('stats')->willReturn(new QueueStats(pending: 10, stale: 2));

        $result = $this->subject->getActionItems();

        static::assertSame(3, $result['reportedImages']);
        static::assertSame(2, $result['staleEmails']);
        static::assertSame(10, $result['pendingEmails']);
    }

    public function testGetUserStatusBreakdownDelegatesToRepo(): void
    {
        $breakdown = ['active' => 100, 'pending' => 5, 'banned' => 2];
        $this->userRepoStub->method('getStatusBreakdown')->willReturn($breakdown);

        $result = $this->subject->getUserStatusBreakdown();

        static::assertSame($breakdown, $result);
    }

    public function testGetActiveUsersCountReturnsCount(): void
    {
        $this->userRepoStub->method('getRecentlyActiveCount')->willReturn(42);

        $result = $this->subject->getActiveUsersCount();

        static::assertSame(42, $result);
    }

    public function testGetImageStatsDelegatesToRepo(): void
    {
        $start = new DateTimeImmutable('2025-01-01');
        $stop = new DateTimeImmutable('2025-01-31');
        $stats = ['count' => 100, 'size' => 1048576];

        $this->imageRepoStub->method('getStorageStats')->willReturn($stats);

        $result = $this->subject->getImageStats($start, $stop);

        static::assertSame($stats, $result);
    }

    public function testGetPastEventsWithoutPhotosReturnsEvents(): void
    {
        $events = [new Event()];
        $this->eventRepoStub->method('getPastEventsWithoutPhotos')->willReturn($events);

        $result = $this->subject->getPastEventsWithoutPhotos(5);

        static::assertCount(1, $result);
    }

    public function testGetSeriesCountReturnsCount(): void
    {
        $this->eventRepoStub->method('getSeriesCount')->willReturn(7);

        $result = $this->subject->getSeriesCount();

        static::assertSame(7, $result);
    }

    public function testGetUnverifiedCountReturnsCount(): void
    {
        $this->userRepoStub->method('getUnverifiedCount')->willReturn(3);

        $result = $this->subject->getUnverifiedCount();

        static::assertSame(3, $result);
    }

    public function testGetMessageStatsReturnsStats(): void
    {
        $stats = ['total' => 100, 'unread' => 5];
        $this->messageRepoStub->method('getSystemStats')->willReturn($stats);

        $result = $this->subject->getMessageStats();

        static::assertSame($stats, $result);
    }

    public function testGetCommandExecutionStatsReturnsStats(): void
    {
        $stats = ['total' => 50, 'successful' => 48, 'failed' => 2];
        $this->commandLogRepoStub->method('getStats')->willReturn($stats);

        $result = $this->subject->getCommandExecutionStats();

        static::assertSame($stats, $result);
    }

    public function testGetLastCommandExecutionsReturnsLogs(): void
    {
        $logs = [];
        $this->commandLogRepoStub->method('getLastExecutionsByCommand')->willReturn($logs);

        $result = $this->subject->getLastCommandExecutions();

        static::assertSame($logs, $result);
    }
}
