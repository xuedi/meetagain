<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImageReport;
use App\Enum\ImageReportStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ImageReport> */
class ImageReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImageReport::class);
    }

    public function getOpenCount(): int
    {
        return (int) $this
            ->createQueryBuilder('ir')
            ->select('COUNT(ir.id)')
            ->where('ir.status = :status')
            ->setParameter('status', ImageReportStatus::Open)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return ImageReport[] */
    public function getOpen(): array
    {
        return $this
            ->createQueryBuilder('ir')
            ->where('ir.status = :status')
            ->setParameter('status', ImageReportStatus::Open)
            ->orderBy('ir.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
