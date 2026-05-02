<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Image>
 */
class ImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Image::class);
    }

    public function getReportedCount(): int
    {
        return (int) $this
            ->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.reported IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Image[]
     */
    public function getReported(int $limit = 10): array
    {
        return $this
            ->createQueryBuilder('i')
            ->where('i.reported IS NOT NULL')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{count: int, totalSize: int, weekCount: int}
     */
    public function getStorageStats(DateTimeImmutable $weekStart, DateTimeImmutable $weekEnd): array
    {
        $total = $this
            ->createQueryBuilder('i')
            ->select('COUNT(i.id) as cnt, COALESCE(SUM(i.size), 0) as totalSize')
            ->getQuery()
            ->getSingleResult();

        $weekCount = (int) $this
            ->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.createdAt >= :start')
            ->andWhere('i.createdAt <= :end')
            ->setParameter('start', $weekStart)
            ->setParameter('end', $weekEnd)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'count' => (int) $total['cnt'],
            'totalSize' => (int) $total['totalSize'],
            'weekCount' => $weekCount,
        ];
    }

    public function getOldImageUpdates(int $int): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        return $qb
            ->select('i')
            ->from(Image::class, 'i')
            ->where($qb->expr()->isNotNull('i.updatedAt'))
            ->andWhere($qb->expr()->lt('i.updatedAt', ':date'))
            ->setParameter('date', new DateTime('-' . $int . ' days'))
            ->getQuery()
            ->getResult();
    }

    public function getEventList(User $user): array
    {
        $result = $this
            ->createQueryBuilder('i')
            ->leftJoin('i.event', 'e')
            ->addSelect('e')
            ->where('i.uploader = :user')
            ->andWhere('i.event IS NOT NULL')
            ->setParameter('user', $user)
            ->groupBy('i.event')
            ->getQuery()
            ->getResult();

        $return = [];
        foreach ($result as $item) {
            $event = $item->getEvent();
            $return[] = [
                'id' => $event->getId(),
                'date' => $event->getStart()->format('Y-m-d'),
            ];
        }

        return $return;
    }

    /**
     * @return Image[]
     */
    public function findFiltered(?ImageType $type, ?DateTimeImmutable $since): array
    {
        $qb = $this->createQueryBuilder('i')->orderBy('i.createdAt', 'DESC');
        if ($type !== null) {
            $qb->andWhere('i.type = :type')->setParameter('type', $type);
        }

        if ($since !== null) {
            $qb->andWhere('i.createdAt >= :since')->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    public function countFiltered(?ImageType $type, ?DateTimeImmutable $since): int
    {
        $qb = $this->createQueryBuilder('i')->select('COUNT(i.id)');
        if ($type !== null) {
            $qb->andWhere('i.type = :type')->setParameter('type', $type);
        }

        if ($since !== null) {
            $qb->andWhere('i.createdAt >= :since')->setParameter('since', $since);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getFileList(): array
    {
        $list = [];
        foreach ($this->findAll() as $image) {
            $list[$image->getHash()] = $image->getType();
        }

        return $list;
    }
}
