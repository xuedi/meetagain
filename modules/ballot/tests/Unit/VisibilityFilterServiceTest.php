<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Unit;

use Module\Ballot\Contract\BallotScope;
use Module\Ballot\Contract\VisibilityFilterInterface;
use Module\Ballot\Internal\VisibilityFilterService;
use PHPUnit\Framework\TestCase;

class VisibilityFilterServiceTest extends TestCase
{
    public function testWithNoFilterEverythingStaysVisible(): void
    {
        // Arrange
        $service = new VisibilityFilterService([]);

        // Act
        $visible = $service->narrow('any', $this->scopes(1, 2, 3), 7);

        // Assert
        self::assertSame([1, 2, 3], $visible);
    }

    public function testFiltersIntersectRatherThanUnion(): void
    {
        // Arrange
        $service = new VisibilityFilterService([
            $this->filter([1, 2]),
            $this->filter([2, 3]),
        ]);

        // Act
        $visible = $service->narrow('any', $this->scopes(1, 2, 3), 7);

        // Assert
        self::assertSame([2], $visible);
    }

    public function testNullIsNoOpinionAndDoesNotNarrow(): void
    {
        // Arrange
        $service = new VisibilityFilterService([$this->filter(null), $this->filter([3])]);

        // Act
        $visible = $service->narrow('any', $this->scopes(1, 2, 3), 7);

        // Assert
        self::assertSame([3], $visible);
    }

    public function testAnEmptyListBlocksEverything(): void
    {
        // Arrange
        $service = new VisibilityFilterService([$this->filter([]), $this->filter([1, 2, 3])]);

        // Act
        $visible = $service->narrow('any', $this->scopes(1, 2, 3), 7);

        // Assert
        self::assertSame([], $visible);
    }

    public function testAllowsAnswersForASingleBallot(): void
    {
        // Arrange
        $service = new VisibilityFilterService([$this->filter([2])]);

        // Act
        $verdicts = [$service->allows($this->scopes(2)[0], 7), $service->allows($this->scopes(1)[0], 7)];

        // Assert
        self::assertSame([true, false], $verdicts);
    }

    /**
     * @return list<BallotScope>
     */
    private function scopes(int ...$ids): array
    {
        return array_map(static fn(int $id): BallotScope => new BallotScope($id, 'any'), array_values($ids));
    }

    /**
     * @param list<int>|null $visible
     */
    private function filter(?array $visible): VisibilityFilterInterface
    {
        return new class($visible) implements VisibilityFilterInterface {
            /**
             * @param list<int>|null $visible
             */
            public function __construct(
                private readonly ?array $visible,
            ) {}

            public function getPriority(): int
            {
                return 0;
            }

            public function narrowVisibleBallotIds(string $purpose, array $ballots, ?int $viewerUserId): ?array
            {
                return $this->visible;
            }
        };
    }
}
