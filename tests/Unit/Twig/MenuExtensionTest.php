<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Twig\MenuExtension;
use PHPUnit\Framework\TestCase;

class MenuExtensionTest extends TestCase
{
    public function testGetFunctionsReturnsGetMenuFunction(): void
    {
        // Arrange
        $subject = new MenuExtension();

        // Act
        $functions = $subject->getFunctions();

        // Assert
        static::assertCount(1, $functions);
        static::assertSame('get_menu', $functions[0]->getName());
    }
}
