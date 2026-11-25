<?php declare(strict_types=1);

namespace Plugin\Glossary\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Glossary\Entity\Glossary;
use SortDirection;

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
     * @param array<string, SortDirection> $order
     *
     * @return Glossary[]
     */
    public function findAllowed(?array $ids, array $order = ['phrase' => SortDirection::Ascending]): array
    {
        if ($ids === []) {
            return [];
        }

        $qb = $this->withDefinitions($ids);
        foreach ($order as $field => $direction) {
            $qb->addOrderBy('g.' . $field, $direction);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[]|null $ids null = no restriction, [] = block all
     */
    public function findOneAllowed(int $id, ?array $ids): ?Glossary
    {
        if ($ids === []) {
            return null;
        }

        return $this->withDefinitions($ids)->andWhere('g.id = :id')->setParameter('id', $id)->getQuery()->getOneOrNullResult();
    }

    /**
     * @param int[]|null $ids null = no restriction, [] = block all
     *
     * @return list<int>
     */
    public function findAllowedIds(?array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('g')->select('g.id')->orderBy('g.id', SortDirection::Ascending);
        if ($ids !== null) {
            $qb->andWhere('g.id IN (:ids)')->setParameter('ids', $ids);
        }

        return array_map(intval(...), $qb->getQuery()->getSingleColumnResult());
    }

    /** @param int[]|null $ids */
    private function withDefinitions(?array $ids): QueryBuilder
    {
        $qb = $this->createQueryBuilder('g')->leftJoin('g.definitions', 'd')->addSelect('d');
        if ($ids !== null) {
            $qb->andWhere('g.id IN (:ids)')->setParameter('ids', $ids);
        }

        return $qb;
    }
}
