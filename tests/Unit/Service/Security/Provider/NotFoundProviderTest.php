<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security\Provider;

use App\Entity\NotFoundLog;
use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Repository\NotFoundLogRepository;
use App\Repository\SuspiciousUrlRepository;
use App\Service\Security\Provider\NotFoundProvider;
use App\Service\Security\SuspiciousUrlMatcher;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
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

    public function testApiScanningAcrossManyPathsEscalatesButNeverBlocksAlone(): void
    {
        // Arrange
        $provider = $this->buildProvider();

        // Act
        $report = null;
        for ($i = 0; $i < 120; ++$i) {
            $report = $provider->observe(SecurityEventType::NotFound, Request::create('/api/item-' . $i), [], 'sess', '1.2.3.4');
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(80, $report->threatLevel);
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
    }

    public function testFewDistinctApiPathsScoreFarBelowAScan(): void
    {
        // Arrange
        $provider = $this->buildProvider();

        // Act
        $sameReport = null;
        $scanReport = null;
        for ($i = 0; $i < 10; ++$i) {
            $sameReport = $provider->observe(SecurityEventType::NotFound, Request::create('/api/same'), [], 'sess-a', '1.1.1.1');
            $scanReport = $provider->observe(SecurityEventType::NotFound, Request::create('/api/scan-' . $i), [], 'sess-b', '2.2.2.2');
        }

        // Assert
        static::assertNotNull($sameReport);
        static::assertNotNull($scanReport);
        static::assertSame(0, $sameReport->threatLevel);
        static::assertSame(10, $scanReport->threatLevel);
    }

    public function testWaveHistoryKeepsOnlyTheLastFiveWaves(): void
    {
        // Arrange
        $provider = $this->buildProvider();
        for ($i = 0; $i < 7; ++$i) {
            $provider->observe(SecurityEventType::NotFound, Request::create('/probe-' . $i), [], 'sess', '1.2.3.4');
        }

        // Act
        $report = null;
        for ($i = 0; $i < 6; ++$i) {
            $provider->observe(SecurityEventType::NotFound, Request::create('/api/x-' . $i), [], 'sess', '1.2.3.4');
            $report = $provider->observe(SecurityEventType::NotFound, Request::create('/again-' . $i), [], 'sess', '1.2.3.4');
        }

        // Assert
        static::assertNotNull($report);
        static::assertSame(5, $report->details['waveCount']);
    }

    public function testAFailingLogWriteStillReturnsTheReportAndWarns(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willThrowException(new RuntimeException('db down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('warning')->with(static::stringContains('db down'));
        $provider = $this->buildProvider(em: $em, logger: $logger);

        // Act
        $report = $provider->observe(SecurityEventType::NotFound, Request::create('/missing'), [], 'sess', '1.2.3.4');

        // Assert
        static::assertSame(1, $report->details['probeHits']);
    }

    public function testRetrospectiveScanCountsIpsPatternsAndFlaggedUrls(): void
    {
        // Arrange
        $rows = [
            $this->logRow('/.env', '1.1.1.1'),
            $this->logRow('/backup.sql', '2.2.2.2'),
            $this->logRow('/about', '1.1.1.1'),
            $this->logRow('/wp-admin/setup', ''),
        ];
        $provider = $this->buildProvider(['/backup.sql'], $rows);

        // Act
        $report = $provider->scanRetrospective(new DateTimeImmutable('-1 hour'), new DateTimeImmutable());

        // Assert
        static::assertSame(['rows' => 4, 'uniqueIps' => 2, 'patternHits' => 2, 'flaggedHits' => 1], $report->details);
        static::assertSame(0, $report->threatLevel);
        static::assertSame(SecurityRecommendation::Handled, $report->recommendation);
    }

    public function testRetrospectiveScanRisesWithFlaggedTrafficFromManyIps(): void
    {
        // Arrange
        $rows = [];
        for ($i = 0; $i < 200; ++$i) {
            $rows[] = $this->logRow('/backup.sql', '10.0.0.' . $i);
        }
        $provider = $this->buildProvider(['/backup.sql'], $rows);

        // Act
        $report = $provider->scanRetrospective(new DateTimeImmutable('-1 hour'), new DateTimeImmutable());

        // Assert
        static::assertSame(12, $report->threatLevel);
        static::assertSame(200, $report->details['flaggedHits']);
    }

    /**
     * @param list<string> $flaggedUrls
     * @param list<NotFoundLog> $rows
     */
    private function buildProvider(
        array $flaggedUrls = [],
        array $rows = [],
        ?EntityManagerInterface $em = null,
        ?LoggerInterface $logger = null,
    ): NotFoundProvider {
        $logRepo = $this->createStub(NotFoundLogRepository::class);
        $logRepo->method('findFiltered')->willReturn($rows);
        $suspiciousRepo = $this->createStub(SuspiciousUrlRepository::class);
        $suspiciousRepo->method('findAllUrls')->willReturn($flaggedUrls);

        return new NotFoundProvider(
            new ArrayAdapter(),
            $logger ?? new NullLogger(),
            $em ?? $this->createStub(EntityManagerInterface::class),
            $logRepo,
            new SuspiciousUrlMatcher($suspiciousRepo),
        );
    }

    private function logRow(string $url, string $ip): NotFoundLog
    {
        return new NotFoundLog()
            ->setUrl($url)
            ->setIp($ip)
            ->setCreatedAt(new DateTimeImmutable());
    }
}
