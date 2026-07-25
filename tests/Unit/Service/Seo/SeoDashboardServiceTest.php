<?php declare(strict_types=1);

namespace Tests\Unit\Service\Seo;

use App\Repository\EventCanonicalRootRepository;
use App\Service\Config\ConfigService;
use App\Service\Seo\EventCanonicalOverviewService;
use App\Service\Seo\IndexNowService;
use App\Service\Seo\SeoDashboardService;
use App\Service\Seo\SitemapOverviewService;
use App\ValueObject\CanonicalLane;
use App\ValueObject\SitemapRow;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SeoDashboardServiceTest extends TestCase
{
    public function testCountersReflectTheUnderlyingServices(): void
    {
        // Arrange
        $submittedAt = new DateTimeImmutable('2026-07-01 10:00:00');
        $service = $this->makeService(
            descriptions: ['default' => 'set', 'events' => '  ', 'members' => 'set'],
            rows: [$this->makeRow([]), $this->makeRow(['admin_seo_sitemap.warning_missing_title'])],
            lanes: [$this->makeLane(1), $this->makeLane(3)],
            markers: 12,
            lastSubmittedAt: $submittedAt,
        );

        // Act
        $summary = $service->getSummary();

        // Assert
        self::assertSame(2, $summary->descriptionsConfigured);
        self::assertSame(3, $summary->descriptionsTotal);
        self::assertSame(2, $summary->sitemapUrls);
        self::assertSame(1, $summary->sitemapWarnings);
        self::assertSame($submittedAt, $summary->indexNowLastSubmittedAt);
        self::assertSame(2, $summary->canonicalLanes);
        self::assertSame(1, $summary->canonicalBranchedLanes);
        self::assertSame(12, $summary->canonicalMarkers);
    }

    public function testNeverSubmittedLeavesTheIndexNowTimestampNull(): void
    {
        // Arrange
        $service = $this->makeService(
            descriptions: ['default' => '', 'events' => '', 'members' => ''],
            rows: [],
            lanes: [],
            markers: 0,
            lastSubmittedAt: null,
        );

        // Act
        $summary = $service->getSummary();

        // Assert
        self::assertNull($summary->indexNowLastSubmittedAt);
        self::assertSame(0, $summary->descriptionsConfigured);
    }

    /**
     * @param array<string, string> $descriptions
     * @param list<SitemapRow> $rows
     * @param list<CanonicalLane> $lanes
     */
    private function makeService(array $descriptions, array $rows, array $lanes, int $markers, ?DateTimeImmutable $lastSubmittedAt): SeoDashboardService
    {
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getSeoDescription')->willReturnCallback(
            static fn(string $context): string => $descriptions[$context] ?? '',
        );

        $sitemapOverview = $this->createStub(SitemapOverviewService::class);
        $sitemapOverview->method('getRows')->willReturn($rows);
        $sitemapOverview->method('countWarnings')->willReturn(count(array_filter($rows, static fn(SitemapRow $row) => $row->hasWarnings())));

        $indexNow = $this->createStub(IndexNowService::class);
        $indexNow->method('getLastSubmittedAt')->willReturn($lastSubmittedAt);

        $canonicalOverview = $this->createStub(EventCanonicalOverviewService::class);
        $canonicalOverview->method('getLanes')->willReturn($lanes);

        $rootRepository = $this->createStub(EventCanonicalRootRepository::class);
        $rootRepository->method('count')->willReturn($markers);

        return new SeoDashboardService($configService, $sitemapOverview, $indexNow, $canonicalOverview, $rootRepository);
    }

    /**
     * @param list<string> $warnings
     */
    private function makeRow(array $warnings): SitemapRow
    {
        return new SitemapRow('static', 'Home', 'https://example.org/', 'en', '2026-07-01', $warnings);
    }

    private function makeLane(int $rootCount): CanonicalLane
    {
        return new CanonicalLane(1, 'Tuesday Meetup', 'en', [], $rootCount);
    }
}
