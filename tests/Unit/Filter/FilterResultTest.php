<?php declare(strict_types=1);

namespace Tests\Unit\Filter;

use App\Filter\Location\LocationFilterResult;
use App\Filter\Member\MemberFilterResult;
use PHPUnit\Framework\TestCase;

class FilterResultTest extends TestCase
{
    // =========================================================================
    // LocationFilterResult
    // =========================================================================

    public function testLocationFilterResultNoFilter(): void
    {
        $result = LocationFilterResult::noFilter();

        static::assertNull($result->getLocationIds());
        static::assertFalse($result->hasActiveFilter());
        static::assertFalse($result->isEmpty());
    }

    public function testLocationFilterResultEmptyResult(): void
    {
        $result = LocationFilterResult::emptyResult();

        static::assertSame([], $result->getLocationIds());
        static::assertTrue($result->hasActiveFilter());
        static::assertTrue($result->isEmpty());
    }

    public function testLocationFilterResultWithIds(): void
    {
        $result = new LocationFilterResult([1, 2, 3], true);

        static::assertSame([1, 2, 3], $result->getLocationIds());
        static::assertTrue($result->hasActiveFilter());
        static::assertFalse($result->isEmpty());
    }

    // =========================================================================
    // MemberFilterResult
    // =========================================================================

    public function testMemberFilterResultNoFilter(): void
    {
        $result = MemberFilterResult::noFilter();

        static::assertNull($result->getUserIds());
        static::assertFalse($result->hasActiveFilter());
        static::assertFalse($result->isEmpty());
    }

    public function testMemberFilterResultEmptyResult(): void
    {
        $result = MemberFilterResult::emptyResult();

        static::assertSame([], $result->getUserIds());
        static::assertTrue($result->hasActiveFilter());
        static::assertTrue($result->isEmpty());
    }

    public function testMemberFilterResultWithIds(): void
    {
        $result = new MemberFilterResult([10, 20], true);

        static::assertSame([10, 20], $result->getUserIds());
        static::assertTrue($result->hasActiveFilter());
        static::assertFalse($result->isEmpty());
    }
}
