<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\EventSeries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EventSeries> */
class EventSeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventSeries::class);
    }

    /**
     * @return array<EventSeries>
     */
    public function findOpen(): array
    {
        return $this
            ->createQueryBuilder('s')
            ->where('s.rule IS NOT NULL')
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
