<?php declare(strict_types=1);

namespace App\Portability;

use App\Entity\User;
use App\Filter\Portability\CleanImportFilterInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class CleanImport
{
    public const int BACKUP_MAX_MINUTES = 60;

    /**
     * @param iterable<CleanImportFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(CleanImportFilterInterface::class)]
        private iterable $filters,
        private ExportAction $exportAction,
        private Exporter $exporter,
        private InstanceScopeBuilder $instanceScope,
        private ClockInterface $clock,
    ) {}

    public function isOffered(): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter->allowsCleanImport()) {
                return false;
            }
        }

        return true;
    }

    public function hasFreshBackup(): bool
    {
        $archive = $this->exportAction->archive();
        if ($archive === null) {
            return false;
        }

        return $archive['writtenAt'] > $this->clock->now()->modify(sprintf('-%d minutes', self::BACKUP_MAX_MINUTES));
    }

    /**
     * @return array<string, int> rows per kind a clean import would delete
     */
    public function currentCounts(User $admin): array
    {
        $scope = $this->instanceScope->build();
        $counts = $this->exporter->preview($scope);
        if (isset($scope->users[(int) $admin->getId()], $counts['users'])) {
            --$counts['users'];
        }

        return $counts;
    }
}
