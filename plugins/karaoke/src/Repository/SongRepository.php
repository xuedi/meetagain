<?php declare(strict_types=1);

namespace Plugin\Karaoke\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Karaoke\Entity\Song;

/**
 * @extends ServiceEntityRepository<Song>
 */
class SongRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Song::class);
    }

    /**
     * @param list<int>|null $allowedIds null: no restriction; []: block all
     *
     * @return list<Song>
     */
    public function findAllowed(?array $allowedIds): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('s')->orderBy('s.title', 'ASC');

        if ($allowedIds !== null) {
            $qb->where('s.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param list<int>|null $allowedIds null: no restriction; []: block all
     */
    public function findOneAllowed(int $id, ?array $allowedIds): ?Song
    {
        if ($allowedIds === []) {
            return null;
        }

        $qb = $this
            ->createQueryBuilder('s')
            ->addSelect('l', 't')
            ->leftJoin('s.lines', 'l')
            ->leftJoin('l.translations', 't')
            ->andWhere('s.id = :id')
            ->setParameter('id', $id)
            ->orderBy('l.position', 'ASC');

        if ($allowedIds !== null) {
            $qb->andWhere('s.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
