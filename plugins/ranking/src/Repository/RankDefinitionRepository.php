<?php declare(strict_types=1);

namespace Plugin\Ranking\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Ranking\Entity\RankDefinition;
use Plugin\Ranking\Entity\RankingConfig;

/**
 * @extends ServiceEntityRepository<RankDefinition>
 */
class RankDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RankDefinition::class);
    }

    public function save(RankDefinition $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RankDefinition $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<RankDefinition>
     */
    public function findByConfig(RankingConfig $config): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.config = :config')
            ->setParameter('config', $config)
            ->orderBy('d.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function deleteAllForConfig(RankingConfig $config): int
    {
        return $this->createQueryBuilder('d')
            ->delete()
            ->where('d.config = :config')
            ->setParameter('config', $config)
            ->getQuery()
            ->execute();
    }
}
