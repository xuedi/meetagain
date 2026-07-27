<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security\Provider;

use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Repository\NotFoundLogRepository;
use App\Repository\SuspiciousUrlRepository;
use App\Service\Security\Provider\NotFoundProvider;
use App\Service\Security\SuspiciousUrlMatcher;
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
        $provider = $this->buildProvider();

        // Act
        $report = null;
        for ($i = 0; $i < 50; ++$i) {
            $report = $provider->observe(SecurityEventType::NotFound, Request::create('/api/foo'), [], 'sess', '1.2.3.4');
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
    }

    public function testProbingThirtyDistinctUrlsBlocks(): void
    {
        // Arrange
        $provider = $this->buildProvider();

        // Act
        $report = null;
        for ($i = 0; $i < 30; ++$i) {
            $report = $provider->observe(SecurityEventType::NotFound, Request::create('/random-path-' . $i), [], 'sess', '1.2.3.4');
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Block, $report->recommendation);
        static::assertGreaterThanOrEqual(100, $report->threatLevel);
    }

    public function testSuspiciousPatternBoostsThreatLevel(): void
    {
        // Arrange
        $provider = $this->buildProvider();

        // Act
        $reportSuspicious = $provider->observe(SecurityEventType::NotFound, Request::create('/.env'), [], 'sess-a', '1.1.1.1');
        $reportPlain = $provider->observe(SecurityEventType::NotFound, Request::create('/whatever'), [], 'sess-b', '2.2.2.2');

        // Assert
        static::assertGreaterThan($reportPlain->threatLevel, $reportSuspicious->threatLevel);
    }

    public function testAssetPathsAccumulateAtLowWeight(): void
    {
        // Arrange
        $provider = $this->buildProvider();

        // Act
        $report = null;
        for ($i = 0; $i < 40; ++$i) {
            $report = $provider->observe(SecurityEventType::NotFound, Request::create('/assets/app-staleHash' . $i . '.js'), [], 'sess', '1.2.3.4');
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
        static::assertLessThan(100, $report->threatLevel);
    }

    public function testThreeHundredAssetHitsBlocks(): void
    {
        // Arrange
        $provider = $this->buildProvider();

        // Act
        $report = null;
        for ($i = 0; $i < 300; ++$i) {
            $report = $provider->observe(SecurityEventType::NotFound, Request::create('/assets/scan-' . $i . '.js'), [], 'sess', '1.2.3.4');
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Block, $report->recommendation);
        static::assertGreaterThanOrEqual(100, $report->threatLevel);
    }

    public function testMixedProbeAndAssetHitsCombineWeight(): void
    {
        // Arrange
        $provider = $this->buildProvider();

        // Act
        $report = null;
        for ($i = 0; $i < 15; ++$i) {
            $report = $provider->observe(SecurityEventType::NotFound, Request::create('/probe-' . $i), [], 'sess', '1.2.3.4');
        }
        for ($i = 0; $i < 150; ++$i) {
            $report = $provider->observe(SecurityEventType::NotFound, Request::create('/assets/file-' . $i . '.js'), [], 'sess', '1.2.3.4');
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Block, $report->recommendation);
    }

    public function testFlaggedUrlCountsFiveTimesAPlainProbe(): void
    {
        // Arrange
        $provider = $this->buildProvider(['/backup.sql']);

        // Act
        $flagged = $provider->observe(SecurityEventType::NotFound, Request::create('/backup.sql'), [], 'sess-a', '1.1.1.1');
        $plain = null;
        for ($i = 0; $i < 5; ++$i) {
            $plain = $provider->observe(SecurityEventType::NotFound, Request::create('/plain-' . $i), [], 'sess-b', '2.2.2.2');
        }

        // Assert
        static::assertNotNull($plain);
        static::assertSame($plain->threatLevel, $flagged->threatLevel);
    }

    public function testSixFlaggedHitsBlock(): void
    {
        // Arrange
        $provider = $this->buildProvider(['/backup.sql']);

        // Act
        $report = null;
        for ($i = 0; $i < 6; ++$i) {
            $report = $provider->observe(SecurityEventType::NotFound, Request::create('/backup.sql'), [], 'sess', '1.2.3.4');
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Block, $report->recommendation);
        static::assertGreaterThanOrEqual(100, $report->threatLevel);
    }

    public function testFiveFlaggedHitsStayBelowBlock(): void
    {
        // Arrange
        $provider = $this->buildProvider(['/backup.sql']);

        // Act
        $report = null;
        for ($i = 0; $i < 5; ++$i) {
            $report = $provider->observe(SecurityEventType::NotFound, Request::create('/backup.sql'), [], 'sess', '1.2.3.4');
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
        static::assertLessThan(100, $report->threatLevel);
    }

    public function testUnflaggedProbesKeepTheirNormalWeight(): void
    {
        // Arrange
        $provider = $this->buildProvider(['/backup.sql']);

        // Act
        $report = null;
        for ($i = 0; $i < 29; ++$i) {
            $report = $provider->observe(SecurityEventType::NotFound, Request::create('/random-path-' . $i), [], 'sess', '1.2.3.4');
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
    }

    public function testFlaggingOverridesTheAssetLane(): void
    {
        // Arrange
        $provider = $this->buildProvider(['/assets/probe.js']);

        // Act
        $report = null;
        for ($i = 0; $i < 6; ++$i) {
            $report = $provider->observe(SecurityEventType::NotFound, Request::create('/assets/probe.js'), [], 'sess', '1.2.3.4');
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(SecurityRecommendation::Block, $report->recommendation);
    }

    public function testDoesNotHandleOtherEventTypes(): void
    {
        // Arrange
        $provider = $this->buildProvider();

        // Act + Assert
        static::assertFalse($provider->handles(SecurityEventType::RateLimit));
        static::assertFalse($provider->handles(SecurityEventType::AccessDenied));
        static::assertTrue($provider->handles(SecurityEventType::NotFound));
    }

    /**
     * @param list<string> $flaggedUrls
     */
    private function buildProvider(array $flaggedUrls = []): NotFoundProvider
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $logRepo = $this->createStub(NotFoundLogRepository::class);
        $suspiciousRepo = $this->createStub(SuspiciousUrlRepository::class);
        $suspiciousRepo->method('findAllUrls')->willReturn($flaggedUrls);

        return new NotFoundProvider(
            new ArrayAdapter(),
            new NullLogger(),
            $em,
            $logRepo,
            new SuspiciousUrlMatcher($suspiciousRepo),
        );
    }
}
