<?php declare(strict_types=1);

namespace Module\Trust\Internal\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Module\Trust\Internal\Entity\TrustContextConfig;

/**
 * @extends ServiceEntityRepository<TrustContextConfig>
 */
class TrustContextConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrustContextConfig::class);
    }

    public function findByContext(string $context): ?TrustContextConfig
    {
        return $this->findOneBy(['context' => $context]);
    }

    /**
     * @return list<string>
     */
    public function findConfiguredContexts(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.context')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn(array $row): string => (string) $row['context'], $rows);
    }
}
