<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Twig\ItemFacetExtension;
use PHPUnit\Framework\TestCase;

class ItemFacetExtensionTest extends TestCase
{
    public function testGetFunctionsReturnsFacetFunctionsOnly(): void
    {
        // Arrange
        $subject = new ItemFacetExtension();

        // Act
        $functionNames = array_map(static fn($f) => $f->getName(), $subject->getFunctions());

        // Assert
        static::assertSame(['item_facet_current', 'item_facet_url', 'item_facet_counts'], $functionNames);
    }
}
