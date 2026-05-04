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

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countSince(?DateTimeImmutable $since, ?int $userId = null): int
    {
        $qb = $this->createQueryBuilder('a')->select('COUNT(a.id)');
        if ($since !== null) {
            $qb->andWhere('a.createdAt >= :since')->setParameter('since', $since);
        }
        if ($userId !== null) {
            $qb->andWhere('a.user = :user')->setParameter('user', $userId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findMostRecent(): ?Activity
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Activity[]
     */
    public function findRecentForAdmin(int $limit, ?DateTimeImmutable $since = null, ?int $userId = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($since !== null) {
            $qb->andWhere('a.createdAt >= :since')->setParameter('since', $since);
        }
        if ($userId !== null) {
            $qb->andWhere('a.user = :user')->setParameter('user', $userId);
        }

        return $qb->getQuery()->getResult();
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
     * @param array<int>|null $restrictToUserIds
     * @return array{yes: int, no: int, total: int}
     */
    public function getRsvpStats(DateTimeImmutable $start, DateTimeImmutable $end, ?array $restrictToUserIds = null): array
    {
        if ($restrictToUserIds === []) {
            return ['yes' => 0, 'no' => 0, 'total' => 0];
        }

        $yes = $this->countByType(RsvpYes::TYPE, $start, $end, $restrictToUserIds);
        $no = $this->countByType(RsvpNo::TYPE, $start, $end, $restrictToUserIds);

        return [
            'yes' => $yes,
            'no' => $no,
            'total' => $yes + $no,
        ];
    }

    /**
     * Get login activity trend for dashboard.
     *
     * @param array<int>|null $restrictToUserIds
     * @return array<string, int> Day name => login count
     */
    public function getLoginTrend(DateTimeImmutable $start, DateTimeImmutable $end, ?array $restrictToUserIds = null): array
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $trend = array_fill_keys($days, 0);

        if ($restrictToUserIds === []) {
            return $trend;
        }

        $qb = $this
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
            ->groupBy('day');

        if ($restrictToUserIds !== null) {
            $qb->andWhere('IDENTITY(a.user) IN (:userIds)')->setParameter('userIds', $restrictToUserIds);
        }

        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $trend[$row['day']] = (int) $row['count'];
        }

        return $trend;
    }

    /**
     * Yes-RSVPs per day-of-week label (Monday..Sunday) for the week-bar chart.
     *
     * @param array<int>|null $restrictToUserIds
     * @return array<string, int>
     */
    public function getRsvpYesTrend(DateTimeImmutable $start, DateTimeImmutable $end, ?array $restrictToUserIds = null): array
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $trend = array_fill_keys($days, 0);

        if ($restrictToUserIds === []) {
            return $trend;
        }

        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select("DATE_FORMAT(a.createdAt, '%W') AS day", 'COUNT(a.id) AS count')
            ->from(Activity::class, 'a')
            ->where('a.type = :type')
            ->andWhere('a.createdAt >= :start')
            ->andWhere('a.createdAt <= :end')
            ->setParameter('type', RsvpYes::TYPE)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('day');

        if ($restrictToUserIds !== null) {
            $qb->andWhere('IDENTITY(a.user) IN (:userIds)')->setParameter('userIds', $restrictToUserIds);
        }

        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $trend[$row['day']] = (int) $row['count'];
        }

        return $trend;
    }

    /**
     * Daily counts of logins, RSVPs (yes+no), and new members for the multi-series chart.
     *
     * @param array<int>|null $restrictToUserIds
     * @return array{labels: list<string>, logins: list<int>, rsvps: list<int>, newMembers: list<int>}
     */
    public function getActivityTrend(DateTimeImmutable $start, DateTimeImmutable $end, ?array $restrictToUserIds = null): array
    {
        $labels = [];
        $cursor = $start;
        while ($cursor <= $end) {
            $labels[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        $emptyByDay = array_fill_keys($labels, 0);

        if ($restrictToUserIds === []) {
            return [
                'labels' => $labels,
                'logins' => array_values($emptyByDay),
                'rsvps' => array_values($emptyByDay),
                'newMembers' => array_values($emptyByDay),
            ];
        }

        $logins = $this->countByDay(Login::TYPE, $start, $end, $restrictToUserIds, $emptyByDay);

        // RSVPs combined (yes + no)
        $yes = $this->countByDay(RsvpYes::TYPE, $start, $end, $restrictToUserIds, $emptyByDay);
        $no = $this->countByDay(RsvpNo::TYPE, $start, $end, $restrictToUserIds, $emptyByDay);
        $rsvps = $emptyByDay;
        foreach ($rsvps as $day => $_) {
            $rsvps[$day] = ($yes[$day] ?? 0) + ($no[$day] ?? 0);
        }

        $newMembersQb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select("DATE_FORMAT(u.createdAt, '%Y-%m-%d') AS day", 'COUNT(u.id) AS count')
            ->from(\App\Entity\User::class, 'u')
            ->where('u.createdAt >= :start')
            ->andWhere('u.createdAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('day');

        if ($restrictToUserIds !== null) {
            $newMembersQb->andWhere('u.id IN (:userIds)')->setParameter('userIds', $restrictToUserIds);
        }

        $newMembers = $emptyByDay;
        foreach ($newMembersQb->getQuery()->getArrayResult() as $row) {
            $newMembers[$row['day']] = (int) $row['count'];
        }

        return [
            'labels' => $labels,
            'logins' => array_values($logins),
            'rsvps' => array_values($rsvps),
            'newMembers' => array_values($newMembers),
        ];
    }

    /**
     * @param array<int>|null $restrictToUserIds
     */
    private function countByType(string $type, DateTimeImmutable $start, DateTimeImmutable $end, ?array $restrictToUserIds): int
    {
        $qb = $this
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.type = :type')
            ->andWhere('a.createdAt >= :start')
            ->andWhere('a.createdAt <= :end')
            ->setParameter('type', $type)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($restrictToUserIds !== null) {
            $qb->andWhere('IDENTITY(a.user) IN (:userIds)')->setParameter('userIds', $restrictToUserIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param array<int>|null $restrictToUserIds
     * @param array<string, int> $emptyByDay
     * @return array<string, int>
     */
    private function countByDay(string $type, DateTimeImmutable $start, DateTimeImmutable $end, ?array $restrictToUserIds, array $emptyByDay): array
    {
        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select("DATE_FORMAT(a.createdAt, '%Y-%m-%d') AS day", 'COUNT(a.id) AS count')
            ->from(Activity::class, 'a')
            ->where('a.type = :type')
            ->andWhere('a.createdAt >= :start')
            ->andWhere('a.createdAt <= :end')
            ->setParameter('type', $type)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('day');

        if ($restrictToUserIds !== null) {
            $qb->andWhere('IDENTITY(a.user) IN (:userIds)')->setParameter('userIds', $restrictToUserIds);
        }

        $byDay = $emptyByDay;
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $byDay[$row['day']] = (int) $row['count'];
        }

        return $byDay;
    }
}
