<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupportRequest;
use App\Enum\SupportRequestStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SupportRequest> */
class SupportRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportRequest::class);
    }

    public function getNewCount(): int
    {
        return (int) $this
            ->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->where('sr.status = :status')
            ->setParameter('status', SupportRequestStatus::New)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
