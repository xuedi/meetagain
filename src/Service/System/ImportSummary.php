<?php declare(strict_types=1);

namespace App\Service\System;

readonly class ImportSummary
{
    /**
     * @param array<string, array{created: int, matched: int}> $itemsByType
     */
    public function __construct(
        public int $usersCreated,
        public int $usersSkipped,
        public int $locationsCreated,
        public int $eventsCreated,
        public int $cmsPagesCreated,
        public int $cmsPagesSkipped,
        public array $itemsByType = [],
        public int $itemSectionsSkipped = 0,
        public int $taxonomyAssignmentsDropped = 0,
    ) {}
}
