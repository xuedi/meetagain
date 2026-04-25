<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Host;
use App\Entity\User;
use App\Enum\EventRsvpFilter;
use App\Enum\EventSortFilter;
use App\Enum\EventTimeFilter;
use App\Enum\EventStatus;
use App\Enum\EventType;
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/** @extends ServiceEntityRepository<Event> */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * @param array<int>|null $restrictToEventIds Optional event ID filter
     * @return Event[]
     */
    public function findByFilters(
        EventTimeFilter $time,
        EventSortFilter $sort,
        EventType $type,
        ?UserInterface $user = null,
        ?EventRsvpFilter $rsvp = null,
        ?array $restrictToEventIds = null,
    ): array {
        $qb = $this
            ->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->leftJoin('e.rsvp', 'r')
            ->addSelect('r')
            ->leftJoin('e.previewImage', 'pi')
            ->addSelect('pi')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('statuses', [EventStatus::Published->value, EventStatus::Locked->value]);

        match ($time) {
            EventTimeFilter::Past => $qb->andWhere('e.start <= :now')->setParameter('now', new DateTime()),
            EventTimeFilter::Future => $qb->andWhere('e.start >= :now')->setParameter('now', new DateTime()),
            EventTimeFilter::All => null,
        };

        if ($type !== EventType::All) {
            $qb->andWhere('e.type = :type')->setParameter('type', $type->value);
        }

        if ($rsvp instanceof EventRsvpFilter && $rsvp !== EventRsvpFilter::All && $user instanceof \App\Entity\User) {
            if ($rsvp === EventRsvpFilter::My) {
                $qb->innerJoin('e.rsvp', 'u', 'WITH', 'u.id = :userId')->setParameter('userId', $user->getId());
            }

            // Friends filtering not yet implemented
        }

        // Apply event ID filter if provided
        if ($restrictToEventIds !== null) {
            if ($restrictToEventIds === []) {
                return []; // Empty filter = no results
            }
            $qb->andWhere('e.id IN (:eventIds)')->setParameter('eventIds', $restrictToEventIds);
        }

        $qb->orderBy('e.start', $sort->value);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<int>|null $restrictToEventIds Optional event ID filter
     * @return array<Event>
     */
    public function findUpcomingEventsWithinRange(
        DateTimeInterface $start,
        DateTimeInterface $end,
        ?array $restrictToEventIds = null,
    ): array {
        $qb = $this
            ->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->where('e.start BETWEEN :start AND :end')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('statuses', [EventStatus::Published->value, EventStatus::Locked->value]);

        if ($restrictToEventIds !== null) {
            if ($restrictToEventIds === []) {
                return [];
            }
            $qb->andWhere('e.id IN (:eventIds)')->setParameter('eventIds', $restrictToEventIds);
        }

        return $qb->orderBy('e.start', 'ASC')->getQuery()->getResult();
    }

    /**
     * Find upcoming events that need RSVP follower notifications.
     * Returns events where: start is between $from and $to, rsvpNotificationSentAt IS NULL,
     * not canceled, and has at least one RSVP attendee.
     *
     * @return array<Event>
     */
    public function findUpcomingEventsNeedingRsvpNotification(DateTimeInterface $from, DateTimeInterface $to): array
    {
        return $this
            ->createQueryBuilder('e')
            ->innerJoin('e.rsvp', 'r')
            ->where('e.start BETWEEN :from AND :to')
            ->andWhere('e.rsvpNotificationSentAt IS NULL')
            ->andWhere('e.canceled = :notCanceled')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('notCanceled', false)
            ->setParameter('statuses', [EventStatus::Published->value, EventStatus::Locked->value])
            ->groupBy('e.id')
            ->orderBy('e.start', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events starting within $from to $to that have RSVP attendees
     * and have not yet had a reminder sent.
     *
     * @return array<Event>
     */
    public function findEventsNeedingReminder(DateTimeInterface $from, DateTimeInterface $to): array
    {
        return $this
            ->createQueryBuilder('e')
            ->innerJoin('e.rsvp', 'r')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->where('e.start BETWEEN :from AND :to')
            ->andWhere('e.eventReminderSentAt IS NULL')
            ->andWhere('e.canceled = :notCanceled')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('notCanceled', false)
            ->setParameter('statuses', [EventStatus::Published->value, EventStatus::Locked->value])
            ->groupBy('e.id')
            ->addGroupBy('t.id')
            ->orderBy('e.start', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find upcoming events within a date range that the given user has NOT RSVP'd to.
     *
     * @return array<Event>
     */
    public function findUpcomingEventsNotRsvpdByUser(
        DateTimeInterface $from,
        DateTimeInterface $to,
        User $user,
    ): array {
        return $this
            ->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->leftJoin('e.location', 'l')
            ->addSelect('l')
            ->where('e.start BETWEEN :from AND :to')
            ->andWhere('e.canceled = :notCanceled')
            ->andWhere('e.status IN (:statuses)')
            ->andWhere(':user NOT MEMBER OF e.rsvp')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('notCanceled', false)
            ->setParameter('statuses', [EventStatus::Published->value, EventStatus::Locked->value])
            ->setParameter('user', $user)
            ->orderBy('e.start', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getEventNameList(string $language): array
    {
        $events = $this
            ->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->getQuery()
            ->getResult();

        $list = [];
        foreach ($events as $event) {
            $list[$event->getId()] = $event->getTitle($language);
        }

        return $list;
    }

    /**
     * @param array<int>|null $restrictToEventIds Optional event ID filter
     * @return array<Event>
     */
    public function getUpcomingEvents(int $number = 3, ?array $restrictToEventIds = null): array
    {
        // Two-step query so setMaxResults applies to distinct events, not to the
        // post-join row count. Fetch-joining translations + rsvp inflates the row
        // count per event; a single-pass LIMIT would truncate inside one event
        // and hydrate fewer distinct entities than requested.
        if ($restrictToEventIds === []) {
            return [];
        }

        $idsQb = $this
            ->createQueryBuilder('e')
            ->select('e.id')
            ->where('e.start > :date')
            ->setParameter('date', new DateTime())
            ->orderBy('e.start', 'ASC')
            ->setMaxResults($number);

        if ($restrictToEventIds !== null) {
            $idsQb->andWhere('e.id IN (:eventIds)')->setParameter('eventIds', $restrictToEventIds);
        }

        $ids = $idsQb->getQuery()->getSingleColumnResult();
        if ($ids === []) {
            return [];
        }

        return $this
            ->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->leftJoin('e.location', 'l')
            ->addSelect('l')
            ->leftJoin('e.rsvp', 'r')
            ->addSelect('r')
            ->leftJoin('e.previewImage', 'pi')
            ->addSelect('pi')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('e.start', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<int>|null $restrictToEventIds Optional event ID filter
     * @return array<Event>
     */
    public function getPastEvents(int $number = 3, ?array $restrictToEventIds = null): array
    {
        $qb = $this
            ->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->leftJoin('e.location', 'l')
            ->addSelect('l')
            ->leftJoin('e.rsvp', 'r')
            ->addSelect('r')
            ->leftJoin('e.previewImage', 'pi')
            ->addSelect('pi')
            ->where('e.start < :date')
            ->setParameter('date', new DateTime());

        // Apply event ID filter if provided
        if ($restrictToEventIds !== null) {
            if ($restrictToEventIds === []) {
                return [];
            }
            $qb->andWhere('e.id IN (:eventIds)')->setParameter('eventIds', $restrictToEventIds);
        }

        return $qb->orderBy('e.start', 'DESC')->setMaxResults($number)->getQuery()->getResult();
    }

    /**
     * @param array<int>|null $restrictToEventIds Optional event ID filter
     * @return array<Event>
     */
    public function getPastAttendedEvents(User $user, int $number = 20, ?array $restrictToEventIds = null): array
    {
        $qb = $this
            ->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->leftJoin('e.location', 'l')
            ->addSelect('l')
            ->leftJoin('e.rsvp', 'r')
            ->addSelect('r')
            ->leftJoin('e.previewImage', 'pi')
            ->addSelect('pi')
            ->where('e.start < :date')
            ->andWhere(':user MEMBER OF e.rsvp')
            ->setParameter('date', new DateTime())
            ->setParameter('user', $user);

        if ($restrictToEventIds !== null) {
            if ($restrictToEventIds === []) {
                return [];
            }
            $qb->andWhere('e.id IN (:eventIds)')->setParameter('eventIds', $restrictToEventIds);
        }

        return $qb->orderBy('e.start', 'DESC')->setMaxResults($number)->getQuery()->getResult();
    }

    /**
     * Count past events the user attended (RSVPed to).
     *
     * @param array<int>|null $restrictToEventIds Optional event ID filter
     */
    public function countAttendedEvents(User $user, ?array $restrictToEventIds = null): int
    {
        if ($restrictToEventIds === []) {
            return 0;
        }

        $qb = $this
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.start < :date')
            ->andWhere(':user MEMBER OF e.rsvp')
            ->setParameter('date', new DateTime())
            ->setParameter('user', $user);

        if ($restrictToEventIds !== null) {
            $qb->andWhere('e.id IN (:eventIds)')->setParameter('eventIds', $restrictToEventIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Count upcoming events the user has RSVPed to.
     *
     * @param array<int>|null $restrictToEventIds Optional event ID filter
     */
    public function countUpcomingRsvpEvents(User $user, ?array $restrictToEventIds = null): int
    {
        if ($restrictToEventIds === []) {
            return 0;
        }

        $qb = $this
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.start >= :date')
            ->andWhere(':user MEMBER OF e.rsvp')
            ->setParameter('date', new DateTime())
            ->setParameter('user', $user);

        if ($restrictToEventIds !== null) {
            $qb->andWhere('e.id IN (:eventIds)')->setParameter('eventIds', $restrictToEventIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array<Event>
     */
    public function findAllRecurring(): array
    {
        return $this
            ->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->where('e.recurringRule IS NOT NULL')
            ->orderBy('e.start', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, Event>
     */
    public function findFollowUpEvents(int $parentEventId, DateTimeInterface $greaterThan): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $query = $qb
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.recurringOf = :parentEvent')
            ->andWhere('e.start > :greaterThan')
            ->setParameter('parentEvent', $parentEventId)
            ->setParameter('greaterThan', $greaterThan)
            ->orderBy('e.start', 'ASC')
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Get the ID of the next upcoming event.
     *
     * @param array<int>|null $restrictToEventIds Optional event ID filter
     */
    public function getNextEventId(?array $restrictToEventIds = null): ?int
    {
        $now = new DateTime();
        $now->setTime(0, 0, 0);
        foreach ($this->findAllForAdmin($restrictToEventIds) as $event) {
            /** @var DateTime $start */
            $start = clone $event->getStart();
            $start->setTime(0, 0);
            if ($start > $now) { // first bigger than today
                return $event->getId();
            }
        }

        return null;
    }

    public function getChoices(string $locale): array
    {
        $events = $this
            ->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->where('e.initial = :initial')
            ->setParameter('initial', true)
            ->getQuery()
            ->getResult();

        $list = [];
        foreach ($events as $event) {
            $list[$event->getTitle($locale)] = $event->getId();
        }

        return $list;
    }

    /**
     * @return array<Event>
     */
    public function getPastEventsWithoutPhotos(int $limit = 5): array
    {
        return $this
            ->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->leftJoin('e.images', 'i')
            ->where('e.start < :date')
            ->andWhere('i.id IS NULL')
            ->setParameter('date', new DateTime())
            ->orderBy('e.start', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getRecurringCount(): int
    {
        return (int) $this
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.recurringRule IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find all events for admin interface with optional filtering.
     *
     * @param array<int>|null $restrictToEventIds Optional event ID filter
     * @return array<Event>
     */
    public function findAllForAdmin(?array $restrictToEventIds = null): array
    {
        $qb = $this
            ->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->leftJoin('e.location', 'l')
            ->addSelect('l');

        // Apply event ID filter if provided
        if ($restrictToEventIds !== null) {
            if ($restrictToEventIds === []) {
                return []; // Empty filter = no results
            }
            $qb->andWhere('e.id IN (:eventIds)')->setParameter('eventIds', $restrictToEventIds);
        }

        return $qb->orderBy('e.start', 'ASC')->getQuery()->getResult();
    }

    /**
     * Get RSVP counts per event in a single aggregate query.
     *
     * @param array<int>|null $eventIds Optional event ID filter
     * @return array<int, int> Map of event ID to RSVP count
     */
    public function getRsvpCounts(?array $eventIds = null): array
    {
        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('e.id', 'COUNT(r.id) as cnt')
            ->from(Event::class, 'e')
            ->leftJoin('e.rsvp', 'r')
            ->groupBy('e.id');

        if ($eventIds !== null && $eventIds !== []) {
            $qb->andWhere('e.id IN (:ids)')->setParameter('ids', $eventIds);
        }

        return array_column($qb->getQuery()->getArrayResult(), 'cnt', 'id');
    }

    /**
     * Find featured events with location and preview image eagerly loaded.
     *
     * @param array<int>|null $restrictToEventIds Optional event ID filter
     * @return array<Event>
     */
    public function findFeatured(?array $restrictToEventIds = null): array
    {
        if ($restrictToEventIds === []) {
            return [];
        }

        $qb = $this
            ->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->leftJoin('e.location', 'l')
            ->addSelect('l')
            ->leftJoin('e.previewImage', 'pi')
            ->addSelect('pi')
            ->where('e.featured = :featured')
            ->setParameter('featured', true)
            ->orderBy('e.start', 'DESC');

        if ($restrictToEventIds !== null) {
            $qb->andWhere('e.id IN (:ids)')->setParameter('ids', $restrictToEventIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find a single event with all detail-page associations eagerly loaded to avoid N+1 queries.
     * Fetches: location, previewImage, images, host, host.user, rsvp, rsvp.image, translations.
     */
    public function findOneForDetails(int $id): ?Event
    {
        return $this
            ->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->leftJoin('e.location', 'l')
            ->addSelect('l')
            ->leftJoin('e.previewImage', 'pi')
            ->addSelect('pi')
            ->leftJoin('e.images', 'img')
            ->addSelect('img')
            ->leftJoin('e.host', 'h')
            ->addSelect('h')
            ->leftJoin('h.user', 'hu')
            ->addSelect('hu')
            ->leftJoin('e.rsvp', 'r')
            ->addSelect('r')
            ->leftJoin('r.image', 'ri')
            ->addSelect('ri')
            ->where('e.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find events by their IDs with translations eagerly loaded.
     *
     * @param array<int> $eventIds
     * @return array<Event>
     */
    public function findByIds(array $eventIds, string $orderBy = 'start', string $direction = 'DESC'): array
    {
        if ($eventIds === []) {
            return [];
        }

        return $this
            ->createQueryBuilder('e')
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->where('e.id IN (:eventIds)')
            ->setParameter('eventIds', $eventIds)
            ->orderBy('e.' . $orderBy, $direction)
            ->getQuery()
            ->getResult();
    }

    /**
     * Paginated public upcoming events for the JSON API.
     * Returns `[items, total]`. `items` respects $restrictToEventIds; $total is the
     * full matching row count (pre-limit/offset) so callers can render pagination.
     *
     * @param array<int>|null $restrictToEventIds null = no restriction, [] = empty result
     * @return array{items: Event[], total: int}
     */
    public function findPublicUpcoming(
        DateTimeInterface $from,
        ?DateTimeInterface $to,
        int $limit,
        int $offset,
        ?array $restrictToEventIds,
    ): array {
        if ($restrictToEventIds === []) {
            return ['items' => [], 'total' => 0];
        }

        $qb = $this
            ->createQueryBuilder('e')
            ->where('e.start >= :from')
            ->andWhere('e.canceled = :notCanceled')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('from', $from)
            ->setParameter('notCanceled', false)
            ->setParameter('statuses', [EventStatus::Published->value, EventStatus::Locked->value]);

        if ($to !== null) {
            $qb->andWhere('e.start <= :to')->setParameter('to', $to);
        }

        if ($restrictToEventIds !== null) {
            $qb->andWhere('e.id IN (:eventIds)')->setParameter('eventIds', $restrictToEventIds);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(DISTINCT e.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->leftJoin('e.translations', 't')
            ->addSelect('t')
            ->orderBy('e.start', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Find all published events for sitemap generation.
     *
     * @return Event[]
     */
    public function findForSitemap(): array
    {
        return $this
            ->createQueryBuilder('e')
            ->where('e.status IN (:statuses)')
            ->setParameter('statuses', [EventStatus::Published->value, EventStatus::Locked->value])
            ->orderBy('e.start', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Event[]
     */
    public function findByHost(Host $host): array
    {
        return $this
            ->createQueryBuilder('e')
            ->join('e.host', 'h')
            ->where('h = :host')
            ->setParameter('host', $host)
            ->getQuery()
            ->getResult();
    }
}
