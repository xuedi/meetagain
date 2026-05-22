<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security\Provider;

use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Repository\NotFoundLogRepository;
use App\Service\Security\Provider\NotFoundProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

class NotFoundProviderTest extends TestCase
{
    public function testApiHammeringIsLenient(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(NotFoundLogRepository::class);
        $provider = new NotFoundProvider(new ArrayAdapter(), new NullLogger(), $em, $repo);

        // Act - hammer the same /api/foo path many times
        $report = null;
        for ($i = 0; $i < 50; ++$i) {
            $report = $provider->observe(
                SecurityEventType::NotFound,
                Request::create('/api/foo'),
                [],
                'sess',
                '1.2.3.4',
            );
        }

        // Assert - lenient: never blocks
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
    }

    public function testProbingThirtyDistinctUrlsBlocks(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(NotFoundLogRepository::class);
        $provider = new NotFoundProvider(new ArrayAdapter(), new NullLogger(), $em, $repo);

        // Act - 30 distinct probe URLs (no api/ prefix)
        $report = null;
        for ($i = 0; $i < 30; ++$i) {
            $report = $provider->observe(
                SecurityEventType::NotFound,
                Request::create('/random-path-' . $i),
                [],
                'sess',
                '1.2.3.4',
            );
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Block, $report->recommendation);
        static::assertGreaterThanOrEqual(100, $report->threatLevel);
    }

    public function testSuspiciousPatternBoostsThreatLevel(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(NotFoundLogRepository::class);
        $provider = new NotFoundProvider(new ArrayAdapter(), new NullLogger(), $em, $repo);

        // Act - probing /.env adds the suspicious-pattern boost
        $reportSuspicious = $provider->observe(
            SecurityEventType::NotFound,
            Request::create('/.env'),
            [],
            'sess-a',
            '1.1.1.1',
        );
        $reportPlain = $provider->observe(
            SecurityEventType::NotFound,
            Request::create('/whatever'),
            [],
            'sess-b',
            '2.2.2.2',
        );

        // Assert
        static::assertGreaterThan($reportPlain->threatLevel, $reportSuspicious->threatLevel);
    }

    public function testAssetPathsAccumulateAtLowWeight(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(NotFoundLogRepository::class);
        $provider = new NotFoundProvider(new ArrayAdapter(), new NullLogger(), $em, $repo);

        // Act - 40 distinct asset 404s (stale browser cache after a redeploy)
        $report = null;
        for ($i = 0; $i < 40; ++$i) {
            $report = $provider->observe(
                SecurityEventType::NotFound,
                Request::create('/assets/app-staleHash' . $i . '.js'),
                [],
                'sess',
                '1.2.3.4',
            );
        }

        // Assert - 40 asset 404s = 40/300 * 100 ≈ 13 threat, well below block threshold
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
        static::assertLessThan(100, $report->threatLevel);
    }

    public function testThreeHundredAssetHitsBlocks(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(NotFoundLogRepository::class);
        $provider = new NotFoundProvider(new ArrayAdapter(), new NullLogger(), $em, $repo);

        // Act - scanner hammering /assets/* at scale
        $report = null;
        for ($i = 0; $i < 300; ++$i) {
            $report = $provider->observe(
                SecurityEventType::NotFound,
                Request::create('/assets/scan-' . $i . '.js'),
                [],
                'sess',
                '1.2.3.4',
            );
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Block, $report->recommendation);
        static::assertGreaterThanOrEqual(100, $report->threatLevel);
    }

    public function testMixedProbeAndAssetHitsCombineWeight(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(NotFoundLogRepository::class);
        $provider = new NotFoundProvider(new ArrayAdapter(), new NullLogger(), $em, $repo);

        // Act - 15 regular probes (50 threat) + 150 asset 404s (50 threat) = 100, blocks
        $report = null;
        for ($i = 0; $i < 15; ++$i) {
            $report = $provider->observe(
                SecurityEventType::NotFound,
                Request::create('/probe-' . $i),
                [],
                'sess',
                '1.2.3.4',
            );
        }
        for ($i = 0; $i < 150; ++$i) {
            $report = $provider->observe(
                SecurityEventType::NotFound,
                Request::create('/assets/file-' . $i . '.js'),
                [],
                'sess',
                '1.2.3.4',
            );
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Block, $report->recommendation);
    }

    public function testDoesNotHandleOtherEventTypes(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(NotFoundLogRepository::class);
        $provider = new NotFoundProvider(new ArrayAdapter(), new NullLogger(), $em, $repo);

        // Act + Assert
        static::assertFalse($provider->handles(SecurityEventType::RateLimit));
        static::assertFalse($provider->handles(SecurityEventType::AccessDenied));
        static::assertTrue($provider->handles(SecurityEventType::NotFound));
    }
}
