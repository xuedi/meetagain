<?php declare(strict_types=1);

namespace App\Service\Security\Incident;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

/**
 * Iterates each registered incident source in turn. Each source runs in its
 * own transaction; one failing source does not abort the others.
 */
final readonly class IncidentAggregator
{
    /**
     * @param iterable<IncidentSourceInterface> $sources
     */
    public function __construct(
        #[AutowireIterator(IncidentSourceInterface::class)]
        private iterable $sources,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return list<IncidentSourceStats>
     */
    public function aggregate(): array
    {
        $stats = [];
        foreach ($this->sources as $source) {
            $key = $source->getKey();
            $this->em->beginTransaction();
            try {
                $sourceStats = $source->ingest();
                $this->em->commit();
                $stats[] = $sourceStats;
            } catch (Throwable $e) {
                $this->em->rollback();
                $this->logger->error(
                    sprintf('IncidentAggregator source "%s" failed: %s', $key, $e->getMessage()),
                    ['exception' => $e],
                );
                $stats[] = IncidentSourceStats::empty($key, 0);
            }
        }

        return $stats;
    }
}
