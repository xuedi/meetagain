<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security\Incident;

use App\Enum\IncidentSeverity;
use App\Service\Security\Incident\IncidentSeverityCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IncidentSeverityCalculatorTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int, int, IncidentSeverity}>
     */
    public static function severityCases(): iterable
    {
        yield 'all zero -> low'                   => [0, 0, 0, 0, IncidentSeverity::Low];
        yield 'four probing hits -> low'          => [4, 0, 0, 0, IncidentSeverity::Low];
        yield 'five probing hits -> medium'       => [5, 0, 0, 0, IncidentSeverity::Medium];
        yield 'three access-denied -> medium'     => [0, 3, 0, 0, IncidentSeverity::Medium];
        yield 'eight access-denied -> high'       => [0, 8, 0, 0, IncidentSeverity::High];
        yield 'one brute-force -> medium'         => [0, 0, 0, 1, IncidentSeverity::Medium];
        yield 'three brute-force -> high'         => [0, 0, 0, 3, IncidentSeverity::High];
        yield 'eight brute-force -> critical'     => [0, 0, 0, 8, IncidentSeverity::Critical];
        yield 'mixed sources cumulate'            => [10, 5, 5, 1, IncidentSeverity::High];
        yield 'mixed pushing critical'            => [10, 10, 10, 5, IncidentSeverity::Critical];
    }

    #[DataProvider('severityCases')]
    public function testCalculateSeverityFromCounters(
        int $probing,
        int $accessDenied,
        int $rateLimit,
        int $bruteForce,
        IncidentSeverity $expected,
    ): void {
        // Arrange
        $calculator = new IncidentSeverityCalculator();

        // Act
        $result = $calculator->calculate($probing, $accessDenied, $rateLimit, $bruteForce);

        // Assert
        static::assertSame($expected, $result);
    }
}
