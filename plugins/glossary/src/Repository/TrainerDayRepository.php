<?php declare(strict_types=1);

namespace Plugin\Glossary\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Glossary\Entity\TrainerDay;
use SortDirection;

/**
 * @extends ServiceEntityRepository<TrainerDay>
 */
class TrainerDayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainerDay::class);
    }

    public function findDay(int $userId, DateTimeImmutable $day): ?TrainerDay
    {
        return $this
            ->createQueryBuilder('d')
            ->where('d.userId = :userId')
            ->andWhere('d.day = :day')
            ->setParameter('userId', $userId)
            ->setParameter('day', $day->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<string> Y-m-d of every day since the given one on which the member reviewed anything, newest first */
    public function activeDays(int $userId, DateTimeImmutable $since): array
    {
        $rows = $this
            ->createQueryBuilder('d')
            ->select('d.day')
            ->where('d.userId = :userId')
            ->andWhere('d.day >= :since')
            ->andWhere('d.reviewed > 0')
            ->setParameter('userId', $userId)
            ->setParameter('since', $since->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->orderBy('d.day', SortDirection::Descending)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn(array $row): string => $row['day']->format('Y-m-d'), $rows);
    }

    public function deleteForUser(int $userId): void
    {
        $this
            ->createQueryBuilder('d')
            ->delete()
            ->where('d.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}
