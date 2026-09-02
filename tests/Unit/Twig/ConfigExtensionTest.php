<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Twig\ConfigExtension;
use PHPUnit\Framework\TestCase;

class ConfigExtensionTest extends TestCase
{
    public function testGetFunctionsReturnsExpectedFunctions(): void
    {
        // Arrange
        $subject = new ConfigExtension();

        // Act
        $functionNames = array_map(static fn($f) => $f->getName(), $subject->getFunctions());

        // Assert
        static::assertCount(5, $functionNames);
        static::assertContains('get_date_format', $functionNames);
        static::assertContains('get_date_format_flatpickr', $functionNames);
        static::assertContains('get_footer_column_title', $functionNames);
        static::assertContains('site_logo', $functionNames);
        static::assertContains('has_image_attributions', $functionNames);
    }
}
