<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\Image;
use App\Entity\User;
use App\Entity\WallPost;
use App\Filter\TownHall\TownHallEventScopeFilterInterface;
use App\Filter\TownHall\WallScopeFilterInterface;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;
use App\Repository\ImageRepository;
use App\Repository\UserRepository;
use App\Repository\WallPostRepository;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class TownHallService
{
    /**
     * @param iterable<WallScopeFilterInterface> $wallFilters
     * @param iterable<TownHallEventScopeFilterInterface> $eventFilters
     */
    public function __construct(
        private WallPostRepository $wallPostRepo,
        private CommentRepository $commentRepo,
        private ImageRepository $imageRepo,
        private EventRepository $eventRepo,
        private UserRepository $userRepo,
        #[AutowireIterator(WallScopeFilterInterface::class)]
        private iterable $wallFilters,
        #[AutowireIterator(TownHallEventScopeFilterInterface::class)]
        private iterable $eventFilters,
    ) {}

    /**
     * @return array<WallPost>
     */
    public function getRecentWallPosts(int $limit = 5): array
    {
        return $this->wallPostRepo->findRecent($limit, $this->resolveWallPostIds());
    }

    /**
     * @return array<WallPost>
     */
    public function getPaginatedWallPosts(int $page, int $perPage): array
    {
        return $this->wallPostRepo->findPaginated($page, $perPage, $this->resolveWallPostIds());
    }

    public function countWallPosts(): int
    {
        return $this->wallPostRepo->countAll($this->resolveWallPostIds());
    }

    /**
     * @return array<Comment>
     */
    public function getLatestEventComments(int $limit = 5): array
    {
        return $this->commentRepo->findRecentAcrossEvents($limit, $this->resolveEventIds());
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
     * @return array{memberCount: int, eventCount: int, wallPostCount: int}
     */
    public function getStats(): array
    {
        $eventIds = $this->resolveEventIds();
        $userIds = $this->resolveUserIds();
        $wallIds = $this->resolveWallPostIds();

        return [
            'memberCount' => $this->userRepo->getNumberOfActiveMembers([], $userIds),
            'eventCount' => $eventIds === null ? $this->countAllPublishedEvents() : count($eventIds),
            'wallPostCount' => $this->wallPostRepo->countAll($wallIds),
        ];
    }

    /**
     * @return array<int>|null
     */
    private function resolveWallPostIds(): ?array
    {
        $result = null;
        foreach ($this->wallFilters as $filter) {
            $ids = $filter->getWallPostIdFilter();
            if ($ids === null) {
                continue;
            }
            if ($ids === []) {
                return [];
            }
            $result = $result === null ? $ids : array_values(array_intersect($result, $ids));
            if ($result === []) {
                return [];
            }
        }

        return $result;
    }

    /**
     * @return array<int>|null
     */
    private function resolveEventIds(): ?array
    {
        $result = null;
        foreach ($this->eventFilters as $filter) {
            $ids = $filter->getEventIdFilter();
            if ($ids === null) {
                continue;
            }
            if ($ids === []) {
                return [];
            }
            $result = $result === null ? $ids : array_values(array_intersect($result, $ids));
            if ($result === []) {
                return [];
            }
        }

        return $result;
    }

    /**
     * @return array<int>|null
     */
    private function resolveUserIds(): ?array
    {
        $result = null;
        foreach ($this->eventFilters as $filter) {
            $ids = $filter->getUserIdFilter();
            if ($ids === null) {
                continue;
            }
            if ($ids === []) {
                return [];
            }
            $result = $result === null ? $ids : array_values(array_intersect($result, $ids));
            if ($result === []) {
                return [];
            }
        }

        return $result;
    }

    private function countAllPublishedEvents(): int
    {
        return (int) $this->eventRepo->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
