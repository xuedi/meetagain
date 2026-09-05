<?php declare(strict_types=1);

namespace Plugin\Photos\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Photos\Entity\Photo;

/**
 * @extends ServiceEntityRepository<Photo>
 */
class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    public function save(Photo $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Photo $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param list<int>|null $allowedIds null: no restriction; []: block all
     *
     * @return list<Photo>
     */
    public function findAll(?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.takenAt', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC');

        if ($allowedIds !== null) {
            $qb->where('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $this->withRelations($qb)->getQuery()->getResult();
    }

    /**
     * @param list<int>|null $allowedIds null: no restriction; []: block all
     *
     * @return list<Photo>
     */
    public function findByCreator(int $userId, ?array $allowedIds = null, ?int $limit = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->where('p.createdBy = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('p.takenAt', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC');

        if ($allowedIds !== null) {
            $qb->andWhere('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        if ($limit === null) {
            return $this->withRelations($qb)->getQuery()->getResult();
        }

        $ids = array_values(array_map(intval(...), $qb->select('p.id')->setMaxResults($limit)->getQuery()->getSingleColumnResult()));

        return $ids === [] ? [] : $this->findAll($ids);
    }

    /**
     * @param list<int>|null $allowedIds null: no restriction; []: block all
     *
     * @return array<int, int> user id => photo count, highest count first
     */
    public function countByCreator(?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->select('p.createdBy AS userId, COUNT(p.id) AS total')
            ->groupBy('p.createdBy')
            ->orderBy('total', 'DESC');

        if ($allowedIds !== null) {
            $qb->where('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        $counts = [];
        foreach ($qb->getQuery()->getScalarResult() as $row) {
            if ($row['userId'] === null) {
                continue;
            }

            $counts[(int) $row['userId']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @param  list<int>|null $allowedIds null: no restriction; []: block all
     * @return list<int>      submitted photo ids, oldest first
     */
    public function findSubmittedIds(?array $allowedIds): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.contestSubmitted = true')
            ->orderBy('p.createdAt', 'ASC');

        if ($allowedIds !== null) {
            $qb->andWhere('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return array_values(array_map(intval(...), $qb->getQuery()->getSingleColumnResult()));
    }

    /** @param list<int>|null $allowedIds null: no restriction; []: block all */
    public function countSubmittedByCreator(int $userId, ?array $allowedIds): int
    {
        if ($allowedIds === []) {
            return 0;
        }

        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.contestSubmitted = true')
            ->andWhere('p.createdBy = :userId')
            ->setParameter('userId', $userId);

        if ($allowedIds !== null) {
            $qb->andWhere('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @param list<int> $ids */
    public function clearSubmitted(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $this->createQueryBuilder('p')
            ->update()
            ->set('p.contestSubmitted', ':off')->setParameter('off', false)
            ->where('p.id IN (:ids)')->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    /**
     * @param list<int>|null $allowedIds null: no restriction; []: block all
     */
    public function findOneAllowed(int $id, ?array $allowedIds): ?Photo
    {
        if ($allowedIds === []) {
            return null;
        }

        $qb = $this->createQueryBuilder('p')->andWhere('p.id = :id')->setParameter('id', $id);

        if ($allowedIds !== null) {
            $qb->andWhere('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    private function withRelations(QueryBuilder $qb): QueryBuilder
    {
        return $qb
            ->addSelect('i', 't')
            ->leftJoin('p.image', 'i')
            ->leftJoin('p.translations', 't');
    }
}
