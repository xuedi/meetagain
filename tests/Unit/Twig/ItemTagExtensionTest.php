<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Twig\ItemTagExtension;
use PHPUnit\Framework\TestCase;

class ItemTagExtensionTest extends TestCase
{
    public function testGetFunctionsReturnsTagFunctionsOnly(): void
    {
        // Arrange
        $subject = new ItemTagExtension();

        // Act
        $functionNames = array_map(static fn($f) => $f->getName(), $subject->getFunctions());

        // Assert
        static::assertSame(
            ['item_tag_labels', 'item_tag_choices', 'item_tag_levels', 'item_tag_pending'],
            $functionNames,
        );
    }
}
