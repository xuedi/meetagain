<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Twig\MonthLabelExtension;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MonthLabelExtensionTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function localeProvider(): array
    {
        return [
            'english' => ['en', 'DEC'],
            'german' => ['de', 'DEZ'],
            'french' => ['fr', 'DÉC'],
            'spanish' => ['es', 'DIC'],
        ];
    }

    #[DataProvider('localeProvider')]
    public function testMonthLabelIsLocalizedUpperCaseWithoutTrailingDot(string $locale, string $expected): void
    {
        // Arrange
        $subject = new MonthLabelExtension();

        // Act
        $label = $subject->monthLabel(new DateTimeImmutable('2026-12-05 18:00:00'), $locale);

        // Assert
        static::assertSame($expected, $label);
    }
}
