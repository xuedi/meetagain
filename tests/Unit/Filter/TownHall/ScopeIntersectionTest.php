<?php declare(strict_types=1);

namespace Tests\Unit\Filter\TownHall;

use App\Filter\TownHall\ScopeIntersection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScopeIntersectionTest extends TestCase
{
    public static function idListProvider(): iterable
    {
        yield 'no filter at all leaves the scope open' => [[], null];
        yield 'a filter without an opinion leaves the scope open' => [[null], null];
        yield 'a single allow-list becomes the scope' => [[[3, 1, 2]], [3, 1, 2]];
        yield 'two allow-lists intersect' => [[[1, 2, 3], [2, 3, 4]], [2, 3]];
        yield 'an opinionless filter is skipped' => [[null, [1, 2], null], [1, 2]];
        yield 'an empty allow-list blocks everything' => [[[1, 2], []], []];
        yield 'a disjoint intersection blocks everything' => [[[1, 2], [3, 4]], []];
    }

    #[DataProvider('idListProvider')]
    public function testIntersection(array $idLists, ?array $expected): void
    {
        // Arrange
        $intersection = new ScopeIntersection();

        // Act
        $result = $intersection->of($idLists);

        // Assert
        self::assertSame($expected, $result);
    }

    public function testAnEmptyListShortCircuitsBeforeLaterFilters(): void
    {
        // Arrange
        $intersection = new ScopeIntersection();
        $reached = false;
        $lists = (static function () use (&$reached): iterable {
            yield [];
            $reached = true;
            yield [1, 2];
        })();

        // Act
        $result = $intersection->of($lists);

        // Assert
        self::assertSame([], $result);
        self::assertFalse($reached);
    }
}
