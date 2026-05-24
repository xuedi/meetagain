<?php declare(strict_types=1);

namespace Plugin\Ranking\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Ranking\Entity\RankingConfig;

/**
 * @extends ServiceEntityRepository<RankingConfig>
 */
class RankingConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RankingConfig::class);
    }

    public function save(RankingConfig $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RankingConfig $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByGroup(int $groupId): ?RankingConfig
    {
        return $this->findOneBy(['groupId' => $groupId]);
    }
}
