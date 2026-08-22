<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DefaultBodyLocaleCoverageTest extends TestCase
{
    private const array LOCALES = ['de', 'zh', 'fr', 'es'];

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function localeProvider(): iterable
    {
        foreach (self::LOCALES as $locale) {
            yield $locale => [$locale];
        }
    }

    #[DataProvider('localeProvider')]
    public function testEveryShippedBodyHasATranslationInEveryShippedLocale(string $locale): void
    {
        // Arrange
        $root = dirname(__DIR__, 4) . '/templates/email/defaults';
        $english = array_map('basename', glob($root . '/*.html') ?: []);

        // Act
        $missing = array_values(array_filter(
            $english,
            static fn(string $file): bool => !file_exists($root . '/' . $locale . '/' . $file),
        ));

        // Assert
        static::assertNotEmpty($english);
        static::assertSame([], $missing);
    }
}
