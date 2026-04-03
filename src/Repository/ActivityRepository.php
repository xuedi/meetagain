<?php declare(strict_types=1);

namespace App\Repository;

use App\Activity\Messages\BlockedUser;
use App\Activity\Messages\ChangedUsername;
use App\Activity\Messages\EventImageUploaded;
use App\Activity\Messages\FollowedUser;
use App\Activity\Messages\Login;
use App\Activity\Messages\RsvpNo;
use App\Activity\Messages\RsvpYes;
use App\Activity\Messages\UnblockedUser;
use App\Activity\Messages\UpdatedProfilePicture;
use App\Entity\Activity;
use App\Entity\Event;
use App\Entity\User;
use DateTimeImmutable;
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
        $events = $em
            ->createQueryBuilder()
            ->select('e.id')
            ->from(Event::class, 'e')
            ->innerJoin('e.rsvp', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getSingleColumnResult();

        // Get following user IDs with a single query instead of lazy-loading collection
        $following = $em
            ->createQueryBuilder()
            ->select('f.id')
            ->from(User::class, 'u')
            ->innerJoin('u.following', 'f')
            ->where('u.id = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getSingleColumnResult();

        // Get all activities of the wanted types with user eager-loaded
        $userActivities = $this
            ->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->where('a.type IN (:types)')
            ->setParameter('types', [
                ChangedUsername::TYPE,
                EventImageUploaded::TYPE,
                UpdatedProfilePicture::TYPE,
                BlockedUser::TYPE,
                UnblockedUser::TYPE,
                FollowedUser::TYPE,
                RsvpYes::TYPE,
            ])
            ->getQuery()
            ->getResult();

        $activityIds = [];
        foreach ($userActivities as $userActivity) {
            $activityUserId = $userActivity->getUser()->getId();
            switch ($userActivity->getType()) {
                case ChangedUsername::TYPE:
                    if (in_array($activityUserId, $following)) {
                        $activityIds[] = $userActivity->getId();
                    }
                    break;

                case EventImageUploaded::TYPE:
                    $eventId = $userActivity->getMeta()['event_id'];
                    if (in_array($activityUserId, $following) || in_array($eventId, $events)) {
                        $activityIds[] = $userActivity->getId();
                    }
                    break;

                case UpdatedProfilePicture::TYPE:
                    if (in_array($activityUserId, $following) || $user->getId() === $activityUserId) {
                        $activityIds[] = $userActivity->getId();
                    }
                    break;

                case BlockedUser::TYPE:
                case UnblockedUser::TYPE:
                    // Only visible to the user themselves (self-only visibility)
                    if ($user->getId() === $activityUserId) {
                        $activityIds[] = $userActivity->getId();
                    }
                    break;

                case RsvpYes::TYPE:
                    if (in_array($activityUserId, $following)) {
                        $activityIds[] = $userActivity->getId();
                    }
                    break;

                case FollowedUser::TYPE:
                    // Visible to the target user (the user being followed - "X started following you")
                    $targetUserId = $userActivity->getMeta()['user_id'] ?? null;
                    if ($user->getId() === $targetUserId) {
                        $activityIds[] = $userActivity->getId();
                    }
                    break;
            }
        }

        if ($activityIds === []) {
            return [];
        }

        return $this
            ->createQueryBuilder('a')
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

    /**
     * Get RSVP statistics for a time period.
     *
     * @return array{yes: int, no: int, total: int}
     */
    public function getRsvpStats(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $yes = (int) $this
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.type = :type')
            ->andWhere('a.createdAt >= :start')
            ->andWhere('a.createdAt <= :end')
            ->setParameter('type', RsvpYes::TYPE)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        $no = (int) $this
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.type = :type')
            ->andWhere('a.createdAt >= :start')
            ->andWhere('a.createdAt <= :end')
            ->setParameter('type', RsvpNo::TYPE)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'yes' => $yes,
            'no' => $no,
            'total' => $yes + $no,
        ];
    }

    /**
     * Get login activity trend for dashboard.
     *
     * @return array<string, int> Day name => login count
     */
    public function getLoginTrend(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $result = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select("DATE_FORMAT(a.createdAt, '%W') AS day", 'COUNT(a.id) as count')
            ->from(Activity::class, 'a')
            ->where('a.type = :type')
            ->andWhere('a.createdAt >= :start')
            ->andWhere('a.createdAt <= :end')
            ->setParameter('type', Login::TYPE)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('day')
            ->getQuery()
            ->getArrayResult();

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $trend = array_fill_keys($days, 0);
        foreach ($result as $row) {
            $trend[$row['day']] = (int) $row['count'];
        }

        return $trend;
    }
}
