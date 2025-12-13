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
        $em = $this->getEntityManager();

        // Get RSVP event IDs with a single query instead of lazy-loading collection
        $events = $em->createQueryBuilder()
            ->select('e.id')
            ->from('App\Entity\Event', 'e')
            ->innerJoin('e.rsvp', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getSingleColumnResult();

        // Get following user IDs with a single query instead of lazy-loading collection
        $following = $em->createQueryBuilder()
            ->select('f.id')
            ->from('App\Entity\User', 'u')
            ->innerJoin('u.following', 'f')
            ->where('u.id = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getSingleColumnResult();

        // Get all activities of the wanted types with user eager-loaded
        $userActivities = $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->where('a.type IN (:types)')
            ->setParameter('types', [
                ActivityType::ChangedUsername->value,
                ActivityType::EventImageUploaded->value,
                ActivityType::UpdatedProfilePicture->value,
            ])
            ->getQuery()
            ->getResult();

        $activityIds = [];
        foreach ($userActivities as $userActivity) {
            $activityUserId = $userActivity->getUser()->getId();
            switch ($userActivity->getType()->value) {
                case ActivityType::ChangedUsername->value:
                    if (in_array($activityUserId, $following)) {
                        $activityIds[] = $userActivity->getId();
                    }
                    break;

                case ActivityType::EventImageUploaded->value:
                    $eventId = $userActivity->getMeta()['event_id'];
                    if (in_array($activityUserId, $following) || in_array($eventId, $events)) {
                        $activityIds[] = $userActivity->getId();
                    }
                    break;

                case ActivityType::UpdatedProfilePicture->value:
                    if (in_array($activityUserId, $following) || $user->getId() === $activityUserId) {
                        $activityIds[] = $userActivity->getId();
                    }
                    break;
            }
        }

        if (empty($activityIds)) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->leftJoin('u.image', 'i')
            ->addSelect('i')
            ->where('a.id IN (:ids)')
            ->orderBy('a.createdAt', 'DESC')
            ->setParameter('ids', $activityIds)
            ->getQuery()
            ->getResult();
    }
}
