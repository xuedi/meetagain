<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Filter;

use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Filter\FilmGroupFilterInterface;
use Plugin\Filmclub\Filter\FilmGroupFilterService;

class FilmGroupFilterServiceTest extends TestCase
{
    // --- getAllowedFilmIds ---

    public function testReturnsNullWhenNoImplementationsExist(): void
    {
        // Arrange
        $service = new FilmGroupFilterService([]);

        // Act
        $result = $service->getAllowedFilmIds();

        // Assert
        static::assertNull($result);
    }

    public function testReturnsNullWhenSingleImplementationReturnsNull(): void
    {
        // Arrange
        $filter = $this->createStub(FilmGroupFilterInterface::class);
        $filter->method('getAllowedFilmIds')->willReturn(null);
        $service = new FilmGroupFilterService([$filter]);

        // Act
        $result = $service->getAllowedFilmIds();

        // Assert
        static::assertNull($result);
    }

    public function testReturnsEmptyArrayWhenSingleImplementationBlocksAll(): void
    {
        // Arrange
        $filter = $this->createStub(FilmGroupFilterInterface::class);
        $filter->method('getAllowedFilmIds')->willReturn([]);
        $service = new FilmGroupFilterService([$filter]);

        // Act
        $result = $service->getAllowedFilmIds();

        // Assert
        static::assertSame([], $result);
    }

    public function testReturnsSingleImplementationAllowlist(): void
    {
        // Arrange
        $filter = $this->createStub(FilmGroupFilterInterface::class);
        $filter->method('getAllowedFilmIds')->willReturn([1, 2, 3]);
        $service = new FilmGroupFilterService([$filter]);

        // Act
        $result = $service->getAllowedFilmIds();

        // Assert
        static::assertSame([1, 2, 3], $result);
    }

    public function testIntersectsTwoNonNullResults(): void
    {
        // Arrange
        $filterA = $this->createStub(FilmGroupFilterInterface::class);
        $filterA->method('getAllowedFilmIds')->willReturn([1, 2, 3]);

        $filterB = $this->createStub(FilmGroupFilterInterface::class);
        $filterB->method('getAllowedFilmIds')->willReturn([2, 3, 4]);

        $service = new FilmGroupFilterService([$filterA, $filterB]);

        // Act
        $result = $service->getAllowedFilmIds();

        // Assert
        static::assertEqualsCanonicalizing([2, 3], $result);
    }

    public function testIntersectionWithBlockAllGivesBlockAll(): void
    {
        // Arrange
        $filterA = $this->createStub(FilmGroupFilterInterface::class);
        $filterA->method('getAllowedFilmIds')->willReturn([1, 2]);

        $filterB = $this->createStub(FilmGroupFilterInterface::class);
        $filterB->method('getAllowedFilmIds')->willReturn([]);

        $service = new FilmGroupFilterService([$filterA, $filterB]);

        // Act
        $result = $service->getAllowedFilmIds();

        // Assert
        static::assertSame([], $result);
    }

    public function testNullOpinionIsIgnoredInFavourOfNonNullResult(): void
    {
        // Arrange
        $filterNull = $this->createStub(FilmGroupFilterInterface::class);
        $filterNull->method('getAllowedFilmIds')->willReturn(null);

        $filterOpinionated = $this->createStub(FilmGroupFilterInterface::class);
        $filterOpinionated->method('getAllowedFilmIds')->willReturn([1, 2]);

        $service = new FilmGroupFilterService([$filterNull, $filterOpinionated]);

        // Act
        $result = $service->getAllowedFilmIds();

        // Assert
        static::assertSame([1, 2], $result);
    }

    // --- smoke tests for the remaining six methods ---

    public function testAllMethodsReturnNullWhenNoImplementations(): void
    {
        // Arrange
        $service = new FilmGroupFilterService([]);

        // Act + Assert
        static::assertNull($service->getAllowedEventIds());
        static::assertNull($service->getAllowedPollIds());
        static::assertNull($service->getAllowedNoteIds());
        static::assertNull($service->getAllowedWishlistEntryIds());
    }

    public function testAllMethodsForwardToImplementation(): void
    {
        // Arrange
        $filter = $this->createStub(FilmGroupFilterInterface::class);
        $filter->method('getAllowedEventIds')->willReturn([20]);
        $filter->method('getAllowedPollIds')->willReturn([30]);
        $filter->method('getAllowedNoteIds')->willReturn([40]);
        $filter->method('getAllowedWishlistEntryIds')->willReturn([50]);

        $service = new FilmGroupFilterService([$filter]);

        // Act + Assert
        static::assertSame([20], $service->getAllowedEventIds());
        static::assertSame([30], $service->getAllowedPollIds());
        static::assertSame([40], $service->getAllowedNoteIds());
        static::assertSame([50], $service->getAllowedWishlistEntryIds());
    }
}
