<?php declare(strict_types=1);

namespace Tests\Unit\Contribution;

use App\Contribution\ScopeFilterInterface;
use App\Contribution\ScopeFilterService;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class ScopeFilterServiceTest extends TestCase
{
    public function testAnEmptyChainMeansNoOpinionAndNothingIsNarrowed(): void
    {
        // Arrange
        $service = new ScopeFilterService([]);

        // Act
        $reachable = $service->narrow('location', [1, 2, 3], new User());

        // Assert
        self::assertSame([1, 2, 3], $reachable);
    }

    public function testFiltersIntersectRatherThanUnion(): void
    {
        // Arrange
        $service = new ScopeFilterService([$this->filter([1, 2]), $this->filter([2, 3])]);

        // Act
        $reachable = $service->narrow('location', [1, 2, 3], new User());

        // Assert
        self::assertSame([2], $reachable);
    }

    public function testNullIsNoOpinionAndDoesNotNarrow(): void
    {
        // Arrange
        $service = new ScopeFilterService([$this->filter(null), $this->filter([3])]);

        // Act
        $reachable = $service->narrow('location', [1, 2, 3], new User());

        // Assert
        self::assertSame([3], $reachable);
    }

    public function testAnEmptyListBlocksEverything(): void
    {
        // Arrange
        $service = new ScopeFilterService([$this->filter([]), $this->filter([1, 2, 3])]);

        // Act
        $reachable = $service->narrow('location', [1, 2, 3], new User());

        // Assert
        self::assertSame([], $reachable);
    }

    public function testAFilterOnlyNarrowsTheTypeItClaims(): void
    {
        // Arrange
        $service = new ScopeFilterService([$this->filter([1], onlyType: 'location')]);

        // Act
        $locations = $service->narrow('location', [1, 2], new User());
        $events = $service->narrow('event', [1, 2], new User());

        // Assert
        self::assertSame([1], $locations);
        self::assertSame([1, 2], $events);
    }

    public function testAllowsAnswersForASingleRow(): void
    {
        // Arrange
        $service = new ScopeFilterService([$this->filter([2])]);

        // Act
        $verdicts = [$service->allows('location', 2, new User()), $service->allows('location', 1, new User())];

        // Assert
        self::assertSame([true, false], $verdicts);
    }

    public function testTheHigherPriorityFilterRunsFirst(): void
    {
        // Arrange
        $order = [];
        $service = new ScopeFilterService([$this->recordingFilter('low', 0, $order), $this->recordingFilter('high', 10, $order)]);

        // Act
        $service->narrow('location', [1, 2], new User());

        // Assert
        self::assertSame(['high', 'low'], $order);
    }

    /**
     * @param list<int>|null $visible
     */
    private function filter(?array $visible, ?string $onlyType = null): ScopeFilterInterface
    {
        return new class($visible, $onlyType) implements ScopeFilterInterface {
            /**
             * @param list<int>|null $visible
             */
            public function __construct(
                private readonly ?array $visible,
                private readonly ?string $onlyType,
            ) {}

            public function getPriority(): int
            {
                return 0;
            }

            public function narrowContributableIds(string $type, array $ids, User $user): ?array
            {
                if ($this->onlyType !== null && $this->onlyType !== $type) {
                    return null;
                }

                return $this->visible;
            }
        };
    }

    /**
     * @param list<string> $order
     */
    private function recordingFilter(string $name, int $priority, array &$order): ScopeFilterInterface
    {
        return new class($name, $priority, $order) implements ScopeFilterInterface {
            /**
             * @param list<string> $order
             */
            public function __construct(
                private readonly string $name,
                private readonly int $priority,
                private array &$order,
            ) {}

            public function getPriority(): int
            {
                return $this->priority;
            }

            public function narrowContributableIds(string $type, array $ids, User $user): ?array
            {
                $this->order[] = $this->name;

                return null;
            }
        };
    }
}
