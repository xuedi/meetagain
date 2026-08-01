<?php declare(strict_types=1);

namespace Plugin\Photos\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
            ->addSelect('i')
            ->leftJoin('p.image', 'i')
            ->orderBy('p.takenAt', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC');

        if ($allowedIds !== null) {
            $qb->where('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
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
}
