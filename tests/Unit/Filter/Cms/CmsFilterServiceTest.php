<?php

declare(strict_types=1);

namespace App\Tests\Unit\Filter\Cms;

use App\Filter\Cms\CmsFilterInterface;
use App\Filter\Cms\CmsFilterService;
use PHPUnit\Framework\TestCase;

class CmsFilterServiceTest extends TestCase
{
    public function testIsCmsAccessibleWithNoFilters(): void
    {
        // Arrange
        $service = new CmsFilterService([]);

        // Act
        $result = $service->isCmsAccessible(123);

        // Assert - Default behavior allows access when no filters registered
        $this->assertTrue($result);
    }

    public function testIsCmsAccessibleWithAllowingFilter(): void
    {
        // Arrange
        $filter = $this->createStub(CmsFilterInterface::class);
        $filter->method('getPriority')->willReturn(100);
        $filter->method('isCmsAccessible')->willReturn(true);

        $service = new CmsFilterService([$filter]);

        // Act
        $result = $service->isCmsAccessible(456);

        // Assert
        $this->assertTrue($result);
    }

    public function testIsCmsAccessibleWithDenyingFilter(): void
    {
        // Arrange
        $filter = $this->createStub(CmsFilterInterface::class);
        $filter->method('getPriority')->willReturn(100);
        $filter->method('isCmsAccessible')->willReturn(false);

        $service = new CmsFilterService([$filter]);

        // Act
        $result = $service->isCmsAccessible(456);

        // Assert
        $this->assertFalse($result);
    }

    public function testIsCmsAccessibleWithMultipleFilters(): void
    {
        // Arrange
        $filter1 = $this->createStub(CmsFilterInterface::class);
        $filter1->method('getPriority')->willReturn(100);
        $filter1->method('isCmsAccessible')->willReturn(true);

        $filter2 = $this->createStub(CmsFilterInterface::class);
        $filter2->method('getPriority')->willReturn(50);
        $filter2->method('isCmsAccessible')->willReturn(true);

        $service = new CmsFilterService([$filter1, $filter2]);

        // Act
        $result = $service->isCmsAccessible(456);

        // Assert
        $this->assertTrue($result);
    }

    public function testIsCmsAccessibleStopsOnFirstDeny(): void
    {
        // Arrange
        $filter1 = $this->createStub(CmsFilterInterface::class);
        $filter1->method('getPriority')->willReturn(100);
        $filter1->method('isCmsAccessible')->willReturn(false);

        $filter2 = $this->createStub(CmsFilterInterface::class);
        $filter2->method('getPriority')->willReturn(50);
        $filter2->method('isCmsAccessible')->willReturn(true);

        $service = new CmsFilterService([$filter1, $filter2]);

        // Act
        $result = $service->isCmsAccessible(456);

        // Assert
        $this->assertFalse($result);
    }

    public function testIsCmsAccessibleChecksFiltersInPriorityOrder(): void
    {
        // Arrange - High priority filter denies early
        $highPriorityFilter = $this->createMock(CmsFilterInterface::class);
        $highPriorityFilter->method('getPriority')->willReturn(200);
        $highPriorityFilter->expects($this->once())->method('isCmsAccessible')->with(789)->willReturn(false);

        $lowPriorityFilter = $this->createMock(CmsFilterInterface::class);
        $lowPriorityFilter->method('getPriority')->willReturn(50);
        $lowPriorityFilter->expects($this->never())->method('isCmsAccessible'); // Should not be called

        $service = new CmsFilterService([$lowPriorityFilter, $highPriorityFilter]);

        // Act
        $result = $service->isCmsAccessible(789);

        // Assert - Returns false without checking low priority filter
        $this->assertFalse($result);
    }
}
