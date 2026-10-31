<?php declare(strict_types=1);

namespace Module\Geo\Internal\Import;

final readonly class Report
{
    public function __construct(
        public Dataset $dataset,
        public int $created = 0,
        public int $updated = 0,
        public int $unchanged = 0,
        public int $skipped = 0,
        public int $rows = 0,
    ) {}
}
