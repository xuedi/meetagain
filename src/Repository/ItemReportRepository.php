<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\ItemReport;
use App\Enum\ItemReportStatus;
use DateTimeImmutable;
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

    public function anonymiseResolvedBefore(DateTimeImmutable $cutoff): int
    {
        return (int) $this
            ->createQueryBuilder('ir')
            ->update()
            ->set('ir.reporter', 'NULL')
            ->set('ir.notifierName', ':blank')
            ->set('ir.notifierEmail', ':blank')
            ->set('ir.explanation', ':blank')
            ->where('ir.status <> :open')
            ->andWhere('ir.resolvedAt < :cutoff')
            ->andWhere("ir.reporter IS NOT NULL OR ir.notifierName <> '' OR ir.notifierEmail <> '' OR ir.explanation <> ''")
            ->setParameter('blank', '')
            ->setParameter('open', ItemReportStatus::Open)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }

    public function anonymiseNotifier(int $userId, string $email): int
    {
        return (int) $this
            ->createQueryBuilder('ir')
            ->update()
            ->set('ir.notifierName', ':blank')
            ->set('ir.notifierEmail', ':blank')
            ->where('IDENTITY(ir.reporter) = :user OR LOWER(ir.notifierEmail) = :email')
            ->setParameter('blank', '')
            ->setParameter('user', $userId)
            ->setParameter('email', mb_strtolower($email))
            ->getQuery()
            ->execute();
    }
}
