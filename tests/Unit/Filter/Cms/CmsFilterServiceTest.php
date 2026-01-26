<?php declare(strict_types=1);

namespace App\Tests\Unit\Filter\Cms;

use App\Filter\Cms\CmsFilterInterface;
use App\Filter\Cms\CmsFilterResult;
use App\Filter\Cms\CmsFilterService;
use PHPUnit\Framework\TestCase;

class CmsFilterServiceTest extends TestCase
{
    public function testGetCmsIdFilterReturnsNoFilterWhenNoFiltersRegistered(): void
    {
        // Arrange - No filters registered
        $service = new CmsFilterService([]);

        // Act
        $result = $service->getCmsIdFilter();

        // Assert - Returns noFilter result
        $this->assertInstanceOf(CmsFilterResult::class, $result);
        $this->assertFalse($result->hasActiveFilter());
        $this->assertNull($result->getCmsIds());
    }

    public function testGetCmsIdFilterReturnsSingleFilterResult(): void
    {
        // Arrange - Single filter returning IDs
        $filter = $this->createMock(CmsFilterInterface::class);
        $filter->method('getPriority')->willReturn(100);
        $filter->method('getCmsIdFilter')->willReturn([10, 20, 30]);

        $service = new CmsFilterService([$filter]);

        // Act
        $result = $service->getCmsIdFilter();

        // Assert - Returns filter's CMS IDs
        $this->assertTrue($result->hasActiveFilter());
        $this->assertEquals([10, 20, 30], $result->getCmsIds());
    }

    public function testGetCmsIdFilterIgnoresFiltersReturningNull(): void
    {
        // Arrange - Two filters, first returns null (no opinion)
        $filter1 = $this->createMock(CmsFilterInterface::class);
        $filter1->method('getPriority')->willReturn(200);
        $filter1->method('getCmsIdFilter')->willReturn(null);

        $filter2 = $this->createMock(CmsFilterInterface::class);
        $filter2->method('getPriority')->willReturn(100);
        $filter2->method('getCmsIdFilter')->willReturn([5, 10, 15]);

        $service = new CmsFilterService([$filter1, $filter2]);

        // Act
        $result = $service->getCmsIdFilter();

        // Assert - Returns second filter's result
        $this->assertTrue($result->hasActiveFilter());
        $this->assertEquals([5, 10, 15], $result->getCmsIds());
    }

    public function testGetCmsIdFilterUsesIntersectionForMultipleFilters(): void
    {
        // Arrange - Two filters with overlapping IDs
        $filter1 = $this->createMock(CmsFilterInterface::class);
        $filter1->method('getPriority')->willReturn(200);
        $filter1->method('getCmsIdFilter')->willReturn([10, 20, 30, 40]);

        $filter2 = $this->createMock(CmsFilterInterface::class);
        $filter2->method('getPriority')->willReturn(100);
        $filter2->method('getCmsIdFilter')->willReturn([20, 30, 50]);

        $service = new CmsFilterService([$filter1, $filter2]);

        // Act
        $result = $service->getCmsIdFilter();

        // Assert - Returns intersection (AND logic)
        $this->assertTrue($result->hasActiveFilter());
        $this->assertEquals([20, 30], $result->getCmsIds());
    }

    public function testGetCmsIdFilterReturnsEmptyResultWhenNoIntersection(): void
    {
        // Arrange - Two filters with no overlap
        $filter1 = $this->createMock(CmsFilterInterface::class);
        $filter1->method('getPriority')->willReturn(200);
        $filter1->method('getCmsIdFilter')->willReturn([10, 20]);

        $filter2 = $this->createMock(CmsFilterInterface::class);
        $filter2->method('getPriority')->willReturn(100);
        $filter2->method('getCmsIdFilter')->willReturn([30, 40]);

        $service = new CmsFilterService([$filter1, $filter2]);

        // Act
        $result = $service->getCmsIdFilter();

        // Assert - Returns empty result
        $this->assertTrue($result->hasActiveFilter());
        $this->assertTrue($result->isEmpty());
        $this->assertEquals([], $result->getCmsIds());
    }

