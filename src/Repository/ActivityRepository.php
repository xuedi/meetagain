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
        $events = [];
        foreach ($user->getRsvpEvents() as $event) {
            $events[] = $event->getId();
        }

        $following = [];
        foreach ($user->getFollowing() as $followedUser) {
            $following[] = $followedUser->getId();
        }

        // get all activities of the wanted types
        $qb = $this->getEntityManager()->createQueryBuilder();
        $userActivities = $qb->select('a')
            ->from(Activity::class, 'a')
            ->where($qb->expr()->in('a.type', ':types'))
            ->setParameter('types', [
                ActivityType::ChangedUsername->value,
                ActivityType::EventImageUploaded->value,
            ])
            ->getQuery()
            ->getResult();

        $activities = [];
        foreach ($userActivities as $userActivity) {
            $activityUserId = $userActivity->getUser()->getId();
            switch ($userActivity->getType()->value) {

                // username change of people the user follows
                case ActivityType::ChangedUsername->value:
                    if (in_array($activityUserId, $following)) {
                        $activities[] = $userActivity->getId();
                    }
                    break;

                // uploaded images of the people the user follows, or if he attended the event
                case ActivityType::EventImageUploaded->value:
                    $eventId = $userActivity->getMeta()['event_id'];
                    if (in_array($activityUserId, $following) || in_array($eventId, $events)) {
                        $activities[] = $userActivity->getId();
                    }
                    break;
            }
        }

        $qb = $this->getEntityManager()->createQueryBuilder();
        return $qb->select('a')
            ->from(Activity::class, 'a')
            ->where($qb->expr()->in('a.id', ':ids'))
            ->orderBy('a.createdAt', 'DESC')
            ->setParameter('ids', $activities)
            ->getQuery()
            ->getResult();
    }
}
