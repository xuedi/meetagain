<?php declare(strict_types=1);

namespace App\Service;

use App\Comment\EventTargetProvider;
use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\Image;
use App\Entity\Topic;
use App\Entity\User;
use App\Filter\TownHall\ScopeIntersection;
use App\Filter\TownHall\TownHallEventScopeFilterInterface;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;
use App\Repository\ImageRepository;
use App\Repository\UserRepository;
use App\Service\TownHall\TopicService;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class TownHallService
{
    /**
     * @param iterable<TownHallEventScopeFilterInterface> $eventFilters
     */
    public function __construct(
        private CommentRepository $commentRepo,
        private ImageRepository $imageRepo,
        private EventRepository $eventRepo,
        private UserRepository $userRepo,
        private TopicService $topicService,
        private ScopeIntersection $scopeIntersection,
        #[AutowireIterator(TownHallEventScopeFilterInterface::class)]
        private iterable $eventFilters,
    ) {}

    /**
     * @return list<array{comment: Comment, event: ?Event, topic: ?Topic, topicPath: list<Topic>}>
     */
    public function getLatestConversations(int $limit = 6): array
    {
        $eventComments = $this->commentRepo->findRecentForTargetType(EventTargetProvider::TYPE, $limit, $this->resolveEventIds());
        $events = $this->indexById($this->eventRepo->findBy(['id' => $this->targetIds($eventComments)]));

        $rows = [];
        foreach ($eventComments as $comment) {
            $event = $events[$comment->getTargetId()] ?? null;
            if ($event instanceof Event) {
                $rows[] = ['comment' => $comment, 'event' => $event, 'topic' => null, 'topicPath' => []];
            }
        }
        foreach ($this->topicService->getRecentActivity($limit) as $row) {
            $rows[] = ['comment' => $row['comment'], 'event' => null, 'topic' => $row['topic'], 'topicPath' => $row['topicPath']];
        }

        usort($rows, static fn(array $a, array $b): int => $b['comment']->getCreatedAt() <=> $a['comment']->getCreatedAt());

        return array_slice($rows, 0, $limit);
    }

    /**
     * @return array<Image>
     */
    public function getLatestEventImages(int $limit = 8): array
    {
        return $this->imageRepo->findRecentEventUploads($limit, $this->resolveEventIds());
    }

    /**
     * @return array<Image>
     */
    public function getAllEventImagesChronological(int $limit = 500): array
    {
        return $this->imageRepo->findAllEventUploadsChronological($this->resolveEventIds(), $limit);
    }

    /**
     * @return array<Event>
     */
    public function getUpcomingEvents(int $limit = 5): array
    {
        return $this->eventRepo->getUpcomingEvents($limit, $this->resolveEventIds());
    }

    /**
     * @return array<Event>
     */
    public function getRecentPastEvents(int $limit = 3): array
    {
        return $this->eventRepo->getPastEvents($limit, $this->resolveEventIds());
    }

    /**
     * @return array<User>
     */
    public function getNewMembersThisMonth(int $limit = 5): array
    {
        $since = new DateTimeImmutable('-30 days');

        return $this->userRepo->findActiveCreatedSince($since, $limit, $this->resolveUserIds());
    }

    /**
     * @return array{memberCount: int, eventCount: int, topicCount: int}
     */
    public function getStats(): array
    {
        $eventIds = $this->resolveEventIds();

        return [
            'memberCount' => $this->userRepo->getNumberOfActiveMembers([], $this->resolveUserIds()),
            'eventCount' => $eventIds === null ? $this->countAllPublishedEvents() : count($eventIds),
            'topicCount' => $this->topicService->countVisible(),
        ];
    }

    /**
     * @return array<int>|null
     */
    private function resolveEventIds(): ?array
    {
        $lists = [];
        foreach ($this->eventFilters as $filter) {
            $lists[] = $filter->getEventIdFilter();
        }

        return $this->scopeIntersection->of($lists);
    }

    /**
     * @return array<int>|null
     */
    private function resolveUserIds(): ?array
    {
        $lists = [];
        foreach ($this->eventFilters as $filter) {
            $lists[] = $filter->getUserIdFilter();
        }

        return $this->scopeIntersection->of($lists);
    }

    /**
     * @param array<Comment> $comments
     * @return list<int>
     */
    private function targetIds(array $comments): array
    {
        return array_values(array_map(static fn(Comment $c): int => (int) $c->getTargetId(), $comments));
    }

    /**
     * @param array<Event> $events
     * @return array<int, Event>
     */
    private function indexById(array $events): array
    {
        $indexed = [];
        foreach ($events as $event) {
            $indexed[(int) $event->getId()] = $event;
        }

        return $indexed;
    }

    private function countAllPublishedEvents(): int
    {
        return (int) $this->eventRepo->createQueryBuilder('e')->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();
    }
}
