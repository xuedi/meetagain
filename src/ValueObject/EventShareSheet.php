<?php declare(strict_types=1);

namespace App\ValueObject;

final readonly class EventShareSheet
{
    /**
     * @param list<ShareLink> $shareTargets
     * @param list<ShareLink> $mapLinks
     */
    public function __construct(
        public string $title,
        public string $teaser,
        public string $shareUrl,
        public array $shareTargets,
        public array $mapLinks,
        public string $qrSvg,
        public string $qrPngUrl,
    ) {}
}
