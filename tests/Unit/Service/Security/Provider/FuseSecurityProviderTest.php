<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security\Provider;

use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Service\Security\Provider\FuseSecurityProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

class FuseSecurityProviderTest extends TestCase
{
    public function testDoesNotTripBelowThreshold(): void
    {
        // Arrange
        $provider = new FuseSecurityProvider(new ArrayAdapter(), new NullLogger());

        // Act
        $report = $provider->observe(SecurityEventType::NotFound, new Request(), [], 'session-1', '1.2.3.4');

        // Assert
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
    }

    public function testTripsAtThresholdAndReturnsBlockShortCircuit(): void
    {
        // Arrange
        $provider = new FuseSecurityProvider(new ArrayAdapter(), new NullLogger());

        // Act
        $finalReport = null;
        for ($i = 0; $i < (FuseSecurityProvider::EVENTS_PER_IP_FUSE + 1); ++$i) {
            $finalReport = $provider->observe(
                SecurityEventType::NotFound,
                new Request(),
                [],
                'session-' . $i,
                '1.2.3.4',
            );
        }

        // Assert
        static::assertNotNull($finalReport);
        static::assertSame(SecurityRecommendation::BlockShortCircuit, $finalReport->recommendation);
        static::assertSame(100, $finalReport->threatLevel);
    }

    public function testStateIsKeyedByIpNotSession(): void
    {
        // Arrange
        $provider = new FuseSecurityProvider(new ArrayAdapter(), new NullLogger());

        // Act - cycle through many sessions on the same IP
        $finalReport = null;
        for ($i = 0; $i < (FuseSecurityProvider::EVENTS_PER_IP_FUSE + 1); ++$i) {
            $finalReport = $provider->observe(
                SecurityEventType::NotFound,
                new Request(),
                [],
                'rotating-session-' . $i,
                '5.6.7.8',
            );
        }

        // Assert
        static::assertNotNull($finalReport);
        static::assertSame(SecurityRecommendation::BlockShortCircuit, $finalReport->recommendation);
    }

    public function testReadOnlyDoesNotIncrement(): void
    {
        // Arrange
        $provider = new FuseSecurityProvider(new ArrayAdapter(), new NullLogger());
        for ($i = 0; $i < 5; ++$i) {
            $provider->observe(SecurityEventType::NotFound, new Request(), [], 'sess', '9.9.9.9');
        }

        // Act - read-only should return cached state without incrementing
        $report1 = $provider->observe(
            SecurityEventType::NotFound,
            new Request(),
            [],
            'sess',
            '9.9.9.9',
            readOnly: true,
        );
        $report2 = $provider->observe(
            SecurityEventType::NotFound,
            new Request(),
            [],
            'sess',
            '9.9.9.9',
            readOnly: true,
        );

        // Assert - threat level didn't grow despite multiple observe calls
        static::assertSame($report1->threatLevel, $report2->threatLevel);
    }

    public function testHandlesAllEventTypes(): void
    {
        // Arrange
        $provider = new FuseSecurityProvider(new ArrayAdapter(), new NullLogger());

        // Act + Assert
        static::assertTrue($provider->handles(SecurityEventType::NotFound));
        static::assertTrue($provider->handles(SecurityEventType::RateLimit));
        static::assertTrue($provider->handles(SecurityEventType::AccessDenied));
    }

    public function testPriorityIsHigherThanZero(): void
    {
        // Arrange
        $provider = new FuseSecurityProvider(new ArrayAdapter(), new NullLogger());

        // Act + Assert
        static::assertGreaterThan(0, $provider->getPriority());
    }
}
