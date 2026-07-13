<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Filter;

use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Filter\GlossaryFilterInterface;
use Plugin\Glossary\Filter\GlossaryFilterService;

class GlossaryFilterServiceTest extends TestCase
{
    public function testReturnsNullWhenNoFiltersRegistered(): void
    {
        // Arrange
        $service = new GlossaryFilterService([]);

        // Act + Assert
        static::assertNull($service->getAllowedGlossaryIds());
    }

    public function testNullOpinionsAreIgnored(): void
    {
        // Arrange
        $service = new GlossaryFilterService([$this->filter(null), $this->filter([1, 2, 3])]);

        // Act + Assert
        static::assertSame([1, 2, 3], $service->getAllowedGlossaryIds());
    }

    public function testIntersectsMultipleOpinions(): void
    {
        // Arrange
        $service = new GlossaryFilterService([$this->filter([1, 2, 3]), $this->filter([2, 3, 4])]);

        // Act + Assert
        static::assertSame([2, 3], $service->getAllowedGlossaryIds());
    }

    public function testBlockAllWinsInIntersection(): void
    {
        // Arrange
        $service = new GlossaryFilterService([$this->filter([1, 2]), $this->filter([])]);

        // Act + Assert
        static::assertSame([], $service->getAllowedGlossaryIds());
    }

    /** @param int[]|null $ids */
    private function filter(?array $ids): GlossaryFilterInterface
    {
        return new class($ids) implements GlossaryFilterInterface {
            /** @param int[]|null $ids */
            public function __construct(private readonly ?array $ids) {}

            public function getAllowedGlossaryIds(): ?array
            {
                return $this->ids;
            }
        };
    }
}
