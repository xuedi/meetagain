<?php

declare(strict_types=1);

namespace App\Tests\Unit\Filter\Language;

use App\Filter\Language\LanguageFilterInterface;
use App\Filter\Language\LanguageFilterService;
use PHPUnit\Framework\TestCase;

class LanguageFilterServiceTest extends TestCase
{
    public function testGetLanguageCodeFilterWithNoFilters(): void
    {
        // Arrange
        $service = new LanguageFilterService([]);

        // Act
        $result = $service->getLanguageCodeFilter();

        // Assert - No active filters means no restriction
        $this->assertNull($result->getLanguageCodes());
        $this->assertFalse($result->hasActiveFilter());
    }

    public function testGetLanguageCodeFilterWithSingleFilter(): void
    {
        // Arrange
        $filter = $this->createStub(LanguageFilterInterface::class);
        $filter->method('getPriority')->willReturn(100);
        $filter->method('getLanguageCodeFilter')->willReturn(['en', 'de']);

        $service = new LanguageFilterService([$filter]);

        // Act
        $result = $service->getLanguageCodeFilter();

        // Assert
        $this->assertEquals(['en', 'de'], $result->getLanguageCodes());
        $this->assertTrue($result->hasActiveFilter());
    }

    public function testGetLanguageCodeFilterWithNullFilter(): void
    {
        // Arrange
        $filter = $this->createStub(LanguageFilterInterface::class);
        $filter->method('getPriority')->willReturn(100);
        $filter->method('getLanguageCodeFilter')->willReturn(null);

        $service = new LanguageFilterService([$filter]);

        // Act
        $result = $service->getLanguageCodeFilter();

        // Assert - null means no opinion, should return no filter
        $this->assertNull($result->getLanguageCodes());
        $this->assertFalse($result->hasActiveFilter());
    }

    public function testGetLanguageCodeFilterWithEmptyFilter(): void
    {
        // Arrange
        $filter = $this->createStub(LanguageFilterInterface::class);
        $filter->method('getPriority')->willReturn(100);
        $filter->method('getLanguageCodeFilter')->willReturn([]);

        $service = new LanguageFilterService([$filter]);

        // Act
        $result = $service->getLanguageCodeFilter();

        // Assert - Empty array means block all
        $this->assertTrue($result->isEmpty());
        $this->assertEquals([], $result->getLanguageCodes());
        $this->assertTrue($result->hasActiveFilter());
    }

    public function testGetLanguageCodeFilterWithMultipleFiltersIntersects(): void
    {
        // Arrange
        $filter1 = $this->createStub(LanguageFilterInterface::class);
        $filter1->method('getPriority')->willReturn(100);
        $filter1->method('getLanguageCodeFilter')->willReturn(['en', 'de', 'fr']);

        $filter2 = $this->createStub(LanguageFilterInterface::class);
        $filter2->method('getPriority')->willReturn(50);
        $filter2->method('getLanguageCodeFilter')->willReturn(['de', 'fr', 'es']);

        $service = new LanguageFilterService([$filter1, $filter2]);

        // Act
        $result = $service->getLanguageCodeFilter();

        // Assert - AND logic: only codes in both filters
        $this->assertEquals(['de', 'fr'], $result->getLanguageCodes());
        $this->assertTrue($result->hasActiveFilter());
    }

    public function testGetLanguageCodeFilterWithNoIntersectionReturnsEmpty(): void
    {
        // Arrange
        $filter1 = $this->createStub(LanguageFilterInterface::class);
        $filter1->method('getPriority')->willReturn(100);
        $filter1->method('getLanguageCodeFilter')->willReturn(['en', 'de']);

        $filter2 = $this->createStub(LanguageFilterInterface::class);
        $filter2->method('getPriority')->willReturn(50);
        $filter2->method('getLanguageCodeFilter')->willReturn(['fr', 'es']);

        $service = new LanguageFilterService([$filter1, $filter2]);

        // Act
        $result = $service->getLanguageCodeFilter();

        // Assert - No intersection means empty result
        $this->assertTrue($result->isEmpty());
        $this->assertEquals([], $result->getLanguageCodes());
    }

    public function testIsLanguageAccessibleWithNoFilters(): void
    {
        // Arrange
        $service = new LanguageFilterService([]);

        // Act
        $result = $service->isLanguageAccessible('en');

        // Assert - Default behavior allows access when no filters registered
        $this->assertTrue($result);
    }

    public function testIsLanguageAccessibleWithDenyingFilter(): void
    {
        // Arrange
        $filter = $this->createStub(LanguageFilterInterface::class);
        $filter->method('getPriority')->willReturn(100);
        $filter->method('isLanguageAccessible')->willReturn(false);

        $service = new LanguageFilterService([$filter]);

        // Act
        $result = $service->isLanguageAccessible('fr');

        // Assert
        $this->assertFalse($result);
    }

    public function testIsLanguageAccessibleWithAllowingFilter(): void
    {
        // Arrange
        $filter = $this->createStub(LanguageFilterInterface::class);
        $filter->method('getPriority')->willReturn(100);
        $filter->method('isLanguageAccessible')->willReturn(true);

        $service = new LanguageFilterService([$filter]);

        // Act
        $result = $service->isLanguageAccessible('en');

        // Assert
        $this->assertTrue($result);
    }

    public function testFilterPriorityOrdering(): void
    {
        // Arrange - High priority filter should be checked first
        $highPriorityFilter = $this->createMock(LanguageFilterInterface::class);
        $highPriorityFilter->method('getPriority')->willReturn(200);
        $highPriorityFilter->expects($this->once())->method('isLanguageAccessible')->with('de')->willReturn(false);

        $lowPriorityFilter = $this->createMock(LanguageFilterInterface::class);
        $lowPriorityFilter->method('getPriority')->willReturn(50);
        $lowPriorityFilter->expects($this->never())->method('isLanguageAccessible'); // Should not be called

        $service = new LanguageFilterService([$lowPriorityFilter, $highPriorityFilter]);

        // Act
        $result = $service->isLanguageAccessible('de');

        // Assert - Returns false without checking low priority filter
        $this->assertFalse($result);
    }
}
