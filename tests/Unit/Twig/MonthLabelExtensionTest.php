<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Twig\MonthLabelExtension;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Loader\ArrayLoader;

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
        $label = $subject->monthLabel($this->twig('Europe/Berlin'), new DateTimeImmutable('2026-12-05 18:00:00'), $locale);

        // Assert
        static::assertSame($expected, $label);
    }

    public function testMonthLabelFollowsTheTwigTimezoneLikeTheDateFilter(): void
    {
        // Arrange
        $subject = new MonthLabelExtension();
        $date = new DateTimeImmutable('2026-11-30 23:30:00', new DateTimeZone('UTC'));

        // Act
        $label = $subject->monthLabel($this->twig('Europe/Berlin'), $date, 'en');

        // Assert
        static::assertSame('DEC', $label);
    }

    private function twig(string $timezone): Environment
    {
        $twig = new Environment(new ArrayLoader());
        $twig->getExtension(CoreExtension::class)->setTimezone($timezone);

        return $twig;
    }
}
