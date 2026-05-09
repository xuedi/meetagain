<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security\Incident;

use App\Enum\IncidentSeverity;
use App\Service\Security\Incident\IncidentSeverityCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IncidentSeverityCalculatorTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int, IncidentSeverity}>
     */
    public static function severityCases(): iterable
    {
        yield 'all zero -> low'                  => [0, 0, 0, IncidentSeverity::Low];
        yield 'four probing hits -> low'         => [4, 0, 0, IncidentSeverity::Low];
        yield 'five probing hits -> medium'      => [5, 0, 0, IncidentSeverity::Medium];
        yield 'three access-denied -> medium'    => [0, 3, 0, IncidentSeverity::Medium];
        yield 'eight access-denied -> high'      => [0, 8, 0, IncidentSeverity::High];
        yield 'three rate-limit -> medium'       => [0, 0, 3, IncidentSeverity::Medium];
        yield 'eight rate-limit -> high'         => [0, 0, 8, IncidentSeverity::High];
        yield 'twenty rate-limit -> critical'    => [0, 0, 20, IncidentSeverity::Critical];
        yield 'mixed sources cumulate'           => [10, 5, 5, IncidentSeverity::High];
        yield 'mixed pushing critical'           => [10, 10, 10, IncidentSeverity::Critical];
    }

    #[DataProvider('severityCases')]
    public function testCalculateSeverityFromCounters(
        int $probing,
        int $accessDenied,
        int $rateLimit,
        IncidentSeverity $expected,
    ): void {
        // Arrange
        $calculator = new IncidentSeverityCalculator();

        // Act
        $result = $calculator->calculate($probing, $accessDenied, $rateLimit);

        // Assert
        static::assertSame($expected, $result);
    }
}
