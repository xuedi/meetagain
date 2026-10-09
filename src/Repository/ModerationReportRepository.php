<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\ModerationReport;
use App\Entity\User;
use App\Enum\ModerationReportStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ModerationReport> */
class ModerationReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModerationReport::class);
    }

    /** @return list<ModerationReport> */
    public function findAllNewestFirst(): array
    {
        return $this
            ->createQueryBuilder('mr')
            ->addSelect('reporter', 'author')
            ->leftJoin('mr.reporter', 'reporter')
            ->leftJoin('mr.author', 'author')
            ->orderBy('mr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<ModerationReport> */
    public function findOpenNewestFirst(): array
    {
        return $this
            ->createQueryBuilder('mr')
            ->where('mr.status = :status')
            ->setParameter('status', ModerationReportStatus::Open)
            ->orderBy('mr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<ModerationReport> */
    public function findOpenForSubject(string $subjectType, int $subjectId): array
    {
        return $this->findBy([
            'subjectType' => $subjectType,
            'subjectId' => $subjectId,
            'status' => ModerationReportStatus::Open,
        ]);
    }

    public function findOpenByReporter(User $reporter, string $subjectType, int $subjectId): ?ModerationReport
    {
        return $this->findOneBy([
            'reporter' => $reporter,
            'subjectType' => $subjectType,
            'subjectId' => $subjectId,
            'status' => ModerationReportStatus::Open,
        ]);
    }
}
