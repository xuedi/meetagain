<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Twig\LanguageExtension;
use PHPUnit\Framework\TestCase;

class LanguageExtensionTest extends TestCase
{
    public function testGetFunctionsReturnsExpectedFunctions(): void
    {
        // Arrange
        $subject = new LanguageExtension();

        // Act
        $functionNames = array_map(static fn($f) => $f->getName(), $subject->getFunctions());

        // Assert
        static::assertCount(7, $functionNames);
        static::assertContains('get_hreflang_code', $functionNames);
        static::assertContains('get_enabled_locales', $functionNames);
        static::assertContains('current_locale', $functionNames);
        static::assertContains('get_alternative_languages', $functionNames);
        static::assertContains('get_hreflang_languages', $functionNames);
        static::assertContains('get_admin_language_codes', $functionNames);
        static::assertContains('route_exists', $functionNames);
    }
}
