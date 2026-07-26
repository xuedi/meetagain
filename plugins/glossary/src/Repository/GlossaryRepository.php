<?php declare(strict_types=1);

namespace Plugin\Glossary\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Glossary\Entity\Glossary;

/**
 * @extends ServiceEntityRepository<Glossary>
 */
class GlossaryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Glossary::class);
    }

    /**
     * @param int[]|null            $ids   null = no restriction, [] = block all
     * @param array<string, string> $order
     *
     * @return Glossary[]
     */
    public function findAllowed(?array $ids, array $order = ['phrase' => 'ASC'], bool $approvedOnly = false): array
    {
        if ($ids === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('g');
        if ($ids !== null) {
            $qb->andWhere('g.id IN (:ids)')->setParameter('ids', $ids);
        }
        if ($approvedOnly) {
            $qb->andWhere('g.approved = true');
        }
        foreach ($order as $field => $direction) {
            $qb->addOrderBy('g.' . $field, $direction);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[]|null $ids null = no restriction, [] = block all
     */
    public function findOneAllowed(int $id, ?array $ids, bool $approvedOnly = false): ?Glossary
    {
        if ($ids === []) {
            return null;
        }

        $qb = $this->createQueryBuilder('g')->andWhere('g.id = :id')->setParameter('id', $id);
        if ($ids !== null) {
            $qb->andWhere('g.id IN (:ids)')->setParameter('ids', $ids);
        }
        if ($approvedOnly) {
            $qb->andWhere('g.approved = true');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
