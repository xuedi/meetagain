<?php declare(strict_types=1);

namespace Module\Task\Internal;

use DateTimeImmutable;
use Module\Task\Contract\FinishedRun;
use Module\Task\Contract\RunLogInterface;
use Module\Task\Contract\Status;
use Module\Task\Internal\Entity\Run;
use Module\Task\Internal\Repository\RunRepository;
use Override;

readonly class RunLog implements RunLogInterface
{
    public function __construct(
        private RunRepository $runRepository,
    ) {}

    #[Override]
    public function find(int $id): ?FinishedRun
    {
        $run = $this->runRepository->find($id);

        return $run === null ? null : $this->toFinishedRun($run);
    }

    #[Override]
    public function list(int $limit = 100, ?string $identifier = null, ?Status $status = null): array
    {
        return $this->filtered($limit, null, $identifier, $status === null ? null : [$status]);
    }

    /**
     * @param list<Status>|null $statuses
     *
     * @return list<FinishedRun>
     */
    public function filtered(int $limit, ?DateTimeImmutable $since, ?string $identifier, ?array $statuses): array
    {
        return array_map($this->toFinishedRun(...), $this->runRepository->findFiltered($limit, $since, $identifier, $statuses));
    }

    /**
     * @param list<Status>|null $statuses
     */
    public function count(?DateTimeImmutable $since = null, ?string $identifier = null, ?array $statuses = null): int
    {
        return $this->runRepository->countFiltered($since, $identifier, $statuses);
    }

    /**
     * @return array<string, FinishedRun>
     */
    public function latestPerIdentifier(): array
    {
        return array_map($this->toFinishedRun(...), $this->runRepository->findLatestPerIdentifier());
    }

    private function toFinishedRun(Run $run): FinishedRun
    {
        return new FinishedRun(
            id: (int) $run->getId(),
            identifier: $run->getIdentifier(),
            startedAt: $run->getStartedAt(),
            durationMs: $run->getDurationMs(),
            status: $run->getStatus(),
            message: $run->getMessage(),
            host: $run->getHost(),
        );
    }
}
