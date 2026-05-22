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
            ->andWhere('i.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', ImageType::EventUpload)
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
     * @param array<int>|null $restrictToEventIds null = no restriction
     * @return array<Image>
     */
    public function findRecentEventUploads(int $limit, ?array $restrictToEventIds = null): array
    {
        $qb = $this
            ->createQueryBuilder('i')
            ->leftJoin('i.event', 'e')
            ->addSelect('e')
            ->where('i.type = :type')
            ->andWhere('i.event IS NOT NULL')
            ->setParameter('type', ImageType::EventUpload)
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($restrictToEventIds !== null) {
            if ($restrictToEventIds === []) {
                return [];
            }
            $qb->andWhere('i.event IN (:eventIds)')->setParameter('eventIds', $restrictToEventIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<int>|null $restrictToEventIds null = no restriction
     * @return array<Image>
     */
    public function findAllEventUploadsChronological(?array $restrictToEventIds = null, int $limit = 500): array
    {
        $qb = $this
            ->createQueryBuilder('i')
            ->leftJoin('i.event', 'e')
            ->addSelect('e')
            ->where('i.type = :type')
            ->andWhere('i.event IS NOT NULL')
            ->setParameter('type', ImageType::EventUpload)
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($restrictToEventIds !== null) {
            if ($restrictToEventIds === []) {
                return [];
            }
            $qb->andWhere('i.event IN (:eventIds)')->setParameter('eventIds', $restrictToEventIds);
        }

        return $qb->getQuery()->getResult();
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

    /**
     * Images with more than one usage and no alt text - high-impact SEO/a11y issues
     * because one missing alt fans out across every page that embeds the image.
     *
     * @return array<array{id: int, count: int}>
     */
    public function findHighUsageMissingAlt(int $limit = 10): array
    {
        $rows = $this
            ->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative('SELECT i.id AS id, COUNT(il.id) AS cnt
             FROM image i
             INNER JOIN image_location il ON il.image_id = i.id
             WHERE i.alt IS NULL OR i.alt = ""
             GROUP BY i.id
             HAVING cnt > 1
             ORDER BY cnt DESC, i.id ASC
             LIMIT ' . $limit);

        return array_map(static fn(array $r) => ['id' => (int) $r['id'], 'count' => (int) $r['cnt']], $rows);
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
