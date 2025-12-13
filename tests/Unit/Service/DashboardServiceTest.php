<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Repository\ActivityRepository;
use App\Repository\EmailQueueRepository;
use App\Repository\EventRepository;
use App\Repository\NotFoundLogRepository;
use App\Repository\UserRepository;
use App\Service\DashboardService;
use DateTimeImmutable;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\Common\Collections\Selectable;
use PHPUnit\Framework\TestCase;

final class DashboardServiceTest extends TestCase
{
    /**
     * Create a lazy, selectable collection that returns a fixed count,
     * satisfying Doctrine's intersection return type (AbstractLazyCollection & Selectable).
     */
    private static function makeLazySelectableWithCount(int $count): AbstractLazyCollection
    {
        return new class ($count) extends AbstractLazyCollection implements Selectable {
            private int $count;

            public function __construct(int $count)
            {
                $this->count = $count;
            }

            protected function doInitialize(): void
            {
                $this->collection = new ArrayCollection(array_fill(0, $this->count, 1));
                $this->initialized = true;
            }

            public function matching(Criteria $criteria): ReadableCollection&Selectable
            {
                $this->initialize();
                \assert($this->collection instanceof Selectable);
                return $this->collection->matching($criteria);
            }
        };
    }

    private function createService(
        ?EventRepository $eventRepo = null,
        ?UserRepository $userRepo = null,
        ?EmailQueueRepository $mailRepo = null,
        ?NotFoundLogRepository $notFoundRepo = null,
        ?ActivityRepository $activityRepo = null,
    ): DashboardService {
        $service = new DashboardService(
            $eventRepo ?? $this->createStub(EventRepository::class),
            $userRepo ?? $this->createStub(UserRepository::class),
            $mailRepo ?? $this->createStub(EmailQueueRepository::class),
            $notFoundRepo ?? $this->createStub(NotFoundLogRepository::class),
            $activityRepo ?? $this->createStub(ActivityRepository::class),
        );

        // Fix the time window deterministically (ISO week 10 of 2024)
        $service->setTime(2024, 10);

        return $service;
    }

    public function testGetTimeControlReturnsExpectedStructure(): void
    {
        // Arrange: create service with default stubs
        $service = $this->createService();

        // Act: get time control data
        $time = $service->getTimeControl();

        // Assert: expected week values and date range
        $this->assertSame(10, $time['week']);
        $this->assertSame(2024, $time['year']);
        $this->assertSame(11, $time['weekNext']);
        $this->assertSame(9, $time['weekPrevious']);
        $this->assertSame('2024-03-04 - 2024-03-10', $time['weekDetails']);
    }

    public function testGetDetailsAggregatesCountsAndWeeklyCounts(): void
    {
        // Arrange: mock notFound repository to return count and weekly matching
        $notFoundRepo = $this->createMock(NotFoundLogRepository::class);
        $notFoundRepo->expects($this->once())->method('count')->willReturn(7);
        $notFoundRepo->expects($this->once())->method('matching')
            ->with($this->isInstanceOf(Criteria::class))
            ->willReturn(self::makeLazySelectableWithCount(2));

        // Arrange: mock user repository to return count and weekly matching
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())->method('count')->willReturn(5);
        $userRepo->expects($this->once())->method('matching')
            ->with($this->isInstanceOf(Criteria::class))
            ->willReturn(self::makeLazySelectableWithCount(3));

        // Arrange: mock activity repository to return count and weekly matching
        $activityRepo = $this->createMock(ActivityRepository::class);
        $activityRepo->expects($this->once())->method('count')->willReturn(12);
        $activityRepo->expects($this->once())->method('matching')
            ->with($this->isInstanceOf(Criteria::class))
            ->willReturn(self::makeLazySelectableWithCount(1));

        // Arrange: mock event repository to return count and weekly matching
        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo->expects($this->once())->method('count')->willReturn(9);
        $eventRepo->expects($this->once())->method('matching')
            ->with($this->isInstanceOf(Criteria::class))
            ->willReturn(self::makeLazySelectableWithCount(4));

        // Arrange: mock mail repository to return count and weekly matching
        $mailRepo = $this->createMock(EmailQueueRepository::class);
        $mailRepo->expects($this->once())->method('count')->willReturn(11);
        $mailRepo->expects($this->once())->method('matching')
            ->with($this->isInstanceOf(Criteria::class))
            ->willReturn(self::makeLazySelectableWithCount(5));

        $service = $this->createService(
            eventRepo: $eventRepo,
            userRepo: $userRepo,
            mailRepo: $mailRepo,
            notFoundRepo: $notFoundRepo,
            activityRepo: $activityRepo,
        );

        // Act: get details
        $details = $service->getDetails();

        // Assert: counts and weekly counts are aggregated correctly
        $this->assertSame(['count' => 7, 'week' => 2], $details['404pages']);
        $this->assertSame(['count' => 5, 'week' => 3], $details['members']);
        $this->assertSame(['count' => 12, 'week' => 1], $details['activity']);
        $this->assertSame(['count' => 9, 'week' => 4], $details['events']);
        $this->assertSame(['count' => 11, 'week' => 5], $details['emails']);
    }

    public function testGetPagesNotFoundDelegatesToRepository(): void
    {
        // Arrange: mock notFound repository to return week summary
        $expected = [
            'Monday' => 1,
            'Tuesday' => 0,
            'Wednesday' => 2,
            'Thursday' => 0,
            'Friday' => 0,
            'Saturday' => 0,
            'Sunday' => 0,
        ];

        $notFoundRepo = $this->createMock(NotFoundLogRepository::class);
        $notFoundRepo
            ->expects($this->once())
            ->method('getWeekSummary')
            ->with(
                $this->isInstanceOf(DateTimeImmutable::class),
                $this->isInstanceOf(DateTimeImmutable::class)
            )
            ->willReturn($expected);

        $service = $this->createService(notFoundRepo: $notFoundRepo);

        // Act: get pages not found
        $result = $service->getPagesNotFound();

        // Assert: returns week summary in expected format
        $this->assertSame(['list' => $expected], $result);
    }

    public function testGetNeedForApprovalUsesCorrectFilterAndOrder(): void
    {
        // Arrange: mock user repository to return users needing approval
        $users = ['u1', 'u2'];
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo
            ->expects($this->once())
            ->method('findBy')
            ->with(['status' => 1], ['createdAt' => 'desc'])
            ->willReturn($users);

        $service = $this->createService(userRepo: $userRepo);

        // Act & Assert: returns users needing approval
        $this->assertSame($users, $service->getNeedForApproval());
    }
}
