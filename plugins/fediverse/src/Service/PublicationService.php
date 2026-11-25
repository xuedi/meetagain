<?php declare(strict_types=1);

namespace Plugin\Fediverse\Service;

use App\Entity\Event;
use App\Enum\EventStatus;
use App\Filter\Event\EventFilterService;
use App\Publisher\PluginSettings\Resolver;
use App\Repository\EventRepository;
use App\Service\Config\PluginService;
use App\Service\Event\EventScope;
use App\Service\Seo\NoindexService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Fediverse\ActivityPub\Actor;
use Plugin\Fediverse\ActivityPub\EventObject;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Entity\Publication;
use Plugin\Fediverse\Repository\AccountRepository;
use Plugin\Fediverse\Repository\FollowerRepository;
use Plugin\Fediverse\Repository\PublicationRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;

readonly class PublicationService
{
    public const int HORIZON_DAYS = 60;

    private const string PLUGIN_KEY = 'fediverse';
    private const string EVENT_ROUTE = 'app_event_details';
    private const array PUBLIC_STATUSES = [EventStatus::Published, EventStatus::Locked];

    public function __construct(
        private EventRepository $events,
        private PublicationRepository $publications,
        private FollowerRepository $followers,
        private AccountRepository $accounts,
        private AccountService $accountService,
        private ConfigService $configService,
        private EventScope $eventScope,
        private Resolver $resolver,
        private PluginService $pluginService,
        private EventFilterService $eventFilter,
        private NoindexService $noindex,
        private EventObject $eventObject,
        private Actor $actor,
        private DeliveryQueue $deliveries,
        private EntityManagerInterface $em,
        private LockFactory $lockFactory,
        private ClockInterface $clock,
    ) {}

    public function find(int $publicationId): ?Publication
    {
        return $this->publications->find($publicationId);
    }

    public function publish(int $eventId): void
    {
        $this->locked($eventId, fn() => $this->reconcile($eventId));
    }

    public function leave(Account $account): int
    {
        $this->accountService->retire($account);
        $withdrawn = 0;
        foreach ($this->publications->findLiveEventIdsFor($account) as $eventId) {
            $this->locked($eventId, function () use ($eventId, $account, &$withdrawn): void {
                $live = $this->publications->findLiveForEvent($eventId);
                if ($live instanceof Publication && $live->getAccount()->getId() === $account->getId()) {
                    $this->withdraw($live);
                    ++$withdrawn;
                }
            });
        }

        $actorId = $this->actor->id($account);
        $this->deliveries->queue($account, $this->followers->findInboxesFor($account), [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actorId . '#delete',
            'type' => 'Delete',
            'actor' => $actorId,
            'to' => [EventObject::PUBLIC],
            'object' => $actorId,
        ]);
        $this->followers->removeFor($account);

        return $withdrawn;
    }

    private function locked(int $eventId, callable $work): void
    {
        $lock = $this->lockFactory->createLock('fediverse.publish.' . $eventId);
        $lock->acquire(true);
        try {
            $work();
        } finally {
            $lock->release();
        }
    }

    private function reconcile(int $eventId): void
    {
        $event = $this->events->find($eventId);
        $live = $this->publications->findLiveForEvent($eventId);
        $draft = $event instanceof Event ? $this->eventScope->runForEvent($eventId, fn(): ?array => $this->draft($event)) : null;
        $draftAccountId = $draft === null ? null : $draft['account']->getId();
        if ($live instanceof Publication && $draftAccountId !== $live->getAccount()->getId()) {
            $this->withdraw($live);
            $live = null;
        }

        if ($draft === null || !$event instanceof Event) {
            return;
        }

        $hash = hash('sha256', json_encode($draft['document'], JSON_THROW_ON_ERROR));
        if (!$live instanceof Publication) {
            if ($this->isWithinHorizon($event)) {
                $this->create($event, $draft['account'], $draft['document'], $hash);
            }

            return;
        }

        if ($live->getHash() !== $hash) {
            $this->update($live, $event, $draft['document'], $hash);
        }
    }

    /**
     * @return array{account: Account, document: array<string, mixed>}|null
     */
    private function draft(Event $event): ?array
    {
        $isPublic =
            in_array($event->getStatus(), self::PUBLIC_STATUSES, true)
            && in_array(self::PLUGIN_KEY, $this->pluginService->getActiveList(), true)
            && $this->eventFilter->isEventAccessible((int) $event->getId())
            && !$this->noindex->shouldNoindex($this->pageRequest($event));
        if (!$isPublic) {
            return null;
        }

        $scopeId = $this->resolver->resolveScopeId();
        $account = $this->accountService->ensureForScope($scopeId);
        if (!$account instanceof Account) {
            return null;
        }

        return ['account' => $account, 'document' => $this->eventObject->build($event, $account, $this->configService->getConfigFor($scopeId))];
    }

    private function pageRequest(Event $event): Request
    {
        $locale = $this->eventObject->defaultLocale();
        $request = Request::create($this->eventObject->pageUrl($event, $locale));
        $request->setLocale($locale);
        $request->attributes->add([
            '_route' => self::EVENT_ROUTE,
            '_route_params' => ['_locale' => $locale, 'id' => $event->getId()],
            '_locale' => $locale,
            'id' => $event->getId(),
        ]);

        return $request;
    }

    /** @param array<string, mixed> $draft */
    private function create(Event $event, Account $account, array $draft, string $hash): void
    {
        $now = $this->clock->now();
        $publication = new Publication($account, (int) $event->getId(), $event->getStart(), $now);
        $this->em->persist($publication);
        $this->em->flush();

        $document = $this->document($publication, $draft, null);
        $publication->publish($document, $hash, $event->getStart(), $now);
        $account->lock($now);
        $this->em->flush();

        $this->deliveries->queue(
            $account,
            $this->followers->findInboxesFor($account),
            $this->eventObject->activity('Create', $account, $document, $hash, $now),
        );
        $this->announce($publication);
    }

    /** @param array<string, mixed> $draft */
    private function update(Publication $publication, Event $event, array $draft, string $hash): void
    {
        $now = $this->clock->now();
        $document = $this->document($publication, $draft, $now);
        $publication->publish($document, $hash, $event->getStart(), $now);
        $this->em->flush();

        $this->deliveries->queue(
            $publication->getAccount(),
            $this->audience($publication),
            $this->eventObject->activity('Update', $publication->getAccount(), $document, $hash, $this->clock->now()),
        );
    }

    private function withdraw(Publication $publication): void
    {
        $account = $publication->getAccount();
        $tombstone = ['id' => $this->eventObject->id($publication), 'type' => 'Tombstone'];
        $this->deliveries->queue(
            $account,
            $this->audience($publication),
            $this->eventObject->activity('Delete', $account, $tombstone, $publication->getHash(), $this->clock->now()),
        );
        $publication->delete($this->clock->now());
        $this->em->flush();
    }

    private function announce(Publication $publication): void
    {
        $account = $publication->getAccount();
        if ($account->isInstallation() || !$this->configService->getCeiling()->isAnnounce()) {
            return;
        }

        $installation = $this->accountService->ensureForScope(null);
        if (!$installation instanceof Account) {
            return;
        }

        $actorId = $this->actor->id($installation);
        $objectId = $this->eventObject->id($publication);
        $this->deliveries->queue($installation, $this->followers->findInboxesFor($installation), [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actorId . '#announces/' . $publication->getId(),
            'type' => 'Announce',
            'actor' => $actorId,
            'published' => $this->clock->now()->format(DATE_ATOM),
            'to' => [EventObject::PUBLIC],
            'cc' => [$actorId . '/followers', $this->actor->id($account)],
            'object' => $objectId,
        ]);
        $publication->markAnnounced();
        $installation->lock($this->clock->now());
        $this->em->flush();
    }

    /** @return list<string> */
    private function audience(Publication $publication): array
    {
        $inboxes = $this->followers->findInboxesFor($publication->getAccount());
        $installation = $publication->isAnnounced() ? $this->accounts->findLiveForScope(null) : null;
        if ($installation instanceof Account) {
            $inboxes = [...$inboxes, ...$this->followers->findInboxesFor($installation)];
        }

        return array_values(array_unique($inboxes));
    }

    /**
     * @param array<string, mixed> $draft
     * @return array<string, mixed>
     */
    private function document(Publication $publication, array $draft, ?DateTimeImmutable $updated): array
    {
        $document = [
            '@context' => EventObject::CONTEXT,
            'id' => $this->eventObject->id($publication),
            ...$draft,
            'published' => $publication->getCreatedAt()->format(DATE_ATOM),
        ];
        if ($updated instanceof DateTimeImmutable) {
            $document['updated'] = $updated->format(DATE_ATOM);
        }

        return $document;
    }

    private function isWithinHorizon(Event $event): bool
    {
        $now = $this->clock->now();

        return $event->getStart() > $now && $event->getStart() <= $now->modify(sprintf('+%d days', self::HORIZON_DAYS));
    }
}
