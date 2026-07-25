<?php declare(strict_types=1);

namespace App\ValueObject;

use DateTimeImmutable;

final readonly class SeoDashboardSummary
{
    public function __construct(
        public int $descriptionsConfigured,
        public int $descriptionsTotal,
        public int $sitemapUrls,
        public int $sitemapWarnings,
        public ?DateTimeImmutable $indexNowLastSubmittedAt,
        public int $canonicalLanes,
        public int $canonicalBranchedLanes,
        public int $canonicalMarkers,
    ) {}
}
