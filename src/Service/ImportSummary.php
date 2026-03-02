<?php declare(strict_types=1);

namespace App\Service;

readonly class ImportSummary
{
    public function __construct(
        public int $usersCreated,
        public int $usersSkipped,
        public int $locationsCreated,
        public int $eventsCreated,
        public int $cmsPagesCreated,
        public int $cmsPagesSkipped,
    ) {}
}
