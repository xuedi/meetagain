<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Event;
use App\Entity\RsvpGuest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RsvpGuest>
 */
class RsvpGuestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RsvpGuest::class);
    }

    /**
     * @return array<int, int> guest count keyed by user id
     */
    public function getCountsForEvent(Event $event): array
    {
        $rows = $this
            ->createQueryBuilder('g')
            ->select('u.id AS userId', 'g.guests')
            ->join('g.user', 'u')
            ->where('g.event = :event')
            ->setParameter('event', $event)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['userId']] = (int) $row['guests'];
        }

        return $counts;
    }

    /**
     * @param list<int> $eventIds
     * @return array<int, int> guest total keyed by event id
     */
    public function getTotalsForEvents(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $rows = $this
            ->createQueryBuilder('g')
            ->select('IDENTITY(g.event) AS eventId', 'SUM(g.guests) AS total')
            ->where('g.event IN (:events)')
            ->groupBy('g.event')
            ->setParameter('events', $eventIds)
            ->getQuery()
            ->getArrayResult();

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['eventId']] = (int) $row['total'];
        }

        return $totals;
    }

    /**
     * @param list<int> $eventIds
     * @return array<int, int> guest count of this user keyed by event id
     */
    public function getCountsForUser(User $user, array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $rows = $this
            ->createQueryBuilder('g')
            ->select('IDENTITY(g.event) AS eventId', 'g.guests')
            ->where('g.event IN (:events)')
            ->andWhere('g.user = :user')
            ->setParameter('events', $eventIds)
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['eventId']] = (int) $row['guests'];
        }

        return $counts;
    }

    public function deleteFor(Event $event, User $user): void
    {
        $this
            ->createQueryBuilder('g')
            ->delete()
            ->where('g.event = :event')
            ->andWhere('g.user = :user')
            ->setParameter('event', $event)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
