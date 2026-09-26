<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\ItemReport;
use App\Enum\ItemReportStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ItemReport> */
class ItemReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemReport::class);
    }

    /** @return list<ItemReport> */
    public function findOpen(): array
    {
        return $this
            ->createQueryBuilder('r')
            ->where('r.status = :status')
            ->setParameter('status', ItemReportStatus::Open)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<ItemReport> */
    public function findOpenForItem(string $itemType, int $itemId): array
    {
        return $this
            ->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.itemType = :itemType')
            ->andWhere('r.itemId = :itemId')
            ->setParameter('status', ItemReportStatus::Open)
            ->setParameter('itemType', $itemType)
            ->setParameter('itemId', $itemId)
            ->getQuery()
            ->getResult();
    }
}
