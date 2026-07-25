<?php declare(strict_types=1);

namespace App\Service\Seo;

use App\Repository\EventCanonicalRootRepository;
use App\Service\Config\ConfigService;
use App\ValueObject\CanonicalLane;
use App\ValueObject\SeoDashboardSummary;

readonly class SeoDashboardService
{
    private const array DESCRIPTION_CONTEXTS = ['default', 'events', 'members'];

    public function __construct(
        private ConfigService $configService,
        private SitemapOverviewService $sitemapOverviewService,
        private IndexNowService $indexNowService,
        private EventCanonicalOverviewService $canonicalOverviewService,
        private EventCanonicalRootRepository $canonicalRootRepository,
    ) {}

    /**
     * @param array<int>|null $eventIds restricts the canonical numbers to these events
     */
    public function getSummary(?array $eventIds = null): SeoDashboardSummary
    {
        $rows = $this->sitemapOverviewService->getRows();
        $lanes = $this->canonicalOverviewService->getLanes(eventIds: $eventIds);

        return new SeoDashboardSummary(
            descriptionsConfigured: $this->countConfiguredDescriptions(),
            descriptionsTotal: count(self::DESCRIPTION_CONTEXTS),
            sitemapUrls: count($rows),
            sitemapWarnings: $this->sitemapOverviewService->countWarnings($rows),
            indexNowLastSubmittedAt: $this->indexNowService->getLastSubmittedAt(),
            canonicalLanes: count($lanes),
            canonicalBranchedLanes: count(array_filter($lanes, static fn(CanonicalLane $lane) => $lane->isBranched())),
            canonicalMarkers: $this->canonicalRootRepository->count([]),
        );
    }

    private function countConfiguredDescriptions(): int
    {
        $configured = 0;
        foreach (self::DESCRIPTION_CONTEXTS as $context) {
            if (trim($this->configService->getSeoDescription($context)) === '') {
                continue;
            }

            ++$configured;
        }

        return $configured;
    }
}
