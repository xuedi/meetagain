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
        $rows = $this->createQueryBuilder('g')
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

    public function deleteFor(Event $event, User $user): void
    {
        $this->createQueryBuilder('g')
            ->delete()
            ->where('g.event = :event')
            ->andWhere('g.user = :user')
            ->setParameter('event', $event)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
