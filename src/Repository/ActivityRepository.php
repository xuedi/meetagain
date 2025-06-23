<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    public function getUserDisplay(User $user): array
    {
        $types = [
            ActivityType::ChangedUsername->value,
        ];
        $qb = $this->getEntityManager()->createQueryBuilder();
        return $qb->select('a')
            ->from(Activity::class, 'a')
            ->where($qb->expr()->in('a.type', ':types'))
            ->andWhere($qb->expr()->lt('a.user', ':user'))
            ->setParameter('types', $types)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        // TODO: add event of other that relate to me by my beeing on the same event RSVP
    }
}
