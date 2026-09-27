<?php declare(strict_types=1);

namespace Module\Email\Contract;

final readonly class QueueStats
{
    /**
     * @param int $stale pending messages queued more than an hour ago
     */
    public function __construct(
        public int $pending,
        public int $stale,
    ) {}
}