    public function testGetCmsIdFilterReturnsEmptyWhenAnyFilterReturnsEmpty(): void
    {
        // Arrange - One filter returns empty array
        $filter1 = $this->createMock(CmsFilterInterface::class);
        $filter1->method('getPriority')->willReturn(200);
        $filter1->method('getCmsIdFilter')->willReturn([10, 20, 30]);

        $filter2 = $this->createMock(CmsFilterInterface::class);
        $filter2->method('getPriority')->willReturn(100);
        $filter2->method('getCmsIdFilter')->willReturn([]);

        $service = new CmsFilterService([$filter1, $filter2]);

        // Act
        $result = $service->getCmsIdFilter();

        // Assert - Returns empty result (AND logic)
        $this->assertTrue($result->hasActiveFilter());
        $this->assertTrue($result->isEmpty());
    }

    public function testGetCmsIdFilterSortsFiltersByPriority(): void
    {
        // Arrange - Filters registered in wrong order, lower priority first
        $lowPriorityFilter = $this->createMock(CmsFilterInterface::class);
        $lowPriorityFilter->method('getPriority')->willReturn(50);
        $lowPriorityFilter->method('getCmsIdFilter')->willReturn([10, 20, 30]);

        $highPriorityFilter = $this->createMock(CmsFilterInterface::class);
        $highPriorityFilter->method('getPriority')->willReturn(100);
        $highPriorityFilter->method('getCmsIdFilter')->willReturn([10, 20]);

        // Register low priority first
        $service = new CmsFilterService([$lowPriorityFilter, $highPriorityFilter]);

        // Act
        $result = $service->getCmsIdFilter();

        // Assert - High priority filter applied first, then intersection
        $this->assertEquals([10, 20], $result->getCmsIds());
    }

    public function testIsCmsAccessibleReturnsTrueWhenNoFilters(): void
    {
        // Arrange - No filters registered
        $service = new CmsFilterService([]);

        // Act
        $result = $service->isCmsAccessible(123);

        // Assert - All CMS pages accessible
        $this->assertTrue($result);
    }

    public function testIsCmsAccessibleReturnsTrueWhenAllFiltersAllowOrHaveNoOpinion(): void
    {
        // Arrange - Multiple filters, all return true or null
        $filter1 = $this->createMock(CmsFilterInterface::class);
        $filter1->method('getPriority')->willReturn(200);
        $filter1->method('isCmsAccessible')->with(123)->willReturn(true);

        $filter2 = $this->createMock(CmsFilterInterface::class);
        $filter2->method('getPriority')->willReturn(100);
        $filter2->method('isCmsAccessible')->with(123)->willReturn(null);

        $service = new CmsFilterService([$filter1, $filter2]);

        // Act
        $result = $service->isCmsAccessible(123);

        // Assert - CMS page is accessible
        $this->assertTrue($result);
    }

    public function testIsCmsAccessibleReturnsFalseWhenAnyFilterDenies(): void
    {
        // Arrange - One filter explicitly denies access
        $filter1 = $this->createMock(CmsFilterInterface::class);
        $filter1->method('getPriority')->willReturn(200);
        $filter1->method('isCmsAccessible')->with(456)->willReturn(true);

        $filter2 = $this->createMock(CmsFilterInterface::class);
        $filter2->method('getPriority')->willReturn(100);
        $filter2->method('isCmsAccessible')->with(456)->willReturn(false);

        $service = new CmsFilterService([$filter1, $filter2]);

        // Act
        $result = $service->isCmsAccessible(456);

        // Assert - CMS page is not accessible (ANY filter can deny)
        $this->assertFalse($result);
    }

    public function testIsCmsAccessibleChecksFiltersInPriorityOrder(): void
    {
        // Arrange - High priority filter denies early
        $highPriorityFilter = $this->createMock(CmsFilterInterface::class);
        $highPriorityFilter->method('getPriority')->willReturn(200);
        $highPriorityFilter->expects($this->once())
            ->method('isCmsAccessible')
            ->with(789)
            ->willReturn(false);

        $lowPriorityFilter = $this->createMock(CmsFilterInterface::class);
        $lowPriorityFilter->method('getPriority')->willReturn(50);
        $lowPriorityFilter->expects($this->never()) // Should not be called
            ->method('isCmsAccessible');

        $service = new CmsFilterService([$lowPriorityFilter, $highPriorityFilter]);

        // Act
        $result = $service->isCmsAccessible(789);

        // Assert - Returns false without checking low priority filter
        $this->assertFalse($result);
    }
}
