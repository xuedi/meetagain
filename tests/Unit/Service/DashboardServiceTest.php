<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Repository\ActivityRepository;
use App\Repository\EmailQueueRepository;
use App\Repository\EventRepository;
use App\Repository\NotFoundLogRepository;
use App\Repository\UserRepository;
use App\Service\DashboardService;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Common\Collections\ReadableCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DashboardServiceTest extends TestCase
{
    /**
     * Create a lazy, selectable collection that returns a fixed count,
     * satisfying Doctrine's intersection return type (AbstractLazyCollection & Selectable).
     */
    private static function makeLazySelectableWithCount(int $count): AbstractLazyCollection&Selectable
    {
        return new class($count) extends AbstractLazyCollection implements Selectable {
            private int $count;
            public function __construct(int $count) { $this->count = $count; }
            protected function doInitialize(): void
            {
                $this->collection = new ArrayCollection(array_fill(0, $this->count, 1));
                $this->initialized = true;
            }
            public function matching(Criteria $criteria): ReadableCollection&Selectable
            {
                // already initialized to an ArrayCollection; forward call
                $this->initialize();
                \assert($this->collection instanceof Selectable);
                return $this->collection->matching($criteria);
            }
        };
    }
    private EventRepository&MockObject $eventRepo;
    private UserRepository&MockObject $userRepo;
    private EmailQueueRepository&MockObject $mailRepo;
    private NotFoundLogRepository&MockObject $notFoundRepo;
    private ActivityRepository&MockObject $activityRepo;

    private DashboardService $service;

    protected function setUp(): void
    {
        $this->eventRepo = $this->createMock(EventRepository::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->mailRepo = $this->createMock(EmailQueueRepository::class);
        $this->notFoundRepo = $this->createMock(NotFoundLogRepository::class);
        $this->activityRepo = $this->createMock(ActivityRepository::class);

        $this->service = new DashboardService(
            $this->eventRepo,
            $this->userRepo,
            $this->mailRepo,
            $this->notFoundRepo,
            $this->activityRepo,
        );

        // Fix the time window deterministically (ISO week 10 of 2024)
        $this->service->setTime(2024, 10);
    }

    public function testGetTimeControlReturnsExpectedStructure(): void
    {
        $time = $this->service->getTimeControl();

        $this->assertSame(10, $time['week']);
        $this->assertSame(2024, $time['year']);
        $this->assertSame(11, $time['weekNext']);
        $this->assertSame(9, $time['weekPrevious']);

        // Week 10 of 2024 starts on 2024-03-04 and ends on 2024-03-10
        $this->assertSame('2024-03-04 - 2024-03-10', $time['weekDetails']);
    }

    public function testGetDetailsAggregatesCountsAndWeeklyCounts(): void
    {
        // Arrange generic expectations for repositories
        $this->notFoundRepo->expects($this->once())->method('count')->willReturn(7);
        $this->notFoundRepo->expects($this->once())->method('matching')
            ->with($this->isInstanceOf(Criteria::class))
            ->willReturn(self::makeLazySelectableWithCount(2)); // week=2

        $this->userRepo->expects($this->once())->method('count')->willReturn(5);
        $this->userRepo->expects($this->once())->method('matching')
            ->with($this->isInstanceOf(Criteria::class))
            ->willReturn(self::makeLazySelectableWithCount(3)); // week=3

        $this->activityRepo->expects($this->once())->method('count')->willReturn(12);
        $this->activityRepo->expects($this->once())->method('matching')
            ->with($this->isInstanceOf(Criteria::class))
            ->willReturn(self::makeLazySelectableWithCount(1)); // week=1

        $this->eventRepo->expects($this->once())->method('count')->willReturn(9);
        $this->eventRepo->expects($this->once())->method('matching')
            ->with($this->isInstanceOf(Criteria::class))
            ->willReturn(self::makeLazySelectableWithCount(4)); // week=4

        $this->mailRepo->expects($this->once())->method('count')->willReturn(11);
        $this->mailRepo->expects($this->once())->method('matching')
            ->with($this->isInstanceOf(Criteria::class))
            ->willReturn(self::makeLazySelectableWithCount(5)); // week=5

        // Act
        $details = $this->service->getDetails();

        // Assert
        $this->assertSame(['count' => 7, 'week' => 2], $details['404pages']);
        $this->assertSame(['count' => 5, 'week' => 3], $details['members']);
        $this->assertSame(['count' => 12, 'week' => 1], $details['activity']);
        $this->assertSame(['count' => 9, 'week' => 4], $details['events']);
        $this->assertSame(['count' => 11, 'week' => 5], $details['emails']);
    }

    public function testGetPagesNotFoundDelegatesToRepository(): void
    {
        $expected = [
            'Monday' => 1,
            'Tuesday' => 0,
            'Wednesday' => 2,
            'Thursday' => 0,
            'Friday' => 0,
            'Saturday' => 0,
            'Sunday' => 0,
        ];

        $this->notFoundRepo
            ->expects($this->once())
            ->method('getWeekSummary')
            ->with(
                $this->isInstanceOf(DateTimeImmutable::class),
                $this->isInstanceOf(DateTimeImmutable::class)
            )
            ->willReturn($expected);

        $result = $this->service->getPagesNotFound();

        $this->assertSame(['list' => $expected], $result);
    }

    public function testGetNeedForApprovalUsesCorrectFilterAndOrder(): void
    {
        $users = ['u1', 'u2'];
        $this->userRepo
            ->expects($this->once())
            ->method('findBy')
            ->with(['status' => 1], ['createdAt' => 'desc'])
            ->willReturn($users);

        $this->assertSame($users, $this->service->getNeedForApproval());
    }
}
