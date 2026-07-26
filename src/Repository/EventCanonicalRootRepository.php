<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Event;
use App\Entity\EventCanonicalRoot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EventCanonicalRoot> */
class EventCanonicalRootRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventCanonicalRoot::class);
    }

    /**
     * @return array<EventCanonicalRoot>
     */
    public function findBySeries(int $seriesId): array
    {
        return $this->seriesQuery([$seriesId])->getQuery()->getResult();
    }

    /**
     * @return array<EventCanonicalRoot>
     */
    public function findBySeriesAndLocale(int $seriesId, string $locale): array
    {
        return $this
            ->seriesQuery([$seriesId])
            ->andWhere('m.locale = :locale')
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<int> $seriesIds
     * @return array<EventCanonicalRoot>
     */
    public function findBySeriesIds(array $seriesIds): array
    {
        if ($seriesIds === []) {
            return [];
        }

        return $this->seriesQuery($seriesIds)->getQuery()->getResult();
    }

    /**
     * @return array<EventCanonicalRoot>
     */
    public function findByEvent(int $eventId): array
    {
        return $this
            ->createQueryBuilder('m')
            ->where('m.event = :eventId')
            ->setParameter('eventId', $eventId)
            ->getQuery()
            ->getResult();
    }

    public function findOneByEventAndLocale(int $eventId, string $locale): ?EventCanonicalRoot
    {
        return $this
            ->createQueryBuilder('m')
            ->where('m.event = :eventId')
            ->andWhere('m.locale = :locale')
            ->setParameter('eventId', $eventId)
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param array<int> $eventIds
     * @param array<string>|null $locales null deletes every locale
     */
    public function deleteByEventIds(array $eventIds, ?array $locales = null): int
    {
        if ($eventIds === [] || $locales === []) {
            return 0;
        }

        $qb = $this
            ->createQueryBuilder('m')
            ->delete()
            ->where('m.event IN (:eventIds)')
            ->setParameter('eventIds', $eventIds);

        if ($locales !== null) {
            $qb->andWhere('m.locale IN (:locales)')->setParameter('locales', $locales);
        }

        return (int) $qb->getQuery()->execute();
    }

    public function deleteOrphaned(): int
    {
        $orphanedEventIds = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('orphan.id')
            ->from(Event::class, 'orphan')
            ->where('orphan.series IS NULL')
            ->getDQL();

        return (int) $this
            ->createQueryBuilder('m')
            ->delete()
            ->where('m.event IN (' . $orphanedEventIds . ')')
            ->getQuery()
            ->execute();
    }

    /**
     * @param array<int> $seriesIds
     */
    private function seriesQuery(array $seriesIds): QueryBuilder
    {
        return $this
            ->createQueryBuilder('m')
            ->innerJoin('m.event', 'e')
            ->addSelect('e')
            ->where('e.series IN (:seriesIds)')
            ->setParameter('seriesIds', $seriesIds)
            ->orderBy('e.start', 'ASC');
    }
}
