<?php declare(strict_types=1);

namespace Plugin\Fediverse\Service;

use App\EntityActionInterface;
use App\Enum\EntityAction;
use Doctrine\ORM\EntityManagerInterface;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Module\Task\Contract\TriggerInterface;
use Override;
use Plugin\Fediverse\Entity\PendingEvent;
use Plugin\Fediverse\Entity\Publication;
use Plugin\Fediverse\Repository\PendingEventRepository;
use Plugin\Fediverse\Repository\PublicationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Lock\LockFactory;
use Throwable;

readonly class PublishQueue implements TaskInterface, EntityActionInterface
{
    public const string IDENTIFIER = 'fediverse-publish';
    public const int TICK_BUDGET = 50;

    private const array EVENT_ACTIONS = [EntityAction::CreateEvent, EntityAction::UpdateEvent, EntityAction::DeleteEvent];

    public function __construct(
        private PendingEventRepository $pending,
        private PublicationRepository $publications,
        private PublicationService $publicationService,
        private EntityManagerInterface $em,
        private LockFactory $lockFactory,
        private TriggerInterface $trigger,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    #[Override]
    public function onEntityAction(EntityAction $action, int $entityId): void
    {
        if (!in_array($action, self::EVENT_ACTIONS, true)) {
            return;
        }

        $hasLivePublication = $this->publications->findLiveForEvent($entityId) instanceof Publication;
        if ($action === EntityAction::DeleteEvent && !$hasLivePublication) {
            return;
        }

        $this->queue([$entityId]);
    }

    /** @param list<int> $eventIds */
    public function queue(array $eventIds): void
    {
        $lock = $this->lockFactory->createLock('fediverse.pending');
        $lock->acquire(true);
        try {
            foreach (array_unique($eventIds) as $eventId) {
                if (!$this->pending->find($eventId) instanceof PendingEvent) {
                    $this->em->persist(new PendingEvent($eventId, $this->clock->now()));
                }
            }
            $this->em->flush();
        } finally {
            $lock->release();
        }

        $this->trigger->runSoon(self::IDENTIFIER);
    }

    #[Override]
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    #[Override]
    public function getCadence(): Cadence
    {
        return new Cadence('*/5 * * * *', offloaded: true);
    }

    #[Override]
    public function getDescription(): string
    {
        return sprintf(
            'Publishes changed events to the fediverse. An event that is created, edited or deleted is queued, and this task takes up to %d of them per run: it builds what the event shares under its scope\'s settings and sends a create, an update or a delete to the followers of its scope\'s account, or nothing when nothing changed. It runs as soon as something is queued and every five minutes for whatever is left.',
            self::TICK_BUDGET,
        );
    }

    #[Override]
    public function run(): Result
    {
        $published = 0;
        $failed = 0;
        foreach ($this->pending->findOldest(self::TICK_BUDGET) as $row) {
            $eventId = $row->getEventId();
            $this->em->remove($row);
            $this->em->flush();
            try {
                $this->publicationService->publish($eventId);
                ++$published;
            } catch (Throwable $e) {
                ++$failed;
                $this->logger->error('Fediverse: publishing an event failed.', ['eventId' => $eventId, 'exception' => $e]);
            }
        }

        if (($published + $failed) === self::TICK_BUDGET) {
            $this->trigger->runSoon(self::IDENTIFIER);
        }

        return new Result($failed > 0 ? Status::warning : Status::ok, sprintf('Checked: %d, failed: %d', $published, $failed));
    }
}
