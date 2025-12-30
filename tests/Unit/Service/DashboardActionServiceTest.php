<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Repository\EmailQueueRepository;
use App\Repository\EventRepository;
use App\Repository\ImageRepository;
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
    private DashboardActionService $subject;

    protected function setUp(): void
    {
        $this->eventRepoStub = $this->createStub(EventRepository::class);
        $this->userRepoStub = $this->createStub(UserRepository::class);
        $this->mailRepoStub = $this->createStub(EmailQueueRepository::class);
        $this->imageRepoStub = $this->createStub(ImageRepository::class);
        $this->translationRepoStub = $this->createStub(TranslationSuggestionRepository::class);

        $this->subject = new DashboardActionService(
            $this->eventRepoStub,
            $this->userRepoStub,
            $this->mailRepoStub,
            $this->imageRepoStub,
            $this->translationRepoStub
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
}
