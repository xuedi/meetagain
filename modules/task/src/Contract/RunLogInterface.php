<?php declare(strict_types=1);

namespace Module\Task\Contract;

interface RunLogInterface
{
    public function find(int $id): ?FinishedRun;

    /**
     * Newest first. A filter left null does not narrow the list.
     *
     * @return list<FinishedRun>
     */
    public function list(int $limit = 100, ?string $identifier = null, ?Status $status = null): array;
}
