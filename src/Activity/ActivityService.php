<?php declare(strict_types=1);

namespace App\Activity;

use App\Entity\Activity;
use App\Entity\User;
use App\Filter\Event\EventFilterService;
use App\Repository\ActivityRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

readonly class ActivityService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActivityRepository $repo,
        private NotificationService $notificationService,
        private MessageFactory $messageFactory,
        private LoggerInterface $logger,
        #[AutowireIterator(MetaEnricherInterface::class)]
        private iterable $enrichers,
        private EventFilterService $eventFilterService,
    ) {}

    public function log(string $type, ?User $user, array $meta = []): void
    {
        foreach ($this->enrichers as $enricher) {
            try {
                $enriched = $enricher->enrich($type, $user, $meta);
                $meta = array_merge($enriched, $meta); // original keys win
            } catch (Throwable $e) {
                $this->logger->warning('Activity meta enricher failed', [
                    'enricher' => $enricher::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $activity = new Activity();
        $activity->setCreatedAt(new DateTimeImmutable());
        $activity->setUser($user);
        $activity->setType($type);
        $activity->setMeta($meta);

        try {
            $this->messageFactory->validate($activity);
            $this->notificationService->notify($activity);

            $this->em->persist($activity);
            $this->em->flush();
        } catch (Throwable $exception) {
            $this->logger->error('Could not log Activity', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function getUserList(User $user): array
    {
        $activities = $this->repo->getUserDisplay($user);
        $eventIds = [];
        foreach ($activities as $activity) {
            $eventId = $activity->getMeta()['event_id'] ?? null;
            if (is_numeric($eventId)) {
                $eventIds[(int) $eventId] = true;
            }
        }
        $visible = array_flip($this->eventFilterService->getAccessibleEventIds(array_keys($eventIds)));

        return $this->prepareActivityList(array_filter($activities, static function (Activity $activity) use ($visible): bool {
            $eventId = $activity->getMeta()['event_id'] ?? null;

            return !is_numeric($eventId) || isset($visible[(int) $eventId]);
        }), true);
    }

    public function getAdminList(int $limit = 250, int $offset = 0, ?DateTimeImmutable $since = null, ?int $userId = null): array
    {
        return $this->prepareActivityList($this->repo->findRecentForAdmin($limit, $offset, $since, $userId));
    }

    /**
     * @param array<string, DateTimeImmutable|null> $sinceByRange
     * @return array{total: int, ranges: array<string, int>}
     */
    public function countByRange(array $sinceByRange, ?int $userId = null): array
    {
        return $this->repo->countByRange($sinceByRange, $userId);
    }

    public function getAdminDetail(int $id): ?Activity
    {
        $activity = $this->repo->find($id);
        if ($activity === null) {
            return null;
        }

        $activity->setMessage($this->messageFactory->build($activity)->render(true));

        return $activity;
    }

    /**
     * @return array<array{id: int, type: string, error: string}>
     */
    public function validateAllActivities(): array
    {
        $invalidActivities = [];
        $activities = $this->repo->findAll();

        foreach ($activities as $activity) {
            try {
                $this->messageFactory->validate($activity);
            } catch (Throwable $e) {
                $invalidActivities[] = [
                    'id' => $activity->getId(),
                    'type' => $activity->getType(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $invalidActivities;
    }

    private function prepareActivityList(array $list, ?bool $asHtml = false): array
    {
        $preparedList = [];
        foreach ($list as $activity) {
            $preparedList[] = $activity->setMessage($this->messageFactory->build($activity)->render($asHtml));
        }

        return $preparedList;
    }
}
